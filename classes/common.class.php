<?php

	/**
	 * @author Chris West
	 * @created 26/08/2016
	 */ 
	 
	namespace Rest\RestServer;
	
	date_default_timezone_set('Europe/London');	
	ini_set("display_errors", "On");
	
	 
	class common extends database {
		
		/**
		 * Create a unigue id
		 * @return string Unique identifier
		 */
		public function generateUniqueID() {
			return uniqid();
		}	
	}
	 
/* ?> These are removed to stop header issues */