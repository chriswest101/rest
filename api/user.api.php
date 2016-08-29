<?php

	/**
	 * @author Chris West
	 * @created 27/08/2016
	 */
	 
	use \Rest\RestServer\RestException;
	
	
	class user extends userHelper {		
		/**
		 * This endpoint validates client and user credentials and if both are valid it returns an authorization token. If not returns an error describing the error
		 * @param int $clientId is the id of the client for which we API is being called from
		 * @param string $clientSecurityCode is the secret security code of the client
		 * @param string $userName is the username of the user
		 * @param string $userPassword is the password of the user
		 * @return string security token
		 */
		public function authorise($clientId, $clientSecurityCode, $userName, $userPassword) {
			$this->checkLogin($userName);
						
			$this->getClient($clientId);
			$this->getUser($userName);
			
			if(!$this->getClientId()) {
				$this->server->unauthorized();
			}
			
			$passwordCheck = crypt($userPassword, '$2y$6$'.$this->getSalt());
			if($passwordCheck != $this->getPassword()) {
				$this->server->unauthorized();
			}
			
			$this->server->setResponseCode(201);
			
			return $this->createAuthorization();
		}
		
		/**
		 * This endpoint accepts a generated token. If the token is valid it will return information pertaining to the client and the user
		 * @param string $token is the generated secure token
		 * @return array array details of client and user
		 */
		public function me($token) {
			$this->checkToken($token);
				
			// Check if the token is valid
			if($token != $this->getToken()) {
				$this->server->unauthorized();
			}
			
			// Check if the expiry date is in the past
			if($this->getExpiryDate() < date("Y-m-d H:i:s")) {
				$this->server->unauthorized();
			}
			
			$user = $this->getUser($this->getUsername());
			$client = $this->getClient($this->getClientId());
			
			$this->server->setResponseCode(200);
			
			return array(
					'client'		=> array(
												'clientName'		=> $this->getClientName()
										),
					'user'			=> array(
												'name'		=> $this->getName(),
												'email'		=> $this->getEmail()
										)
			);
		}
		
		/**
		 * This endpoint will deauthorize the user
		 * @param string application id of the client
		 * @param string security code of the client
		 * @param string authorization code
		 */
		public function deauthorise($clientId, $clientSecurityCode, $token) {
			$this->checkToken($token);
			$this->getClient($clientId);
			
			// Check if the token is valid
			if($token != $this->getToken()) {
				$this->server->unauthorized();
			}
			
			// Check if the client is valid
			if(!$this->getClientId()) {
				$this->server->unauthorized();
			}
			
			$this->deauthorizeUser($token);
			
			$this->server->setResponseCode(200);
		}
	}

/* ?> These are removed to stop header issues */