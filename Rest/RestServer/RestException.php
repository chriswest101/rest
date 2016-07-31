<?php

	/*
	 * @author Chris West
	 * @created 12/11/2015
	*/

    namespace Rest\RestServer;
    
    use \Exception;
    
    class RestException extends Exception {
        public function __construct($code, $message = null) {
            parent::__construct($message, $code);
        }
    }

/* ?> These are removed to stop header issues */