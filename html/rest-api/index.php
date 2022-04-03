<?php
/**
 * REST API Handler
 *
 * @author 		Gregor Müller-Elmau @ schalk&friends
 * @since		02/2022
 */

use RestAPI\Core\ErrorResponse;
use RestAPI\Core\Errors\NoticeException;
use RestAPI\Core\Errors\WarningException;
use RestAPI\Core\Server;

/**
 * Convert PHP Warnings into Exceptions.
 * - prevent inline output.
 * - can be outputted as JSON error response.
 */
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): void {
	throw new WarningException($errstr, $errno);
}, E_WARNING);
/**
 * Convert PHP Notices into Exceptions.
 * - prevent inline output.
 * - can be outputted as JSON error response.
 */
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): void {
	throw new NoticeException($errstr, $errno);
}, E_NOTICE);



try{
	require __DIR__.'/includes/autoload.php';


	//$set = \RestAPI\Core\Tools\Dependency::parse_operator('p1');	exit;

	new Server();

}catch(Error | Exception $e){
	new ErrorResponse($e->getCode(), $e->getMessage(), 500, explode(PHP_EOL, $e->getTraceAsString()));
}

