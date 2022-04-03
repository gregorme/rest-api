<?php

namespace RestAPI\Core\Endpoints;



use RestAPI\Core\Request;
use RestAPI\Core\Response;
use RestAPI\Core\RouteController;
use RestAPI\Core\Tools\Dependency;


class Postman extends RouteController {

	public function __construct(){

		$this->set_name('Postman API');
		$this->set_description('Postman is a powerful tool for developing and testing APIs.');
		$this->set_namespace('postman');

		$this->add_route('export', [
			'GET' => [
				'name'					=> 'Export',
				'description'			=> 'Create an API export in JSON format to be imported as Postman collection',
				'callback' 				=> [&$this, 'get_export'],
				'access'				=> 'public',
				'parameters'			=> [
					'download'			=> [
						'type'			=> 'bool',
						'in'			=> 'query',
						'required'		=> false,
						'default'		=> false,
						'description'	=> 'Produces a download instead of the JSON output',
					],
				],
			],
		]);

	}


	public function get_export(Request $request){
		global $api_settings;

		$data = [
			'info'	=> [
				'name'			=> $api_settings['rest.name'],
				'description'	=> strip_tabs($api_settings['rest.description']),
				'schema'		=> 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
			],
			'auth' => [
				'type'	=> 'bearer',
				'bearer' => [
					'key'	=> 'token',
					'value'	=> '{{JWT}}',
					'type'	=> 'string',
				],
			],
			'variable' => [
				[
					'key'	=> 'base_url',
					'value'	=> untrailing_slash_it(preg_replace("(^https?://)", "", $api_settings['rest.domain'])),
					'type'	=> 'string',
				],
				[
					'key'	=> 'JWT',
					'value'	=> 'Your JSON Web Token from Login',
					'type'	=> 'string',
				],
			],
			"item" => $this->load_namespaces(),
		];

		$response = new Response($data);
		$response->send(false);
	}

	private function load_namespaces(): array {
		global $api_settings;

		$collection = [];

		foreach($api_settings['router.namespaces'] as $route_controller){
			$Controller = new $route_controller();

			$collection[] = [
				'name'	=> $Controller->get_name()? $Controller->get_name() : $Controller->get_namespace(),
				'description' =>  $Controller->get_description(),
				'item' => $this->get_endpoints($Controller->get_routes()),
			];

			unset($Controller);
		}

		return $collection;
	}

	private function get_endpoints(array $routes): array {
		$collection = [];

		foreach($routes as $route => $endpoints){

			$path = 'rest-api/'.$route;

			foreach($endpoints as $method => $endpoint){

				$params = $this->prepare_parameters($endpoint['parameters']);

				$tmp = [
					'name'	=> $endpoint['name'],
					'request' => [
						'auth' => [
							'type' => 'noauth',
						],
						'method' => $method,
						'header' => [
							[
								'key' => 'Content-Type',
								'name'	=> 'Content-Type',
								'value' => 'application/json',
								'type' => 'text',
							],
						],
						'body' => [
							'mode' => 'raw',
							'raw' => json_encode($params['body'], JSON_PRETTY_PRINT),
						],
						'url' => [
							'raw' => 'https://{{base_url}}/'.$path,
							"protocol" => "https",
							'host' => '{{base_url}}',
							'path' => explode('/', $path),
							'query' => $params['query'],
							'variable' => $params['variable'],
						],
						'description' => $this->prepare_endpoint_description($endpoint),
					],
					'response' => [],
				];

				if($endpoint['access'] !== 'public'){
					unset($tmp['request']['auth']);
				}

				$collection[] = $tmp;
			}
		}

		return $collection;
	}

	private function prepare_parameters(array $params): array {
		$collection = [
			'query' => [],
			'variable' => [],
			'body' => [],
		];

		foreach($params as $key => $settings){

			$default = $settings['default'] ?? '';

			switch($settings['in']){
				case 'variable':
					$collection['variable'][] = [
						'key' => $key,
						'value' => is_bool($default)? (int)$default : $default,
						'type' => $settings['type'],
						'description' => $settings['description'] ?? '',
					];
					break;
				case 'query':
					$collection['query'][$key] = is_bool($default)? (int)$default : $default;
					break;
				case 'body':
				$collection['body'][$key] = $default;
					break;
			}
		}

		return $collection;
	}

	// https://www.postman.com/postman/workspace/postman-answers/documentation/6182681-2edec808-beaa-4c31-a727-879275da0ff0
	private function prepare_endpoint_description(array $endpoint): string {
		$output = $endpoint['description'] ? $endpoint['description']."  \n\n" : '';

		/*
		 * ----- Parameters ------
		 */
		if(!count($endpoint['parameters'])) return $output;

		$output .= "#### Parameter Definition\n\n| Parameter | Description |  \n| --- | --- |";

		foreach($endpoint['parameters'] as $key => $settings){

			$txt = sprintf('`%s`', $settings['type']);

			if(isset($settings['required'])){
				$txt .= '  <br>**Required**';
			}

			if(isset($settings['default'])){
				$txt .= '  <br>Default: '.value_toString($settings['default']);
			}

			if(!empty($settings['description'])){
				$txt .= '  <br>'.str_replace("\n", '  <br>', strip_tabs($settings['description']));
			}

			$details = '';

			if(isset($settings['minimum'])){
				$details .= '  <br>Minimum: '.$settings['minimum'];
			}
			if(isset($settings['maximum'])){
				$details .= '  <br>Maximum: '.$settings['maximum'];
			}
			if(isset($settings['enum'])){
				$details .= '  <br>Acceptable values are: '.implode(', ', $settings['enum']);
			}

			if($details){
				$txt .= '  <br><br>'.$details;
			}

			$output .= sprintf("  \n| %s | %s |", $key, $txt);
		}


		/*
		 * ----- Inter-Parameter Dependencies ------
		 */
		if(!count($endpoint['dependencies'])) return $output;

		$output .= "\n\n#### Inter-Parameter Dependencies\n\n";

		foreach($endpoint['dependencies'] as $dependency => $rules){
			switch($dependency){
				case 'Requires':
					foreach($rules as $field => $fields){
						$fields = Dependency::format_fields($fields);
						if(Dependency::contains_operators($field)){
							$output .= sprintf("*   If your parameters matches the rule `%s`, you must also set %s.\n", $field, $fields);
						}else{
							$output .= sprintf("*   If you specify a value for `%s`, you must also set %s.\n", $field, $fields);
						}
					}
					break;
				case 'Or':
					foreach($rules as $fields){
						$output .= sprintf("*   At least on of `%s` must be set.\n", implode('` or `', $fields));
					}
					break;
				case 'OnlyOne':
					foreach($rules as $fields){
						$output .= sprintf("*   You must include either the `%s` parameter.\n", implode('` or `', $fields));
					}
					break;
				case 'AllOrNone':
					foreach($rules as $fields){
						$output .= sprintf("*   The parameters `%s` must be used together.\n", implode('` and `', $fields));
					}
					break;
				case 'ZeroOrOne':
					foreach($rules as $fields){
						$output .= sprintf("*   The parameters `%s` are mutually exclusive, so use only one of them.\n", implode('` and `', $fields));
					}
					break;
				case 'Custom':
					foreach($rules as $attr){
						$output .= sprintf("*   %s.\n", $attr['description'] ?? 'Custom dependency without description');
					}
					break;
			}
		}


		return $output;
	}



}
