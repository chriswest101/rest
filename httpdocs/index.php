<?php

	/*
	 * @author Chris West
	 * @created 12/11/2015
	*/

	require __DIR__ . '/../Rest/RestServer/RestServer.php';
		
	$server = new \Rest\RestServer\RestServer;
	$server->handle();
	
	// print_r($server);

?>