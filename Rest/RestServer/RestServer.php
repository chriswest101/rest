<?php

	/**
	 * @author Chris West
	 * @created 26/08/2016
	*/

	namespace Rest\RestServer;
	require(__DIR__ . '/RestFormat.php');
	require(__DIR__ . '/RestException.php');
	require(__DIR__ . "/../../classes/database.class.php");
	require(__DIR__ . "/../../classes/common.class.php");
		 
	use \Exception;
	use \ReflectionClass;
	use \ReflectionObject;
    use \ReflectionMethod;
	
	class RestServer extends common {
		public $data,
		       $format,
			   $method,
			   $output,
			   $params 				= array(),
			   $path,
			   $root 				= "/",
			   $url,
			   $className;
	
		/** I AM THE CONSTRUCTOR! **/
		public function  __construct() {
			
			parent::__construct();

			$dir = dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
			if ($dir == '.') {
				$dir = '/';
			} else {
				// add a slash at the beginning and end
				if (substr($dir, -1) != '/') $dir .= '/';
				if (substr($dir, 0, 1) != '/') $dir = '/' . $dir;
			}
			$this->root = $dir;
		}
				
		/**
		 * This function will include the files we need for the request based on the name given in the request URI
		 * @param string of file name from request URI
		 * @return file included
		 */
		public function addClass($class) {
					
			// Check for a helper file before we load the class
			if (file_exists(__DIR__ . "/../../helpers/$class.helper.php")) 
				require_once __DIR__ . "/../../helpers/$class.helper.php";
			// Load the cache
			if (file_exists(__DIR__ . "/../../api/$class.api.php"))
				require_once __DIR__ . "/../../api/$class.api.php";
			else {
				// Class does not exist
				$this->handleError(404);
			}
			
		}
				
		/**
		 * This function will get any POST data submited for the request
		 * @return array of POST'ed data
		 */
		public function getData() {
			$fp = fopen('php://input', 'r');
 			$rawData = stream_get_contents($fp);
			parse_str($rawData, $arr);
			foreach($arr AS $key => $value) {
				if(!isset($this->data[$value])) {
					$this->data[$value] = $value;
				}
			}
			return $this->data;
		}
				
		/**
		 * This function will get the format for the return of our request for returning
		 * @return string of format type
		 */
		public function getFormat() {
			$format = RestFormat::PLAIN;
			$accept_mod = @preg_replace('/\s+/i', '', $_SERVER['HTTP_ACCEPT']); // ensures that exploding the HTTP_ACCEPT string does not get confused by whitespaces
			$accept = explode(',', $accept_mod);
			$override = '';
	
			// Give GET parameters precedence before all other options to alter the format
			$override = isset($_GET['format']) ? $_GET['format'] : $format;
			if (isset(RestFormat::$formats[$override])) {
				$format = RestFormat::$formats[$override];
			} elseif (in_array(RestFormat::JSON, $accept)) {
				$format = RestFormat::JSON;
			}
			$this->format = $format;

			return $this->format;
		}
		
		/**
		 * This function will get our request method type such s GET or POST
		 * @return string of request type
		 */
		public function getMethod() {
			$this->method = $_SERVER['REQUEST_METHOD'];

			return $this->method;
		}
		
		/**
		 * This function will get the path class name and function name for the request based on the request URI
		 * @param boolean $returnURL if we want to return the URL part
		 * @param boolean $returnClass if we want to return the class part
		 * @param boolean $returnGet if we want to return the get part
		 * @return string path/class name or get param
		 */
		public function getPath($returnURL = false, $returnClass = false, $returnGet = false) {
			$path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
			
			// remove root from path
			if ($this->root) {
				$path = preg_replace('/^' . preg_quote($this->root, '/') . '/', '', $path);
			}
			// Path name is now as folder/file
			$this->path = $path;
			// Split our path into folder then file array
			$path = explode("/", $path, 3);
			
			if (!isset($path[1])) {
				$this->handleError('405');
			}
			
			if($returnClass && isset($path[0])) {
				return $path[0];
			} 
			
			if($returnGet && isset($path[2])) {
				return $path[2];
			}
			
			if($returnURL && isset($path[1])) {
				return $path[1];
			}
		}
		
		/**
		 * This function will basically handle our API request. It is the function that is called first from our server
		 */
		public function handle() {
			$this->url = $this->getPath(true, false ,false);
			$this->className = $this->getPath(false, true, false);
			$this->method = $this->getMethod();
			$this->format = $this->getFormat();
			$this->data = array();

			if ($this->className) {
				$this->addClass($this->className);
			}
			
			if ($this->method == 'PUT' || $this->method == 'POST' || $this->method == 'DELETE') {
				$this->data = $this->getData();
			} elseif ($this->method == 'GET') {
				$queryString = $this->getPath(false, false, true);
				if($queryString) {
					$this->data = array($queryString);
				}
			}
			
			if ($this->className) {
				if (is_string($this->className)) {
					if (class_exists($this->className)) {
						$obj = new $this->className();
					} else {
						$this->handleError(404, "Class $this->className does not exist");
					}
				}
				
				$obj->server = $this;
				try {
					if (method_exists($obj, 'init')) {
						$obj->init();
					}
					if (method_exists($obj, $this->url)) {
						$result = call_user_func_array(array($obj, $this->url), $this->data);
						$this->sendData($result);
					} else {
						$this->handleError(404, "Function {$this->url} not found");
					}
					
				} catch (RestException $e) {
					$this->handleError($e->getCode(), $e->getMessage());
				}			
			
			} else {
				$this->handleError(406);
			}
		}
		
		/**
		 * This function will return any errors that may happen with the correct response code
		 * @param string $statusCode of the error
		 * @param string $errorMessage is the string of our error message if we have one
		 */
		public function handleError($statusCode, $errorMessage = null) {
			$method = "handle$statusCode";
			
			// Creating the message to show
			$message = $errorMessage ? $errorMessage : (isset($this->codes[$statusCode]) ? $this->codes[$statusCode] : "Error");
			$this->setStatus($statusCode, $message);
			$this->sendData(array('error' => array('code' => $statusCode, 'message' => $message)));
			$this->hasError = true;
			exit;
		}
				
		/**
		 * This function will send our data back to whatever that called it
		 * @param array of our data to return
		 * @return our hson_encoded data to the caller
		 */
		public function sendData($data) {
			header("Cache-Control: no-cache, must-revalidate");
			header("Expires: 0");
			header('Content-Type: ' . $this->format);
			
			if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}
			
			$this->output = json_encode($data);
			echo $this->output;
		}
		
		/**
		 * This will set the status of our header for any errors we have
		 * @param string $code of our error type
		 * @param string $errorMessage our error message if we have one
		 */
		public function setStatus($code, $errorMessage = null) {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			$errorMessage = isset($this->codes[strval($code)]) ? $this->codes[strval($code)] : ($errorMessage ? $errorMessage : "Error");
			$header = $code.' '.$errorMessage;
			if (!preg_match("/^4(1([8-9])|2([0-6]))$/", $code))
				header("$protocol $header");
			$this->header = array($code, $errorMessage);
		}
		
		/**
		 * This function will be our end point if the user is not authorized to access the API
		 */
		public function unauthorized($ask = false) {
			$this->header = "401 {$this->codes[401]}";
			$this->handleError("401", "You are not authorized to access this resource.");
		
		}
		
		/**
		 * This will set response code for our header
		 * @param string $code of our response
		 */
		public function setResponseCode($code) {
			if(isset($this->codes[$code])) {
				$text = $this->codes[$code];
				http_response_code($code);
			}
		}
		
	
		public $codes = array(
			'100' => 'Continue',
			'200' => 'OK',
			'201' => 'Created',
			'202' => 'Accepted',
			'203' => 'Non-Authoritative Information',
			'204' => 'No Content',
			'205' => 'Reset Content',
			'206' => 'Partial Content',
			'300' => 'Multiple Choices',
			'301' => 'Moved Permanently',
			'302' => 'Found',
			'303' => 'See Other',
			'304' => 'Not Modified',
			'305' => 'Use Proxy',
			'307' => 'Temporary Redirect',
			'400' => 'Bad Request',
			'401' => 'Unauthorized',
			'402' => 'Payment Required',
			'403' => 'Forbidden',
			'404' => 'Not Found',
			'405' => 'Method Not Allowed',
			'406' => 'Not Acceptable',
			'409' => 'Conflict',
			'410' => 'Gone',
			'411' => 'Length Required',
			'412' => 'Precondition Failed',
			'413' => 'Request Entity Too Large',
			'414' => 'Request-URI Too Long',
			'415' => 'Unsupported Media Type',
			'416' => 'Requested Range Not Satisfiable',
			'417' => 'Expectation Failed',
			'418' => 'Client Error',
			'419' => 'Client Error',
			'420' => 'Client Error',
			'421' => 'Client Error',
			'422' => 'Client Error',
			'423' => 'Client Error',
			'424' => 'Client Error',
			'425' => 'Client Error',
			'426' => 'Client Error',
			'500' => 'Internal Server Error',
			'501' => 'Not Implemented',
			'503' => 'Service Unavailable'
		);
	}

/* ?> These are removed to stop header issues */