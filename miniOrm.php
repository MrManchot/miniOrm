<?php

/*
 * miniOrm
 * Version: 2.0.0
 * Copyright : Cédric Mouleyre / @MrManchot
 */
 
// namespace miniOrm;
// use PDO;
// use Exception;

# Initialisation
define('_MO_DIR_', __DIR__);
include('miniOrm.config.php');

# Autoload
spl_autoload_register(function ($class) {
	// $class = str_replace('miniOrm\\', '', $class);
	$classFile = _MO_DIR_._MO_CLASS_DIR_ . $class . '.php';
    if(file_exists($classFile))
    	require_once($classFile);
});