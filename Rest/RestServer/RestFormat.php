<?php

	/**
	 * @author Chris West
	 * @created 26/08/2016
	*/

	namespace Rest\RestServer;
	 
	// Constants used in RestServer Class.
	class RestFormat {
		const PLAIN = 'text/plain';
		const HTML  = 'text/html';
		const JSON  = 'application/json';
		const XML   = 'application/xml';
	
		
		static public $formats = array(
			'plain' => RestFormat::PLAIN,
			'txt'   => RestFormat::PLAIN,
			'html'  => RestFormat::HTML,
			'json'  => RestFormat::JSON
		);
	}
	
/* ?> These are removed to stop header issues */