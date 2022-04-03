<?php

namespace RestAPI\Core\Tools;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RestAPI\Core\ErrorResponse;

class Gatekeeper {


	/**
	 * Main user and account authentication for the login routine.
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public static function authenticate(string $username, string $password): bool {
		global $api_settings, $current_user, $api_db;

		$current_user = null;

		$admin_username = $api_settings['admin.username'];
		$admin_password = $api_settings['admin.password'];

		// Default admin authentication
		if($admin_username && $username === $admin_username && $admin_password && $password === $admin_password){
			$current_user = [
				'id'	=> 0,
				'name'	=> 'Administrator',
				'role'	=> 'admin',
				'ref'	=> self::login_history(0),
				'token' => self::create_user_token(0, 'downloads', $api_settings['jwt.lifetime']),
			];
			return true;
		}

		$sql 	 = "SELECT `ID`, `first_name`, `last_name`, `email`, `role`  
					FROM {$api_db->accounts} WHERE `email` = %s AND `password` = %s AND  `status` = 'active'";
		$account = $api_db->get_row($api_db->prepare($sql, [$username, self::salted_password($password)]), ARRAY_A);

		if(!empty($account['ID'])){
			$current_user = [
				'id'	=> $account['ID'],
				'name'	=> $account['first_name'].' '.$account['last_name'],
				'role'	=> $account['role'],
				'ref'	=> self::login_history($account['ID']),
				'token' => self::create_user_token($account['ID'], 'downloads', $api_settings['jwt.lifetime']),
			];
			return true;
		}

		return false;
	}

	/**
	 * Main user and account authorization for all regular API requests.
	 * @param string $bearer
	 * @return bool
	 */
	public static function authorize(string $bearer): bool {
		global $api_settings, $current_user;

		$current_user 	= null;
		$jwt			= str_replace('Bearer ', '', $bearer);
		$secret 		= $api_settings['jwt.secret'] ?? false;

		if(!$secret){
			Logfile::error('Failed to decode JSON Web Token - token secret is missing in config.');
			return false;
		}

		try{
			$decoded = JWT::decode($jwt, new Key($secret, 'HS256'));

			// TODO check ref ID in database, maybe logged out
			// TODO check if IP has changed
			// TODO update database, last usage datetime and IP, maybe Browser

			$current_user = (array)$decoded->user;

		}catch(\Firebase\JWT\BeforeValidException $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_before_valid', $e->getMessage());
		}catch(\Firebase\JWT\ExpiredException $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_expired', $e->getMessage());
		}catch(\Firebase\JWT\SignatureInvalidException $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_invalid', $e->getMessage());
		}catch(\UnexpectedValueException $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_unreadable', $e->getMessage());
		}catch(\DomainException $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_malformed', $e->getMessage());
		}catch(\Exception $e){
			Logfile::error('invalid JWT - '.$e->getMessage());
			new ErrorResponse('jwt_unhandled_exception', $e->getMessage());
		}

		return true;
	}

	/**
	 * Check if the current user can access a specific access level, role or capability.
	 * @param string $access_level
	 * @return bool
	 */
	public static function user_can_access(string $access_level): bool {
		global $api_settings, $current_user;

		// No user no access
		if(!$current_user) return false;

		// Admin role can access everything - no further validation required
		if($current_user['role'] === 'admin') return true;

		$roles = $api_settings['acl.setup'] ?? [];

		// undefined role
		if(!isset($roles[$current_user['role']])){
			Logfile::error(sprintf('the users role %s is undefined', $current_user['role']));
			return false;
		}

		// ACL matches the users role
		if($current_user['role'] === $access_level) return true;

		// This role can access all caps
		if($roles[$current_user['role']] === '*') return true;

		// invalid role/cap setup
		if(!is_array($roles[$current_user['role']])){
			Logfile::error(sprintf('the role setup for %s is invalid', $current_user['role']));
			return false;
		}

		// Is the capability included to the role
 		return in_array($access_level, $roles[$current_user['role']], true);
	}

