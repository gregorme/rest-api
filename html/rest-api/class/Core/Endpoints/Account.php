<?php
/**
 * Account API
 * Handles all account specific requests like login, password recovery etc.
 *
 * @author 	Gregor Müller-Elmau @ schalk&friends
 * @since	02/2022
 */
namespace RestAPI\Core\Endpoints;

use RestAPI\Core\ErrorResponse;
use RestAPI\Core\Request;
use RestAPI\Core\Response;
use RestAPI\Core\RouteController;
use RestAPI\Core\Tools\Gatekeeper;
use RestAPI\Core\Tools\Logfile;
use RestAPI\Core\Tools\Mailer;

class Account extends RouteController{

	public function __construct(){

		$this->set_name('Account API');
		$this->set_description('The account API provides authentication, password recovery and data endpoints.
		An account represents a person or organization with can log on to the application. 
		Therefore, the Account API is used by all users who want to access the application.');
		$this->set_namespace('account');


		$this->add_route('login', [
			'POST' => [
				'name' => 'Login',
				'description' => 'Username and password authentication',
				'access' => 'public',
				'callback' => [&$this, 'process_authentication'],
				'parameters' => [
					'username'			=> [
						'in'			=> 'body',
						'required'		=> true,
						'type'			=> 'string',
					],
					'password'			=> [
						'in'			=> 'body',
						'required'		=> true,
						'type'			=> 'string',
					],
				],
			],
		]);

		// TODO add logout

		$this->add_route('password/recovery', [
			'POST' => [
				'name' => 'Password Recovery',
				'description' => 'Create a password recovery token for the given username and submit the recovery email. 
				There will be no error or exception if the username is unknown.',
				'access' => 'public',
				'callback' => [&$this, 'process_password_recovery'],
				'parameters' => [
					'username'			=> [
						'in'			=> 'body',
						'required'		=> true,
						'type'			=> 'string',
					],
				],
			],
		]);

		$this->add_route('password/requirements', [
			'GET' => [
				'name' => 'Password Requirements',
				'description' => 'The password requirements should be displayed to users when they are required to set or change their passwords.',
				'access' => 'public',
				'callback' => [&$this, 'get_password_requirements'],
			],
		]);

		$this->add_route('password/set', [
			'POST' => [
				'name' => 'Set Password',
				'description' => 'Save the new password for the current account. 
				Requires a valid recovery token and must meet the password requirements.',
				'access' => 'public',
				'callback' => [&$this, 'save_new_password'],
				'parameters' => [
					'token'				=> [
						'in'			=> 'body',
						'required'		=> true,
						'type'			=> 'string',
					],
					'password'			=> [
						'in'			=> 'body',
						'required'		=> true,
						'type'			=> 'string',
					],
				],
			],
		]);
	}


	/**
	 * POST account/login
	 * - process the customer login and create a new session.
	 * @param Request $request
	 * @return void
	 */
	public function process_authentication(Request $request): void {
		global $current_user;

		$username = $request->get_param('username');
		$password = $request->get_param('password');

		if(!Gatekeeper::authenticate($username, $password)){
			Logfile::error(sprintf('Login for %s failed', $username));
			new ErrorResponse('invalid_login', 'The username or password you entered is incorrect.');
		}

		$data = [
			'user' => $current_user,
			'jwt'  => Gatekeeper::create_jwt(),
		];

		$response = new Response($data);
		$response->send();
	}

	/**
	 * GET /account/password/recovery
	 * - create a password recovery token and submit the link by email.
	 * @param Request $request
	 * @return void
	 */
	public function process_password_recovery(Request $request): void {
		global $api_db, $api_settings;

		$username = $request->get_param('username');

		if($username){

			$sql = "SELECT `ID`, `first_name`, `last_name`, `email` 
					FROM {$api_db->accounts} WHERE `email` = %s AND `status` = 'active'";
			$account = $api_db->get_row($api_db->prepare($sql, $username), ARRAY_A);

			if(!empty($account['ID'])){

				$token = Gatekeeper::create_user_token($account['ID'], 'password-recovery', '+1 day');

				$account['token'] = $token;
				$account['link'] = $api_settings['rest.domain'].str_replace(
					':token',
					$token,
					$api_settings['password.reset.route']
				);
				new Mailer(
					'password-recovery',
					$account,
					$account['first_name'].' '.$account['last_name'],
					$account['email']
				);
			}
		}

		// send success in all cases - so nobody can spoof the known mail addresses of the system.
		$response = new Response(['success' => true]);
		$response->send();
	}

	/**
	 * GET account/password/requirements
	 * - retrieve the list of password requirements used for validation.
	 * @param Request $request
	 * @return void
	 */
	public function get_password_requirements(Request $request): void {
		global $api_settings;

		$singular = 'character';
		$plural = 'characters';

		$requirements = [
			[
				'key' => 'length',
				'value' => $api_settings['password.length'],
				'text' => sprintf(
					'Minimum %s %s',
					$api_settings['password.length'],
					singular_plural($api_settings['password.length'], $singular, $plural)
				),
			],
			[
				'key' => 'uppercase',
				'value' => $api_settings['password.uppercase'],
				'text' => sprintf(
					'At least %s uppercase %s',
					$api_settings['password.uppercase'],
					singular_plural($api_settings['password.uppercase'], $singular, $plural)
				),
			],
			[
				'key' => 'lowercase',
				'value' => $api_settings['password.lowercase'],
				'text' => sprintf(
					'At least %s lowercase %s',
					$api_settings['password.lowercase'],
					singular_plural($api_settings['password.lowercase'], $singular, $plural)
				),
			],
			[
				'key' => 'numbers',
				'value' => $api_settings['password.numbers'],
				'text' => sprintf(
					'At least %s %s',
					$api_settings['password.numbers'],
					singular_plural($api_settings['password.numbers'], 'digit', 'digits')

				),
			],
			[
				'key' => 'special',
				'value' => $api_settings['password.special'],
				'text' => sprintf(
					'At least %s special %s of %s',
					$api_settings['password.special'],
					singular_plural($api_settings['password.uppercase'], $singular, $plural),
					$api_settings['password.special.chars'],
				),
			],
			[
				'key' => 'reuse',
				'value' => $api_settings['password.reuse'],
				'text' => $api_settings['password.reuse']
					? 'Passwords that have already been used can be used again'
					: 'Passwords that have already been used cannot be used again',
			],
		];

		$response = new Response(['requirements' => $requirements]);
		$response->send();
	}

	/**
	 * POST /account/password/set
	 * - save the new password for an account.
	 * - requires a valid recovery token and the new password must meet the password requirements.
	 * @param Request $request
	 * @return void
	 */
	public function save_new_password(Request $request): void {
		global $api_db;

		$token		= $request->get_param('token');
		$password 	= $request->get_param('password');
		$account_id	= Gatekeeper::resolve_user_token($token, 'password-recovery');

		// exit if token is invalid
		if(!$account_id)
			new ErrorResponse('invalid_token', 'The provided password recovery token is no longer valid or unknown.', 400);

		$sql = "SELECT `ID`, `first_name`, `last_name`, `email` 
					FROM {$api_db->accounts} WHERE `ID` = %s AND `status` = 'active'";
		$account = $api_db->get_row($api_db->prepare($sql, $account_id), ARRAY_A);

		// exit if the user account is unknown or inactive
		if(!$account)
			new ErrorResponse('invalid_token_user', 'The provided token does not belong to any known user account.', 400);

		$status = Gatekeeper::password_validation($password, $account_id);
		// exit if the new password is invalid
		if(!$status['valid']){
			new ErrorResponse(
				'invalid_password',
				'Please check the password requirements',
				400,
				['password_validation' => $status]
			);
		}

		$pwd = Gatekeeper::salted_password($password);

		$api_db->update($api_db->accounts, ['password' => $pwd], ['ID' => $account_id]);
		$api_db->delete($api_db->tokens, ['token' => $token, 'account_id' => $account_id]);
		$api_db->insert($api_db->passwords, [
			'account_id'	=> $account_id,
			'password'		=> $pwd,
			'created'		=> datetime(),
		]);

		new Mailer(
			'password-changed',
			$account,
			$account['first_name'].' '.$account['last_name'],
			$account['email']
		);


		$response = new Response(['success' => true]);
		$response->send();
	}

}
