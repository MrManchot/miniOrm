<?php

	include(__DIR__.'/../miniOrm.php');

	spl_autoload_register(function ($class) {
		$classFile = __DIR__.'/controller/'. $class . '.php';
   		if(file_exists($classFile)) require_once($classFile);
	});
		
	$controller = isset($_GET['controller']) ? $_GET['controller'] : 'home';
	$controllerClass = 'AdminController'.ucfirst($controller);
	new $controllerClass;