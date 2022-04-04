<?php

namespace RestAPI\Endpoints;



use RestAPI\Core\Request;
use RestAPI\Core\Response;
use RestAPI\Core\RouteController;


class User extends RouteController {

	public function __construct(){

		$this->set_name('User');
		$this->set_description('bla und blub');
		$this->set_namespace('user');

		$this->add_route('data/:id', [
			'GET' => [
				'name' => 'Get User',
				'description' => '',
				'access' => 'public',
				'callback' => [&$this, 'get_user_data'],
				'parameters' => [
					'id'				=> [
						'in'			=> 'variable',
						'required'		=> true,
						'type'			=> 'integer',
						'allow-empty'	=> true,
						'validation'	=> [

							'valitron' => [
								'required',
								['numeric'],
								['min', 0],
								['max', 100]
							],
						]
					],
				],
				// https://medium.com/isa-group/inter-parameter-dependencies-in-rest-apis-4664e901c124
				'dependencies' => [
					/*
					'Requires' => [
						'p1' => ['p2', 'p3'],
						'p2===ente' => ['p1', 'p3'],
						'size===9' => ['p1' => 'video'],
					],
					'Or' => [
						['p1', 'p2', 'p3'],
						['p1', 'p2', 'p3'],
					],
					'OnlyOne' => [
						['p1', 'p2'],
					],
					'AllOrNone' => [
						['p1', 'p2', 'p3'],
						['p1', 'p2', 'p3'],
					],
					'ZeroOrOne' => [
						['p1', 'p2', 'p3'],
					],
					'Custom' => [
						[
							'description' => 'if weare a red hat, then you must add `xy`',
							'callback' => function($parameters){return true;}
						],
					],
					*/
				],
			],
			'POST' => [
				'name'					=> 'Update User',
				'description'			=> '',
				'callback' 				=> [&$this, 'get_user_data'],
				'access'				=> '',
				'parameters'			=> [
					'id'				=> [
						'in'			=> 'variable',
						'required'		=> true,
						'type'			=> 'number',
					],
					'string'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> '',
						'type'			=> 'string',
					],
					'number'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> 0,
						'type'			=> 'number',
					],
					'float'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> 0.0,
						'type'			=> 'float',
					],
					'array'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> [],
						'type'			=> 'array',
					],
					'object'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> new \stdClass(),
						'type'			=> 'object',
					],
					'bool'				=> [
						'in'			=> 'body',
						'required'		=> false,
						'default'		=> false,
						'type'			=> 'bool',
					],
				],
				'dependencies' => [
					'Or' => [
						['string', 'number', 'float'],
					],
				],
			],
			'DELETE' => [
				'name'					=> 'Delete User',
				'description'			=> '',
				'callback' 				=> [&$this, 'get_user_data'],
				'access'				=> 'admin',
				'parameters'			=> [
					'id'				=> [
						'in'			=> 'variable',
						'required'		=> true,
						'type'			=> 'number',
					],
				],
			],
		]);

	}


	public function get_user_data(Request $request){

		$response = new Response(['success' => true]);

		$response->send();
	}


}
