<?php

	/**
	 * @author Chris West
	 * @created 13/11/2015
	 */
	 
	namespace Rest\RestServer;
	
	date_default_timezone_set('Europe/London');
	
	error_reporting(E_ALL);
	ini_set("display_errors", "On");
	// MySQL Server
	define("DB_HOST", "localhost");
	define("DB_USER", "acwest10");
	define("DB_PASS", "Kplant10.");
	 
	class common extends database {
		
		/**
		This common class is to host all of the common classes which can be used across all API's.
		**/
		public function __construct() {
			$this->startTime = microtime(true);
			parent::__construct();
		}
		
		
		/**
		 * @title Alpha Only
		 * @description returns a string of alphabetical characters only (removes all other characters)
		 * @arg:string string
		 * @arg:case (lower|upper) [default = false]
		 * @sample 1 plus 5 = six
		 * @return plussix
		 */
		public function alphaonly($string, $case = false) {
			$string = preg_replace('/[^A-Z]/i', '', $string);
			switch ($case) {
				case "lower":	return $this->lowercase($string);	break;
				case "upper": 	return $this->uppercase($string);	break;
				default:		return $string;
			}
		}	
		
		/**
		 * @title Alpha-Nemeric Only
		 * @description returns a string of alphabetical and numerical characters only (removes all other characters)
		 * @arg:string string
		 * @arg:case (lower|upper) [default = false]
		 * @sample 1 plus 5 = six
		 * @return 1plus5six
		 */
		public function alphanumericonly($string, $case = false) {
			$string = preg_replace('/[^A-Z0-9]/i', '', $string);
			switch ($case) {
				case "lower":	return $this->lowercase($string);	break;
				case "upper": 	return $this->uppercase($string);	break;
				default:		return $string;
			}
		}
		
		
		/**
		 * @title Check Required Fields
		 * @description Check though an array that required fields are present and not empty
If this function finds and empty field it will generate a 418 Error with the response.
		 * @arg:fields array
		 * @arg:data object
		 * @return na
		 */
		public function checkRequiredFields($fields, $data) {
			foreach ($fields as $field) {
				if (!isset($data->$field) || empty($data->$field)) {
					$this->handleError(418, "Required field missing [$field]");
				}
			}
		}
		
		
		/**
		 * @title File Get Contents
		 * @description This function was made to work like the PHP function file_get_contents only it is using the Curl method which is faster.
		 * @arg:url string
		 */
		public function fileGetContents($url) {
			$curl = curl_init("$url");
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);
			curl_close($curl);
			return $response;
		}
		
		
		/**
		 * @format
		 * @description The format function is to handle variables to make them consistent
		 * @arg:data string|int
		 * @arg:type string (address|alpha|alphanumeric|filesize|lower|numeric|postcode|regno|tel|ucwords|upper|website)
		 * @return Formatted string
		 */
		public function format($data, $type) {
			switch ($type) {
				case "address":
					$uppercasefields = array('address1', 'address2', 'town', 'county', 'country');
					foreach ($uppercasefields as $field) {
						if (isset($data->$field) && $data->$field)
							$data->$field = strtoupper($data->$field);
					}
						
					if (isset($data->postcode) && $data->postcode)
						$data->postcode = $this->format($data->postcode, "postcode");
					return $data;
				case "alpha":			
					return $this->alphaonly($data);
				case "alphanumeric":
					return $this->alphanumericonly($data);
				case "alphanumerictolower":
					return $this->alphanumericonly($data, "lower");
				case "alphanumerictoupper":
					return $this->alphanumericonly($data, "upper");
				case "filesize":
					$data = $this->numericonly($data);
					$units = array('B', 'KB', 'MB', 'GB', 'TB');
					for ($i = 0; $data >= 1024 && $i < 4; $i++) 
						$data /= 1024;
					return array('size' => round($data, 2), 'unit' => $units[$i]);
				case "email":
				case "login":
				case "lower":
				case "lowercase":
					return strtolower($data);
				case "numeric":
					return $this->numericonly($data);
				case "postcode":
					return $this->formatPostcode($data, true);
				case "regno":
				case "regmark":
					return strtoupper(substr($this->alphanumericonly($data), 0, 4)." ".substr($this->alphanumericonly($data), 4, 3));
				case "tel":
				case "telephone";
					return trim(substr($this->numericonly($data), 0, 5)." ".substr($this->numericonly($data), 5));
				case "name":
				case "ucwords":
					return ucwords(strtolower($data));
				case "upper":
				case "uppercase":
					return strtoupper($data);
				case "url":
				case "website":
					$url = strtolower($data);
					$url = str_replace(array(" ", "http://", "https://"), "", $url);
					if (preg_match("/^([a-z0-9_-]+\.)*[a-z0-9_-]+(\.[a-z]{2,6}){1,2}$/", $url))
						return "http://$url";
					return false;
				default:
					return $data; // If nothing matches we will just return the data
			}
		}

		
		/**
		 * @title Format Postcode
		 * @description Formats a postcode into a format with or without the middle space
		 * @arg:postcode string
		 * @arg:spaces (true|false) [default = false]
		 * @sample "ex51ew", true
		 * @return EX5 1EW
		 */
		public function formatPostcode($postcode, $spaces = false) {
			$postcode = $this->uppercase(preg_replace("/[^a-zA-Z0-9]+/", "", $postcode));
			if (preg_match("/^([A-PR-UWYZ0-9][A-HK-Y0-9][AEHMNPRTVXY0-9]?[ABEHMNPRVWXY0-9]?[0-9][ABD-HJLN-UW-Z]{2})$/", $postcode)) {
	    		$postcode = strlen($postcode) > 4 ? substr($postcode, 0, (strlen($postcode) - 3)).($spaces ? " " : "").substr($postcode, -3) : $postcode;
 			}
			return $postcode;	
		}
		
		
		/**
		 * @title Local Script
		 * @description Returns true or false depending whether or not the script has been executed on a local server or not
		 * @return true|false
		 */
		public function localScript() {
			return preg_match("/MAMP|repositories/i", $_SERVER['DOCUMENT_ROOT']);
		}

		
		/**
		 * @title Lowercase
		 * @description Returns a string in lowercase format
		 * @arg:string string
		 * @sample "HELLO WORLD"
		 * @return hello world
		 */
		public function lowercase($string) {
			return strtolower($string);
		}
		
		
		/**
		 * @title Nemeric Only
		 * @description returns a string of numerical characters only (removes all other characters)
		 * @arg:string string
		 * @arg:case (lower|upper) [default = false]
		 * @sample 1 plus 5 = six
		 * @return 15
		 */
		public function numericonly($string, $case = false) {
			return preg_replace('/[^0-9]/i', '', $string);
		}
		
		
		/**
		 * @title Remove End
		 * @description Removes the last character of a string when matched
		 * @arg:string text, string end [default "s"]
		 * @sample "We have 1 products";
		 * @return We have 1 product
		 */	
		public static function removeEnd($text, $end = "s") {
			if (strtolower(substr($text, (0 - strlen($end)))) == $end)
				$text = substr($text, 0, (0 - strlen($end)));
				
			return $text;
		}
		
		
		/**
		 * @title Show Time
		 * @description Not related to clowns or circus animals!<br>This function will return the current time based on when the script was started and can work as a timer.
		 * @return decimal (e.g. 0.05)
		 */
		public function showTime() {
			return sprintf("%01.4f", microtime(true) - $this->startTime);
		}
		
		
		/**
		 * @title Timer
		 * @description This function was made as I didn't want to use the showTime() too much because I hate the name. And the comments were too good to remove the function completely.
		 * @return decimal (e.g. 0.05)
		 */
		public function timer() {
			return $this->showTime();
		}
		
		
		/**
		 * @title UK Postcode
		 * @description Checks to see whether the string is a valid UK postcode. If it is then an array of matches is returned
		 * @arg:postcode string
		 * @return
		 */
		public function ukPostcode($postcode, $method = "matches") {
			if (!preg_match("/^([A-PR-UWYZ]([0-9]{1,2}|([A-HK-Y][0-9]([0-9ABEHMNPRV-Y])?)|[0-9][A-HJKPS-UW]))( ?[0-9][ABD-HJLNP-UW-Z]{2}){0,1}$/", strtoupper($postcode), $matches)) {
				return false;
			}
			switch ($method) { // This is so we can return different responses from the postcode
				case "formatted":	return $this->formatPostcode($matches[0]);		// EX5 1EW
				case "no_spaces":	return $this->alphanumericonly($matches[0]); 	// EX51EW
				case "lookup":		return $matches[1];								// EX5
				default: return $matches; // default method is "matches"	
			}
			
		}
			
			
		/**
		 * @title Uppercase
		 * @description Returns a string in uppercase format
		 * @arg:string string
		 * @sample "time to the make the tea!";
		 * @return TIME TO MAKE THE TEA!
		 */	
		public function uppercase($string) {
			return strtoupper($string);
		}
		
		
		/**
		 * @title Check for a valid email address
		 * @description Do a <code>preg_match</code> check on an email address and return true or false on whether it is valid or not
		 * @arg:email string [email address]
		 * @return true|false
		 */
        public function validEmail($email) {
			if(preg_match('/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/', $email))
				return true;

			return false;
		}
		
		
		/**
		 * @title Check for a valid telephone number
		 * @description Do a <code>preg_match</code> check on a telephone number and return true or false on whether it is valid or not
		 * @arg:telephone string [telephone number]
		 * @return true|false
		 */
		public static function validTelephone($tel) {
			if (preg_match("/^[0-9\-\+ ]{6,16}$/", $tel))
				return true;
				
			return false;
		}
	}
	 
/* ?> These are removed to stop header issues */