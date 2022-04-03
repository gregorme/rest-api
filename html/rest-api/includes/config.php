<?php
/**
 * REST API CONFIGURATION
 */

return [

	/*******************
	| API META SETTINGS |
	 *******************/

	/*
	 * The name of the REST API. Used for Postman, API schema etc.
	 */
	'rest.name'	=> 'Rest API',

	/*
	 * A short description of the REST API. Used for Postman, API schema etc.
	 */
	'rest.description' => '',

	/*
	 * The domain of the Rest API including the protocol.
	 */
	'rest.domain' => 'https://rest-api.ddev.site',

	/*
	 * The timezone of the web server, used for time calculations in datetime()
	 * Must be a valid PHP timezone string.
	 * @see https://www.php.net/manual/en/timezones.php
	 */
	'rest.timezone' => 'Europe/Berlin',


	/***************
	| ADMIN ACCOUNT |
	 ***************/

	'admin.username' => 'Gregor',

	'admin.password' => 'Test',


	/*******************
	| PASSWORD SECURITY |
	 *******************/

	// https://api.wordpress.org/secret-key/1.1/salt/
	'password.salt' => 'T*<U9t9#?L29;T;CuG%j4ox=+0reCjd`@,c*x!cMaY[!<^-aQq?NqJz^u)FH-ce|',

	/*
	 * The app route for the password reset link. Please use :token as placeholder for the recovery token.
	 */
	'password.reset.route' => '/#/password/recovery/:token',

	/*
	 * The minimum character length, ranging from a minimum of 6 characters to a maximum of 64 characters.
	 * The default length is 8 characters.
	 */
	'password.length' => 8,

	/*
	 * Should the user be able to reuse his old passwords during password recovery/change.
	 * true 	= user can reuse his old passwords
	 * false 	= user must select a new password on each password change
	 */
	'password.reuse' => false,

	// true false or minimum occasions
	/*
	 * Enable at least three of the following categories.
	 * The default password requirement is one number, one uppercase character, and one lowercase character.
	 * true = at least 1 occurrence
	 * number = at least n occurrences
	 */
	'password.uppercase' => true, // Uppercase English letters
	'password.lowercase' => 2, // Lowercase English letters
	'password.numbers' => true, // Numbers 0 through 9 inclusive
	'password.special' => true, // Special chars, no umlauts or spaces
	'password.special.chars' => '! @ # $ % ^ & * < > ?', // list of chars, spaces are just for readability


	/*****************
	| JSON WEB TOKENS |
	 *****************/

	/*
	 * A secret key that should be at least 256 bits long is used to sign the JWT token.
	 * For safety reasons we recommend 512 bit or more. You can use the following page to create your own
	 * @see https://allkeysgenerator.com -> 512-bit Encryption Key
	 */
	'jwt.secret' => 'VkYp3s6v9y$B&E)H+MbQeThWmZq4t7w!z%C*F-JaNcRfUjXn2r5u8x/A?D(G+KbP',

	/*
	 * In principle, a token can be used indefinitely. For security reasons it should be renewed
	 * from time to time. By specifying a lifetime (expiration time), a token can no longer be used
	 * after the time has been exceeded. You must provide a valid format which can be parsed by DateTime().
	 * @see https://www.php.net/manual/en/datetime.formats.php
	 * Example: +1 day, +4 hours, YYYY-MM-DD HH:ii:ss
	 */
	'jwt.lifetime' => '+1 day',


	/****************
	| EMAIL SETTINGS |
	 ****************/

	/*
	 * Please do not forget to extend the SPF-Record of the sender mail domain.
	 * Otherwise the SPF spam protection of the customers mail client will mark all mails as spam.
	 * @see https://www.spf-record.de/
	 *
	 * Example for schalk&friends Mail-Server
	 * v=spf1 mx include:schalk-it.de -all
	 */

	'email.sender.name' => 'API', // Senders name
	'email.sender.mail' => 'api@api.com', // Senders email address
	'email.replyTo' => 'api@api.com', // Mail address for customer responses and auto-responses
	'email.returnPath' => 'api@api.com', // Mail address for undeliverable emails and submission errors


	/************************
	| HEADER & CORS SETTINGS |
	 ************************/

	/*
	 * The Access-Control-Allow-Origin response header indicates whether the response
	 * can be shared with requesting code from the given origin.
	 * <origin>
	 * - Specifies an origin. Only a single origin can be specified.
	 * - value: 'https://my-website.com'
	 * Wildcard
	 * - allow any origin to access the API
	 * - value: '*'
	 */
	'headers.origin' => '*',

	/*
	 * The Access-Control-Allow-Methods response header specifies one or more methods
	 * allowed when accessing a resource in response to a preflight request.
	 */
	'headers.methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',


	/**************************
	| DEBUGGING & DEVELOPMENT |
	 **************************/

	/*
	 * Used to trigger the "debug" mode throughout the Rest API.
	 * Can be accessed as constant REST_API_DEBUG
	 * Will allow the database to display inline messages (warnings, errors) which will break the JSON format!
	 */
	'debug.enabled' => false,

	/*
	 * Define the log levels to log. Available levels
	 * info, notice, success, warning, error, debug
	 */
	'debug.loglevel' => 'info, notice, success, warning, error, debug',

	/*
	 * If enabled the logfile of the current request will be added to the response object.
	 * Should be disabled in production environment.
	 * Can be disabled on each response in $response->send($append_logfile = false)
	 */
	'debug.response.logfile' => true,


	/*****************************
	| USER ACCESS CONTROL LEVELS  |
	 *****************************/

	/*
	 * The admin role can access everything by default. No configuration required.
	 * Provide all other roles as keys and list their capabilities.
	 * You can use roles or capabilities as access value for your endpoints.
	 * Examples:
	 * - 'role' => '*'						Role can access endpoints for this role and all capabilities.
	 * - 'role' => ['read', 'write']		Role can access endpoints for this role and the listed capabilities.
	 */
	'acl.setup' => [
		//'role' => ['cap'],
	],


	/***********************
	| ROUTER CONFIGURATIONS |
	 ***********************/

	/*
	 * Each namespace is managed by its own controller. To optimize API performance a controller
	 * is only loaded when the namespace is accessed.
	 * A custom controller must extend the basic class RestAPI\Endpoints\RouteController.
	 * Note that each namespace must be unique and an existing namespace cannot be overwritten.
	 * Example: 'my_namespace' => 'RestAPI\\Endpoints\\MyNamespaceController'
	 */
	'router.namespaces' => [
		// API default routes
		'postman' 	=> 'RestAPI\\Core\\Endpoints\\Postman',
		//'account'	=> 'RestAPI\\Core\\Endpoints\\Account',
		// Custom routes
		'user'		=> 'RestAPI\\Endpoints\\User',
	],


	/*********************
	| DATABASE CONNECTION |
	 *********************/

	'database.host' => $_ENV['DB_HOST'], // The database Server / Host
	'database.name' => $_ENV['DB_NAME'], // The database name
	'database.user' => $_ENV['DB_USER'], // The database username
	'database.password' => $_ENV['DB_PASSWORD'], // The database password
	'database.prefix' => 'rest_api_', // The database table prefix


];
