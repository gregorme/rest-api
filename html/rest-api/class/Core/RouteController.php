<?php

namespace RestAPI\Core;

use RestAPI\Core\Tools\Logfile;
use RestAPI\Core\Tools\Validation;

class RouteController {

	private $name = "";
	private $description = "";
	private $namespace 	= '';
	private $routes 	= [];



	public function get_name(): string {
		return $this->name;
	}


	public function get_description(): string {
		return strip_tabs($this->description);
	}


	public function get_namespace(){
		return $this->namespace;
	}


	public function get_routes(){
		return $this->routes;
	}


	public function generate_namespace_schema(): void {

		$data = [
			'name'		=> $this->name,
			'description' => $this->description,
			'namespace'	=> $this->namespace,
			'routes'	=> $this->get_routes_schema($this->routes),
		];

		$response = new Response($data);
		$response->send();
	}


	public function get_routes_schema(array $routes): array {
		$collection = [];

		foreach($routes as $route => $endpoints){
			$collection[$route] = [
				'namespace'	=> $this->namespace,
				'methods'	=> array_keys($endpoints),
				'endpoints' => $this->get_endpoints_schema($endpoints),
			];
		}

		return $collection;
	}


	public function get_endpoints_schema(array $endpoints): array {
		$collection = [];

		$hidden = ['validation', 'format'];

		foreach($endpoints as $method => $endpoint){

			$dependencies = [];

			foreach($endpoint['dependencies'] as $dependency => $rules){
				if($dependency === 'Custom'){
					$dependencies[$dependency] = array_map(function($settings){
						return str_replace('`', '', $settings['description']) ?? 'Custom dependency without description';
					}, $rules);
				}else{
					$dependencies[$dependency] = array_map(function($fields){
						return implode(', ', $fields);
					}, $rules);
				}
			}

			$tmp = [
				'name'		=> $endpoint['name'],
				'description' => $endpoint['description'],
				'method'	=> $method,
				'auth' => $endpoint['access'] === 'public' ? 'none' : 'JWT',
				'access' => $endpoint['access'],
				'parameters' => array_map(function($params) use ($hidden){
					foreach($hidden as $key){
						unset($params[$key]);
					}
					return $params;
				}, $endpoint['parameters']),
				'dependencies' => $dependencies,
			];

			$collection[] = $tmp;
		}

		return $collection;
	}


	protected function set_name(string $name): void {
		$this->name = trim($name);
	}


	protected function set_description(string $description): void {
		$this->description = strip_tabs(trim($description));
	}


	protected function set_namespace(string $namespace): void {
		$this->namespace = trim($namespace, '/');
	}


