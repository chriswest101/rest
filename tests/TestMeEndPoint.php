<?php
	/**
	 * @author Chris West
	 * @created 27/08/2016
	*/

	function createUser() {
		$fields = array(
				'clientId' 				=> urlencode("1"),
				'clientSecurityCode' 	=> urlencode("57c2b1b5e73d5"),
				'userName'				=> urlencode("chriswest"),
				'password' 				=> urlencode("password123")
		);
		return $fields;
	}
	
	function performCurl($url, $fields = false, $type) {
		if($fields) {
			//url-ify the data for the POST
			foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
			rtrim($fields_string, '&');
		}
		
		//open connection
		$ch = curl_init();
		
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		
		
		if($fields) {
			curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch,CURLOPT_CUSTOMREQUEST, $type);
			curl_setopt($ch,CURLOPT_POST, count($fields));
			curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		}
		
		//execute post
		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		echo " - HTTP response code: ".$httpcode."<br/>";
		return $result;
	}
	
	echo "Functional Tests for Me API.<br/><br/>";
	
	echo " - Calling http://rest.christophermwest.co.uk/user/authorise<br/>";
	$url = "http://rest.christophermwest.co.uk/user/authorise";
	$fields = createUser();
	echo " - sending data ".print_r($fields, true)."<br/>";
	$token = performCurl($url, $fields, "POST");
	
	sleep(1);
	
	echo " - Result for authorisation end point ".print_r($token, true)."<br/>";
		
	echo " - Calling http://rest.christophermwest.co.uk/user/me/ <br/>";
	$url = "http://rest.christophermwest.co.uk/user/me/".json_decode($token);
	$result = performCurl($url, false, "GET");

	//echo " - Result for me end point ".print_r($result, true)."<br/>";
	

/* ?> These are removed to stop header issues */