<?php

namespace RestAPI\Core;

use RestAPI\Core\Tools\Gatekeeper;
use RestAPI\Core\Tools\Logfile;


class Server {


	private $namespaces			= [];

	public function __construct(){

		// load custom endpoints from config
		$this->register_routes();
		// handle request and send response
		$this->serve_request();
	}

	private function register_routes(): void {
		global $api_settings;

		if(!empty($api_settings['router.namespaces'])){
			foreach($api_settings['router.namespaces'] as $namespace => $class){
				$namespace = trim($namespace, '/');
				if(!isset($this->namespaces[$namespace])){
					$this->namespaces[$namespace] = $class;
				}
			}
		}
	}

	private function serve_request(): void {
		global $api_settings;

		$headers 	= getallheaders();
		$body 		= file_get_contents('php://input');
		$url_path	= untrailing_slash_it(strtok($_SERVER['REQUEST_URI'], '?'));
		$route	 	= explode('/rest-api/', $url_path)[1] ?? '/';
		$method		= $_SERVER['REQUEST_METHOD'] ?? 'GET';

		Logfile::info(sprintf('serve request %s %s', $method, $url_path), datetime());

		// CORS preflight
		if($method === 'OPTIONS'){
			$response = new Response([
				'name' => $api_settings['rest.name'],
				'url' => $api_settings['rest.domain'],
				'endpoint' => $url_path,
				'datetime' => datetime(),
			], 200, 'options');
			$response->send();
		}

		$request = new Request($method, $route);

		if(!$this->match_rest_route($request)){
			new ErrorResponse('rest_no_route', 'No route was found matching the URL and request method.', 404);
		}

		$request->set_headers($headers);

		// check authentication
		if(!$this->validate_access_level($request)){
			new ErrorResponse('access_denied', 'You do not have the required permissions to access this endpoint.', 401);
		}

		$request->add_raw_params($_GET);
		$request->set_body($body);

		if(!$request->validate_params()){
			new ErrorResponse(
				'invalid_parameter',
				'One or more parameters are missing or invalid.',
				400,
				$request->get_invalid_params()
			);
		}

		if(!$request->validate_dependencies()){
			new ErrorResponse(
				'invalid_parameter_dependency',
				'Please check the following inter-parameters dependencies.',
				400,
				$request->get_invalid_dependencies()
			);
		}

		$callback = $request->get_attribute('callback');

		if(!is_callable($callback)){
			$class = is_string($callback[0])? $callback[0] : get_class($callback[0]);
			$method = $callback[1];
			new ErrorResponse('callback_not_found', sprintf('The callback %s::%s() could nod be found.', $class, $method ));
		}

		call_user_func($callback, $request);
	}

	private function match_rest_route(Request $request): bool {

		$method = $request->get_method();
		$path 	= $request->get_route();

		if(trailing_slash_it($path) === '/'){
			$request->set_attributes([
				'callback' 	 => [&$this, 'generate_rest_schema'],
				'access'	 => 'public',
				'parameters' => [],
				'dependencies' => [],
			]);
			return true;
		}

		foreach($this->namespaces as $namespace => $route_controller){

			// namespace as endpoint
			if(trailing_slash_it($path) === trailing_slash_it($namespace)){
				$Controller = new $route_controller();
				$request->set_attributes([
					'callback' 	 => [&$Controller, 'generate_namespace_schema'],
					'access'	 => 'public',
					'parameters' => [],
					'dependencies' => [],
				]);
				return true;
			}

			// loop namespaces to find controller
			if(strpos(trailing_slash_it($path), trailing_slash_it($namespace)) === 0){

				// exit if controller class is missing
				if(!class_exists($route_controller)){
					new ErrorResponse('controller_missing', sprintf('The controller class %s could not be loaded', $route_controller));
				}
				// exit if the controller does not extend the route controller
				if(!is_subclass_of($route_controller, 'RestAPI\\Core\\RouteController')){
					new ErrorResponse('invalid_controller', sprintf('The controller class %s must extend the RestAPI\\Endpoints\\RouteController', $route_controller));
				}

				// load controller to get routes
				$Controller = new $route_controller();
				$routes = $Controller->get_routes();

				// loop routes to find endpoint
				foreach($routes as $route => $endpoints){

					// skip route if method is not set as endpoint
					if(empty($endpoints[$method])) continue;

					// detect matching route & method
					if(preg_match($this->get_route_regex($route, $endpoints[$method]['parameters']), $path, $matches)){

						// assign controller to request
						$request->set_attributes($endpoints[$method]);

						// assign url parameters
						foreach($matches as $key => $val){
							if(!is_int($key)){
								$request->add_raw_param($key, urldecode($val));
							}
						}
						return true;
					}
				}
			}
		}

		return false;
	}

