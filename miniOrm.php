<?php

/*
 * miniOrm
 * Version: 2.0.0
 * Copyright : Cédric Mouleyre / @MrManchot
 */

namespace miniOrm;
use PDO;
use Exception;
 
# Initialisation
include('miniOrm.config.php');

# Autoload
spl_autoload_register(function ($class) {
	$classFile = __DIR__._MO_CLASS_DIR_ . $class . '.php';
    if(file_exists($classFile)) include($classFile);
});