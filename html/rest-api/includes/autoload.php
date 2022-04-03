<?php


use RestAPI\Core\ErrorResponse;
use RestAPI\Core\Tools\Database;

define('REST_API_VERSION', 	'3.0.0');
define('REST_API_DIR', 		dirname(__DIR__));
define('REST_API_DOC_ROOT', dirname(__DIR__, 3));

require_once REST_API_DOC_ROOT.'/vendor/autoload.php';


// load environment
$dotenv = Dotenv\Dotenv::createImmutable(REST_API_DOC_ROOT);
$dotenv->load();


define('APP_URL',		$_ENV['VUE_DEV'] ? $_ENV['VUE_URL'] : $_ENV['APP_URL']);
define('ENVIRONMENT', 	$_ENV['ENVIRONMENT']);

if(ENVIRONMENT === 'local'){
	ini_set('log_errors', 1);
	ini_set('error_log', REST_API_DOC_ROOT.'/logs/debug.log');
}

spl_autoload_register(function($class){

	$prefix 	= 'RestAPI\\';
	$base_dir 	= REST_API_DIR.'/class/';
	$len		= strlen($prefix);

	if(strncmp($prefix, $class, $len) !== 0) return;

	$relativeClass 	= substr($class, $len);
	$file			= rtrim($base_dir, '/').'/'.str_replace('\\', '/', $relativeClass).'.php';
	$file           = str_replace('//', '/', $file);

	if(file_exists($file)){
		require $file;
	}else{
		die('Missing Class file: '.$file);
	}
});


// setup global variables
global $current_user, $api_db, $api_settings;

require REST_API_DIR.'/includes/functions.php';

$current_user = false;
$api_settings = require REST_API_DIR.'/includes/config.php';
// TODO add $api_settings validation to force valid values!!!

define('REST_API_DEBUG', $api_settings['debug.enabled']);

// Database connection
$api_db = new Database(
	$api_settings['database.user'],
	$api_settings['database.password'],
	$api_settings['database.name'],
	$api_settings['database.host']
);

if($api_db->last_error){
	new ErrorResponse('database_init_error', $api_db->last_error);
}

$api_db->prefix = $api_settings['database.prefix'];
$api_db->accounts = $api_db->prefix.'accounts';
$api_db->sessions = $api_db->prefix.'sessions';
$api_db->tokens = $api_db->prefix.'tokens';
$api_db->passwords = $api_db->prefix.'passwords';