	/**
	 * Create a new JSON web token for the current user.
	 * @return string|null
	 */
	public static function create_jwt(): ?string {
		global $api_settings, $current_user;

		if(!$current_user){
			Logfile::error('Failed to create JSON Web Token - current user is null.');
			return null;
		}

		$secret = $api_settings['jwt.secret'] ?? false;

		if(!$secret){
			Logfile::error('Failed to create JSON Web Token - token secret is missing in config.');
			return null;
		}

		$payload = [
			'iat' 	=> datetime('now', 'U'),
			'exp'	=> datetime($api_settings['jwt.lifetime'], 'U'), // TODO add to config
			'iss' 	=> $api_settings['rest.domain'],
			'user'  => $current_user,
		];

		if($api_settings['jwt.lifetime']){

		}

		return JWT::encode($payload, $secret, 'HS256');
	}

	/**
	 * Create a new user token for a specific task and store it in the database.
	 * A user token is unique for each account, but not for the task scope.
	 * @param int $account_id
	 * @param string $task			Task keyword fo the token scope.
	 * @param string $lifetime		Time string like +1 day
	 * @param int $loop				Execution loops of the method id the token was not unique.
	 * @return string
	 */
	public static function create_user_token(int $account_id, string $task, string $lifetime, int $loop = 0): string {
		global $api_db;

		self::token_sweeper();

		$token = self::generate_uuid();

		$done = $api_db->insert($api_db->tokens, [
			'account_id'	=> $account_id,
			'token'			=> $token,
			'task'			=> $task,
			'created'		=> datetime(),
			'expiration'	=> datetime('now '.$lifetime),
		]);

		if(!$done){
			if($loop < 5){
				Logfile::warning('User token is not unique, retry. '.$api_db->last_error);
				return self::create_user_token($account_id, $task, $lifetime, ++$loop);
			}else{
				Logfile::error('Failed to create unique user token.');
				new ErrorResponse('user_token_error', 'Failed to create unique user token');
			}
		}

		Logfile::success('New user token created and saved');

		return $token;
	}

	/**
	 * Check if the given user token exists for the required task and is still valid.
	 * @param string $token
	 * @param string $task
	 * @return int|null
	 */
	public static function resolve_user_token(string $token, string $task): ?int {
		global $api_db;

		self::token_sweeper();

		$sql = "SELECT `account_id` FROM {$api_db->tokens} WHERE `token` = %s AND `task` = %s";
		$user_id = $api_db->get_var($api_db->prepare($sql, [$token, $task]));

		return $user_id ?: null;
	}

