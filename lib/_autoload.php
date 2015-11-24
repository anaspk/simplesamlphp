<?php

/**
 * This file is a backwards compatible autoloader for SimpleSAMLphp.
 * Loads the Composer autoloader.
 *
 * We have also added support for loading some selected Convo Services
 * classes to this autoloader too.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */

if (!defined('LOCAL_DB_SERVICES_VERSION')) define('LOCAL_DB_SERVICES_VERSION', SERVER_VERSION);

// Overridden constants based on inherited constants
if (!defined('LOCAL_LATEST_SERVICES_PATH')) define('LOCAL_LATEST_SERVICES_PATH', realpath($_SERVER['DOCUMENT_ROOT'] . "/scrybe/amfphp1_9/services/db_services_" . LOCAL_DB_SERVICES_VERSION) . "/");

/**
 * Old Autoload function for simpleSAMLphp.
 *
 * It will autoload some whitelisted Convo classes.
 *
 * @param $className  The name of the class.
 */
function SimpleSAML_autoload_old($className) {

	$convoClasses = array(
		'AccountLogin' => 'accounts/AccountLogin.php',
	);
	
	if (array_key_exists($className, $convoClasses)) {
		$file = LOCAL_LATEST_SERVICES_PATH . $convoClasses[$className];
	}

	if(isset($file) && file_exists($file)) {
		require_once($file);
	}
}

spl_autoload_register('SimpleSAML_autoload_old');

// Newer autoloader of SSP follows.

// SSP is loaded as a separate project
if (file_exists(dirname(dirname(__FILE__)).'/vendor/autoload.php')) {
    require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
} else {  // SSP is loaded as a library.
    if (file_exists(dirname(dirname(__FILE__)).'/../../autoload.php')) {
        require_once dirname(dirname(__FILE__)).'/../../autoload.php';
    } else {
        throw new Exception('Unable to load Composer autoloader');
    }
}
