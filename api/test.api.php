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
		 * @output Array of methods
		 */
		public function authorise($clientId, $clientSecurityCode, $userName, $userPassword) {
			$this->getClient($clientId);
			$this->getUser($userName);
			
			$passwordCheck = crypt($userPassword, $this->salt)

			if($passwordCheck != $this->password) {
				$this->unauthorized();
			}
		}
	}

/* ?> These are removed to stop header issues */