	/**
	 * Validate a password against the API requirements.
	 * @param string $password 	The password to validate.
	 * @param int $account_id	The affected account id.
	 * @return array
	 */
	public static function password_validation(string $password, int $account_id): array {
		global $api_db, $api_settings;

		$valid 		= true;
		$hints		= [];
		$marker		= [];
		$length		= mb_strlen($password); // use multi-byte
		$singular 	= 'character';
		$plural 	= 'characters';

		// check the password length
		if($length < $api_settings['password.length']){
			$valid 		= false;
			$marker[]	= 'length';
			$hints[] 	= sprintf(
				'The password must be at least %s %s long.',
				$api_settings['password.length'],
				singular_plural($api_settings['password.length'], $singular, $plural)
			);
		}

		/*
		 * A password must consist out of letters, digits and special characters.
		 */
		$chars 				= str_replace(' ', '\\', $api_settings['password.special.chars']);
		$count_total		= $length;
		$count_uppercase 	= mb_strlen(preg_replace("/[^A-Z]+/", "", $password));
		$count_lowercase 	= mb_strlen(preg_replace("/[^a-z]+/", "", $password));
		$count_digits 		= mb_strlen(preg_replace("/[^0-9]+/", "", $password));
		$count_special	 	= mb_strlen(preg_replace("/[^{$chars}]+/", "", $password));

		// enough uppercase characters?
		if($count_uppercase < $api_settings['password.uppercase']){
			$valid 		= false;
			$marker[]	= 'uppercase';
			$hints[] 	= sprintf(
				'The password must contain at least %s uppercase %s.',
				$api_settings['password.uppercase'],
				singular_plural($api_settings['password.uppercase'], $singular, $plural)
			);
		}

		// enough lowercase characters?
		if($count_lowercase < $api_settings['password.lowercase']){
			$valid 		= false;
			$marker[]	= 'lowercase';
			$hints[] 	= sprintf(
				'The password must contain at least %s lowercase %s.',
				$api_settings['password.lowercase'],
				singular_plural($api_settings['password.lowercase'], $singular, $plural)
			);
		}

		// enough digits?
		if($count_digits < $api_settings['password.numbers']){
			$valid 		= false;
			$marker[]	= 'numbers';
			$hints[] 	= sprintf(
				'The password must contain at least %s %s.',
				$api_settings['password.numbers'],
				singular_plural($api_settings['password.numbers'], 'digit', 'digits')
			);
		}

		// enough special characters?
		if($count_special < $api_settings['password.special']){
			$valid 		= false;
			$marker[]	= 'special';
			$hints[] 	= sprintf(
				'The password must contain at least %s special %s.',
				$api_settings['password.special'],
				singular_plural($api_settings['password.special'], $singular, $plural)
			);
		}

		/*
		 * A set password must not be the same as any of the previous passwords from the history.
		 */
		$sql = "SELECT COUNT(1) FROM {$api_db->passwords} WHERE `account_id` = %s AND `password` = %s";
		$reused = (bool)$api_db->get_var($api_db->prepare($sql, [$account_id, self::salted_password($password)]));
		if(!$api_settings['password.reuse'] && $reused){
			$valid 		= false;
			$marker[]	= 'reuse';
			$hints[] 	= 'Your password must not be the same as any of your previous passwords.';
		}

		return [
			'valid'			=> $valid,
			'hints'			=> $hints,
			'marker'		=> $marker,
			'components'	=> [
				'total_length'			=> $count_total,
				'uppercase_letters'		=> $count_uppercase,
				'lowercase_letters'		=> $count_lowercase,
				'digits'				=> $count_digits,
				'special_chars'			=> $count_special,
				'reused'				=> $reused,
			],
		];
	}

	/**
	 * Salt and hash the password for database usage.
	 * @param string $password
	 * @return string
	 */
	public static function salted_password(string $password): string {
		global $api_settings;

		return sha1($api_settings['password.salt'].$password);
	}


	/**
	 * Retrieve the IP address from the current request metadata.
	 * @return string
	 */
	private static function get_user_ip(): string {
		$ip_list = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$ips = explode(',', $ip_list);
		return array_pop($ips);
	}

	/**
	 * Retrieve the browser name from the current request metadata.
	 * @return string
	 */
	private static function get_browser_name(): string {

		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

		if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
		elseif (strpos($user_agent, 'Edge')) return 'Edge';
		elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
		elseif (strpos($user_agent, 'Safari')) return 'Safari';
		elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
		elseif (strpos($user_agent, 'Postman') !== false) return 'Postman';
		elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';

		return 'Other';
	}

	/**
	 * Create a Universally Unique Identifier.
	 * Can be used as token in emails, links etc. to identify the associated account.
	 * @return string
	 */
	private static function generate_uuid(): string {

		if(function_exists('com_create_guid')) return com_create_guid();

		// fallback
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		return substr($charid, 0, 8).$hyphen
			.substr($charid, 8, 4).$hyphen
			.substr($charid, 12, 4).$hyphen
			.substr($charid, 16, 4).$hyphen
			.substr($charid, 20, 12);

	}

	/**
	 * Delete all expired user tokens from the database.
	 * - save storage and reduce the risk of duplicates.
	 * @return void
	 */
	private static function token_sweeper(): void {
		global $api_db;

		$sql = "DELETE FROM {$api_db->tokens} WHERE `expiration` <= %s";
		$api_db->query($api_db->prepare($sql, datetime()));
	}

	/**
	 * Extend the user login history and track the current session.
	 * @param int $account_id
	 * @return int
	 */
	private static function login_history(int $account_id): int {
		global $api_db;

		$api_db->insert($api_db->sessions, [
			'account_id'	=> $account_id,
			'active'		=> 1,
			'created'		=> datetime(),
			'last_seen'		=> datetime(),
			'ip_address'	=> self::get_user_ip(),
			'browser'		=> self::get_browser_name(),
			]);

		return $api_db->insert_id;
	}




}
