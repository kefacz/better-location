<?php
/**
 * Convert all errors to exceptions
 */

use Tracy\Debugger;

error_reporting(E_ALL);

DEFINE('LOG_FOLDER', __DIR__ . '/../data/log/');
DEFINE('DATE_FORMAT', 'Y-m-d');
DEFINE('TIME_FORMAT', 'H:i:s');
DEFINE('DATETIME_FORMAT', DATE_FORMAT . ' ' . TIME_FORMAT);

// @TODO in case of error, show some info about renaming config.local.example.php to config.local.php
require_once __DIR__ . '/../data/config.local.php';

require_once __DIR__ . '/../vendor/autoload.php';

Tracy\Debugger::enable(DEVELOPMENT_IPS, __DIR__ . '/../data/log/');
Tracy\Debugger::$strictMode = true;
Tracy\Debugger::$logSeverity = E_NOTICE | E_WARNING;

/**
 * @param $className
 * @throws Exception
 */
function dummyAutoloader($className) {
	$path = str_replace('\\', '/', $className);
	$file = str_replace('\\', '/', __DIR__) . '/libs/' . $path . '.php';
	if (file_exists($file)) {
		require_once $file;
	} else {
		throw new \Exception(sprintf('Class "%s" cannot be loaded, file "%s" does not exists.', $path, $file));
	}
}

spl_autoload_register('dummyAutoloader');

Debugger::log('Request: ' . ($_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] . ' - ' : '') . $_SERVER['REQUEST_URI'], Debugger::DEBUG);