	protected function add_route(string $route, array $endpoints = []): void {

		if(!$this->namespace){
			Logfile::error(sprintf('Routes must be namespaced: %s', $route));
			return;
		}

		$clean_route 		= trim($route, '/');

		if(!$clean_route){
			Logfile::error(sprintf('Invalid route %s for namespace %s', $clean_route, $this->namespace));
			return;
		}

		$route_path 		= trailing_slash_it($this->namespace).$clean_route;
		$clean_endpoints 	= [];

		foreach($endpoints as $method => $endpoint){

			$area = $method.' '.$route_path;

			if(is_numeric($method)){
				Logfile::error('endpoints must be defined as an array of arrays', $area);
				continue;
			}

			if(empty($endpoint['callback'])){
				Logfile::error('no callback defined', $area);
				continue;
			}else if(!is_callable($endpoint['callback'])){
				Logfile::error('endpoint not callable', $area);
				continue;
			}

			$setup = [
				'name' => $endpoint['name'] ?? $route,
				'description' => strip_tabs($endpoint['description'] ?? ''),
				'access' => !empty($endpoint['access'])? $endpoint['access'] : 'public',
				'callback' => $endpoint['callback'],
				'parameters' => [],
				'dependencies' => [],
			];

			if(!empty($endpoint['parameters'])){
				foreach($endpoint['parameters'] as $key => $props){

					$clean_key = sanitize_parameter_key($key);

					if($clean_key !== $key){
						Logfile::warning(sprintf('The name of the parameter \'%s\' is not well formatted and has been renamed to %s', $key, $clean_key));
						$key = $clean_key;
					}

					$props['type'] = (!empty($props['type']) && Validation::type_exists($props['type']))? $props['type'] : 'string';

					$tmp = [
						'type' 		=> $props['type'],
						'in'		=> 'body',
						'required' 	=> isset($props['required'])? (bool)$props['required'] : false,
						'default' 	=> null,
						'allow-empty' => false,
					];

					if(isset($props['in'])){
						$tmp['in'] = in_array($props['in'], ['query', 'variable', 'body'])? $props['in'] : 'body';
					}

					if(isset($props['default'])){
						if($props['required'] === false){
							if(Validation::type_validation($props['default'], $tmp['type'])){
								$tmp['default'] = $props['default'];
							}else{
								Logfile::warning(sprintf('Invalid default value %s for parameter %s', var_export($props['default']), $key), $area);
							}
						}else{
							Logfile::warning(sprintf('Optional parameters cannot have a default value. Default for %s ignored', $key), $area);
						}
					}

					if($props['type'] === 'bool'){
						$tmp['allow-empty'] = true;
					}else if(isset($props['allow-empty'])){
						$tmp['allow-empty'] = (bool)$props['allow-empty'];
					}

					if(!empty($props['description'])){
						$tmp['description'] = trim($props['description']);
					}

					if(in_array($tmp['type'], ['integer', 'number', 'float'])){

						if(isset($props['minimum'])){
							if(Validation::type_validation($props['minimum'], $tmp['type'])){
								$tmp['minimum'] = $props['minimum'];
							}else{
								Logfile::warning(sprintf('invalid minimum value %s for parameter %s', var_export($props['minimum']), $key), $area);
							}
						}
						if(isset($props['maximum'])){
							if(Validation::type_validation($props['maximum'], $tmp['type'])){
								$tmp['maximum'] = $props['maximum'];
							}else{
								Logfile::warning(sprintf('invalid maximum value %s for parameter %s', var_export($props['minimum']), $key), $area);
							}
						}
					}

					if(in_array($tmp['type'], ['string', 'integer', 'number', 'float'])){
						if(isset($props['enum'])){
							$tmp['enum'] = $props['enum'];
						}
					}

					$tmp['validation'] = [];

					// set default validations in default order if they are not listed
					if(!isset($props['validation']['trim'])){
						$tmp['validation']['trim'] = true;
					}
					if(!isset($props['validation']['type'])){
						$tmp['validation']['type'] = true;
					}
					if(!isset($props['validation']['cast'])){
						$tmp['validation']['cast'] = true;
					}

					if(isset($props['validation']) && is_array($props['validation'])){
						foreach($props['validation'] as $val_type => $val_param){
							switch($val_type){
								case 'trim':
								case 'type':
								case 'cast':
									if($val_param){
										$tmp['validation'][$val_type] = true;
									}
									break;
								case 'regex':
									$val_param = trim($val_param);
									if($val_param){
										$tmp['validation']['regex'] = $val_param;
									}else{
										Logfile::warning('empty validation regex', $area);
									}
									break;
								case 'callback':
									if(is_callable($val_param, true, $callable_name)){
										$tmp['validation']['callback'] = $val_param;
									}else{
										Logfile::warning('invalid validation callback '.$callable_name, $area);
									}
									break;
								case 'valitron':
									if(is_array($val_param)){
										$tmp['validation']['valitron'] = $val_param;
									}else{
										$tmp['validation']['valitron'] = [$val_param];
									}
									break;
								case 'format':
									if(is_callable($val_param, true, $callable_name)){
										$tmp['validation']['format'] = $val_param;
									}else{
										Logfile::warning('invalid format callback '.$callable_name, $area);
									}
									break;
								default:
									Logfile::warning(sprintf('unknown validation type %s', $val_type), $area);
									break;
							}
						}
					}

					$setup['parameters'][$key] = $tmp;
				}
			}

			if(!empty($endpoint['dependencies'])){
				$setup['dependencies'] = $endpoint['dependencies'];
			}

			$clean_endpoints[$method] = $setup;
		}

		$this->routes[$route_path] = $clean_endpoints;
	}

}