	private function get_route_regex(string $route, array $params): string {

		if(preg_match_all("/\/:([a-z-_]+)/i", $route, $matches)){

			$total = count($matches[0]) - 1;

			foreach($matches[0] as $key => $search){
				$name 		= $matches[1][$key];
				$type		= $params[$name]['type'] ?? 'string';
				$required 	= $params[$name]['required'] ?? true;

				if(!$required && $key < $total){
					Logfile::warning(sprintf('Only the last parameter of a route can be optional. %s must be defined as mandatory field.', $name));
					$required = true;
				}

				switch($type){
					case 'integer': $reg = '[0-9]+'; break;
					case 'number':	$reg = '[0-9]+(?:\.[0-9]+)*'; break;
					case 'float': 	$reg = '[0-9]+\.[0-9]+'; break;
					case 'bool': 	$reg = '0|1'; break;
					case 'string':
					default:		$reg =  '[a-z0-9-_+]+'; break;
				}

				$reg_base 	= $required ? '/(?<%s>%s)' : '/?(?<%s>%s)?';
				$replace 	= sprintf($reg_base, $name, $reg);
				$route 		= str_replace($search, $replace, $route);
			}
		}

		return sprintf('/^%s$/i', str_replace('/', '\/', $route));
	}

	private function validate_access_level(Request $request): bool {

		$acl 		= $request->get_attribute('access');
		$bearer		= $request->get_header('authorization');

		if($acl === 'public') return true;

		if(!$bearer){
			Logfile::error('Authorization: JSON Web Token is missing');
			return false;
		}

		if(!is_string($acl)){
			if(is_callable($acl, true, $callable_name)){
				$result = call_user_func($acl);
				if(!is_bool($result)){
					Logfile::error(sprintf('the authorization callback %s response must be boolean', $callable_name));
					return false;
				}
				return $result;
			}else{
				Logfile::error('the access setup of the endpoint is invalid');
				return false;
			}
		}

		// invalid JWT - logfile entry done by gatekeeper
		if(!Gatekeeper::authorize($bearer)){
			return false;
		}

		return Gatekeeper::user_can_access($acl);
	}

	private function generate_rest_schema(): void {
		global $api_settings;

		$data = [
			'name'			=> $api_settings['rest.name'],
			'description' 	=> $api_settings['rest.description'],
			'url' 			=> trailing_slash_it($api_settings['rest.domain']).'rest-api/',
			'timezone' 		=> $api_settings['rest.timezone'],
			'namespaces' 	=> [],
			'authentication' => [
				'type' 		=> 'bearer',
				'bearer' 	=> 'JSON Web Token (JWT)'
			],
			'routes' 		=> [],
		];

		foreach($this->namespaces as $route_controller){
			$Controller = new $route_controller();
			$data['namespaces'][$Controller->get_namespace()] = [
				'name' 			=> $Controller->get_name(),
				'description' 	=> $Controller->get_description(),
				'schema'		=> sprintf('%s/rest-api/%s',$api_settings['rest.domain'], $Controller->get_namespace()),
			];
			$data['routes'] += $Controller->get_routes_schema($Controller->get_routes());
		}

		$response = new Response($data);
		$response->send();
	}

}
