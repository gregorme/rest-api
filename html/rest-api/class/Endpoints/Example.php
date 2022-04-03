<?php
/**
 * Example Controller
 *
 * @author		...
 * @since		...
 * @package		...
 * @version 	...
 */
namespace RestAPI\Endpoints;

use RestAPI\Core\Request;
use RestAPI\Core\Response;
use RestAPI\Core\RouteController;


class Example extends RouteController {

	public function __construct(){

		$this->set_name('Example');
		$this->set_description('Can be used as boilerplate for custom extensions');
		$this->set_namespace('example');

		$this->add_route('data/:id', [
			'GET' => [
				'name' => '',
				'description' => '',
				'access' => '',
				'callback' => [&$this, 'example_callback'],
				'parameters' => [
					'key' => [
						'type' => 'number', // string, integer, number, float, bool, array, object
						'in' => 'body', //query, variables, body
						'required' => true,
						'default' => 0,
						'description' => '',
						'minimum' => 0, // ... for integer, number, float
						'maximum' => 100, // ... for integer, number, float
						'enum' => [], // ... for string, integer, number, float, array
						'validation' => [
							'type' => true, // default true
							'cast' => true, // default true
							'regex' => '/^[0-9]+$/', // default null
							'callback' => function($value, $key, $request){}, // callable, default null
							'valitron' => [], // default null
							'format' => function($value, $key, $request){}, // callable, default null
						],
						'dependencies' => [

						],
					],
				],
			],
			'POST' => [],
			'DELETE' => [],
		]);

	}

	/**
	 * GET example/data/:id
	 * - get a data object by id
	 *
	 * @param Request $request
	 * @return void
	 */
	public function example_callback(Request $request){
		$response_data = [
			'success' => true,
		];



		$response = new Response($response_data);
		$response->send();
	}


}
