<?php

	/**
	 * @author Chris West
     * @created 26/08/2016
	*/

    namespace Rest\RestServer;
    
    use \Exception;
    
    class RestException extends Exception {
        public function __construct($code, $message = null) {
            parent::__construct($message, $code);
        }
    }

/* ?> These are removed to stop header issues */