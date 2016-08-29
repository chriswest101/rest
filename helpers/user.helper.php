<?php
    
    /**
    * @author Chris West 
    * @created 27/06/2016
    */

    class userHelper {
    	var  	$clientId 			= false,
    			$clientName 		= false,
    			$clientSecurityCode = false;

    	var  	$userId 			= false,
    			$userName 			= false,
    			$password 			= false,
    			$salt 				= false,
    			$name 				= false,
    			$email 				= false;
    	
    	var 	$tokenId			= false,
    			$token				= false,
    			$loginDate			= false,
    			$expiryDate			= false;
        
    	/**
    	 * The will get the client details for a particular client
    	 * @param string client id is the id of the calling client
    	 */
        public function getClient($clientId) {
        	$client = $this->server->queryRow("SELECT id, client_name, security_code FROM `acwest10_user_auth_api`.`clients` WHERE id = :id", array("id" => $clientId));

        	$this->setClientId($client->id);
        	$this->setClientName($client->client_name);
        	$this->setClientSecurityCode($client->security_code);
        }
		
        /**
         * This will set the client id
         * @param string client id is the id of the calling client
         */
        public function setClientId($clientId) {
        	$this->clientId = $clientId;
        }
	
        /**
         * This will set the client name
         * @param string client name is the name of the calling client
         */
        public function setClientName($clientName) {
        	$this->clientName = $clientName;
        }
	
        /**
         * This will set the client security code
         * @param string client security code is the security code of the calling client
         */
        public function setClientSecurityCode($clientSecurityToken) {
        	$this->clientSecurityToken = $clientSecurityToken;
        }

        /**
         * This will get the client id
         */
        public function getClientId() {
        	return $this->clientId;
        }

        /**
         * This will get the client name
         */
        public function getClientName() {
        	return $this->clientName;
        }

        /**
         * This will get the client security code
         */
        public function getClientSecurityCode() {
        	return $this->clientSecurityToken;
        }

        /**
         * This will get the user who is doing the request
         * @param string userName is the username of the user
         */
        public function getUser($userName) {
        	$user = $this->server->queryRow("SELECT id, user_name, password, salt, name, email FROM `acwest10_user_auth_api`.`users` WHERE user_name = :user_name", array("user_name" => $userName));

        	$this->setUserId($user->id);
        	$this->setUserName($user->user_name);
        	$this->setPassword($user->password);
        	$this->setSalt($user->salt);
        	$this->setName($user->name);
        	$this->setEmail($user->email);
        }

        /**
         * This will set the user id
         * @param string user Id is the id of the user
         */
        public function setUserId($userId) {
        	$this->userId = $userId;
        }

        /**
         * This will set the user name
         * @param string user name is the name of the user
         */
        public function setUserName($userName) {
        	$this->userName = $userName;
        }

        /**
         * This will set the user password
         * @param string user password is the password of the user
         */
        public function setPassword($password) {
        	$this->password = $password;
        }

        /**
         * This will set the user salt
         * @param string user salt is the salt of the user
         */
        public function setSalt($salt) {
        	$this->salt = $salt;
        }
        
        /**
         * This will set the user name
         * @param string user name is the name of the user
         */
        public function setName($name) {
        	$this->name = $name;
        }

        /**
         * This will set the user email
         * @param string user email is the email of the user
         */
        public function setEmail($email) {
        	$this->email = $email;
        }

        /**
         * This will get the user id
         */
        public function getUserId() {
        	return $this->userId;
        }

        /**
         * This will get the user name
         */
        public function getUserName() {
        	return $this->userName;
        }

        /**
         * This will get the user password
         */
        public function getPassword() {
        	return $this->password;
        }

        /**
         * This will get the user salt
         */
        public function getSalt() {
        	return $this->salt;
        }
        
        /**
         * This will get the user name
         */
        public function getName() {
        	return $this->name;
        }

        /**
         * This will get the user email
         */
        public function getEmail() {
        	return $this->email;
        }
        
        /**
         * This will create a authorisation login for the user with a token
         */
        public function createAuthorization() {
        	$token = $this->server->generateUniqueID();
        	$insert = array(
        			'token'			=> $token,
        			'client_id'		=> $this->getClientId(),
        			'user_name'		=> $this->getUserName(),
        			'login_date'	=> date("Y-m-d H:i:s"),
        			'expiry_date'	=> date("Y-m-d H:i:s", strtotime("+1 hour"))
        	);
        	$this->server->insert("`acwest10_user_auth_api`.`login`", $insert);
        	
        	return $token;
        }
        
        /**
         * This will check to see if a valid token already exists and if it does it will void it
         * @param string username is the username of the user loging in
         */
        public function checkLogin($userName) {
        	$login = $this->server->queryRow("SELECT id, token, client_id, user_name, login_date, expiry_date FROM `acwest10_user_auth_api`.`login` WHERE user_name = :user_name AND expiry_date > NOW()", array("user_name" => $userName));
        	
        	if(count($login)) {
        		$this->deauthorizeUser($login->token);
        	}
        }
        
        /**
         * This will check the token of the user to check it it valid
         * @param string token is the unique token assigned to that user
         */
        public function checkToken($token) {
        	$login = $this->server->queryRow("SELECT id, token, client_id, user_name, login_date, expiry_date FROM `acwest10_user_auth_api`.`login` WHERE token = :token", array("token" => $token));
        	
        	$this->setUserName($login->user_name);
        	$this->setTokenId($login->id);
        	$this->setToken($login->token);
        	$this->setClientId($login->client_id);
        	$this->setLoginDate($login->login_date);
        	$this->setExpiryDate($login->expiry_date);
        }
        
        /**
         * This will set the token id of the token
         * @param int tokenid is the id of the token
         */
        public function setTokenId($tokenId) {
        	$this->tokenId = $tokenId;
        }
        
        /**
         * This will set the token
         * @param string token is the unique token
         */
        public function setToken($token) {
        	$this->token = $token;
        }
        
        /**
         * This will set the token login date
         * @param string login date is the login date of the user
         */
        public function setLoginDate($loginDate) {
        	$this->loginDate = $loginDate;
        }
        
        /**
         * This will set the token login expiry date
         * @param string login expiry date is the date of expiry for the login
         */
        public function setExpiryDate($expiryDate) {
        	$this->expiryDate = $expiryDate;
        }
        
        /**
         * This will get the token id
         */
        public function getTokenId() {
        	return $this->tokenId;
        }
        
        /**
         * This will get the token
         */
        public function getToken() {
        	return $this->token;
        }
        
        /**
         * This will get the token login date
         */
        public function getLoginDate() {
        	return $this->loginDate;
        }
        
        /**
         * This will get the token expiry date
         */
        public function getExpiryDate() {
        	return $this->expiryDate;
        }
        
        /**
         * This will deauthorise a user
         * @param string token is the token to expire
         */
        public function deauthorizeUser($token) {
        	$query = $this->server->query("DELETE FROM `acwest10_user_auth_api`.`login` WHERE token = '$token'");
        }
    }   


/* ?> These are removed to stop header issues */