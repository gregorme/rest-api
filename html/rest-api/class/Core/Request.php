<?php

namespace RestAPI\Core;

use RestAPI\Core\Tools\Dependency;
use RestAPI\Core\Tools\Logfile;
use RestAPI\Core\Tools\Validation;

class Request {

	private $method = '';
	private $route = '';
	private $headers = [];
	private $body = '';
	private $params = [];
	private $attributes = [];
	private $raw_params = [];
	private $invalid_params = [];
	private $invalid_dependencies = [];





	public function __construct(string $method, string $route){

		$this->set_method($method);
		$this->set_route($route);


	}

	public function get_method(): string {
		return $this->method;
	}

	public function set_method(string $method): void {
		$this->method = strtoupper($method);
	}

	public function get_headers(): array {
		return $this->headers;
	}

	public function get_header(string $key): ?string {
		return $this->headers[strtolower($key)] ?? null;
	}

	public function set_headers(array $headers): void {
		foreach($headers as $key => $val){
			$this->set_header($key, $val);
		}
	}

	public function set_header(string $key, string $value): void {
		$this->headers[strtolower($key)] = $value;
	}

	public function remove_header(string $key): void {
		unset($this->headers[strtolower($key)]);
	}

	public function get_params(): array {
		return $this->params;
	}

	public function get_param(string $key, $default = null){
		return $this->params[strtolower($key)] ?? $default;
	}

	public function has_param(string $key): bool {
		return array_key_exists(strtolower($key), $this->params);
	}

	public function add_raw_params(array $params): void {
		foreach($params as $key => $val){
			$this->add_raw_param($key, $val);
		}
	}

	public function add_raw_param(string $key, $value): void {
		$this->raw_params[strtolower($key)] = $value;
	}


	public function validate_params(): bool {

		$area		= $this->method.' '.$this->route;
		$parameters = &$this->attributes['parameters'];

		// loop all parameters, validate and format
		foreach($parameters as $key => $props){

			// parameter is not set
			if(!isset($this->raw_params[$key])){
				// set default value
				if($props['default']){
					$this->set_param($key, $props['default']);
				}
				continue;
			}

			$value		= $this->raw_params[$key];
			$type 		= $props['type'];
			$validation = $props['validation'];

			// loop validations and apply in defined order
			foreach($validation as $keyword => $task){
				if(!$task) continue;

				switch($keyword){
					case 'trim':
						// only string values can be trimmed
						if(is_string($value)){
							$value = trim($value);
						}
						break;
					case 'type':
						if(!Validation::type_validation($value, $type)){
							Logfile::error(sprintf('parameter %s must be of type %s', $key, $type), $area);
							$this->set_invalid_param($key, $type, $value,
								'type_validation_failed',
								sprintf('Value must be of type %s', $type)
							);
							continue 3;
						}
						break;
					case 'cast':
						$value = Validation::cast_type($value, $type);
						break;
					case 'regex':
						if(!Validation::regex_validation($value, $task)){
							Logfile::error(sprintf('parameter %s does not match %s', $key, $task), $area);
							$this->set_invalid_param($key,$type, $value,
								'regex_validation_failed',
								sprintf('Value must match %s', $task)
							);
							continue 3;
						}
						break;
					case 'callback':
						if(is_callable($task, false, $callable_name)){
							$result = call_user_func_array($task, [$value, $key, $this]);
							if($result !== true){
								Logfile::error(sprintf('parameter %s failed the %s validation. %s', $key, $callable_name, $result), $area);
								$this->set_invalid_param($key, $type, $value,
									'callback_validation_failed',
									$result ?: 'Invalid value'
								);
								continue 3;
							}
						}
						break;
					case 'valitron':
						$result = Validation::valitron_validation($value, $task);
						if($result !== true){
							Logfile::error(sprintf('parameter %s failed the Valitron validation. %s', $key, $result), $area);
							$this->set_invalid_param($key,$type, $value,
								'valitron_validation_failed',
								$result ?: 'Invalid value'
							);
							continue 3;
						}
						break;
					case 'format':
						$value = call_user_func_array($task, [$value, $key, $this]);
						break;
				}
			}

			// everything is OK, handover value
			$this->set_param($key, $value);
		}
		// check if any undefined parameters have been passed
		foreach($this->raw_params as $key => $value){
			if(empty($parameters[$key])){
				Logfile::notice(sprintf('The parameter %s is undefined and has been ignored', $key),$area);
			}
		}
		// check if required parameters are set properly
		foreach($parameters as $key => $props){
			if(!$props['required']) continue;

			if(!isset($this->params[$key]) || (!$props['allow-empty'] && $this->params[$key])){

				if(isset($this->invalid_params[$key])) continue;

				Logfile::error(sprintf('parameter %s is mandatory', $key), $area);
				$this->set_invalid_param($key, $props['type'], $this->params[$key] ?? '',
					'required_parameter',
				'This field is mandatory'
				);
			}
		}

		return count($this->invalid_params) === 0;
	}

	public function validate_dependencies(): bool {

		$area			= $this->method.' '.$this->route;
		$dependencies 	= &$this->attributes['dependencies'];
		$parameters		= array_keys($this->params);

		// loop all dependencies and validate
		foreach($dependencies as $dependency => $rules){
			switch($dependency){
				case 'Requires':
					foreach($rules as $field => $fields){
						$fields = Dependency::format_fields($fields);
						if(Dependency::contains_operators($field)){
							//$output .= sprintf("*   If your parameters matches the rule `%s`, you must also set %s.\n", $field, $fields);
						}else{
							//$output .= sprintf("*   If you specify a value for `%s`, you must also set %s.\n", $field, $fields);
						}
					}
					break;
				case 'Or':
					foreach($rules as $fields){
						if(!Dependency::validate_Or($parameters, $fields)){
							Logfile::error(sprintf('Inter-Parameter dependency (Or): at least on of `%s` must be set.', implode('` or `', $fields)), $area);
							$this->set_invalid_dependency($dependency,
								'dependency_validation_failed',
								sprintf("At least on of `%s` must be set", implode('` or `', $fields))
							);
						}
					}
					break;
				case 'OnlyOne':
					foreach($rules as $fields){
						//$output .= sprintf("*   You must include either the `%s` parameter.\n", implode('` or `', $fields));
					}
					break;
				case 'AllOrNone':
					foreach($rules as $fields){
						//$output .= sprintf("*   The parameters `%s` must be used together.\n", implode('` and `', $fields));
					}
					break;
				case 'ZeroOrOne':
					foreach($rules as $fields){
						//$output .= sprintf("*   The parameters `%s` are mutually exclusive, so use only one of them.\n", implode('` and `', $fields));
					}
					break;
				case 'Custom':
					foreach($rules as $attr){
						//$output .= sprintf("*   %s.\n", $attr['description'] ?? 'Custom dependency without description');
					}
					break;
			}
		}



		return count($this->invalid_dependencies) === 0;
	}

	public function get_body(): string {
		return $this->body;
	}

	public function set_body(string $data): void {
		$this->body = $data;

		$parameters = @json_decode($data, true);

		if(is_array($parameters)){
			$this->add_raw_params($parameters);
		}
	}

	public function get_route(): string {
		return $this->route;
	}

	public function set_route(string $route): void {
		$this->route = untrailing_slash_it($route);
	}

	public function get_attributes(): array {
		return $this->attributes;
	}

	public function get_attribute(string $key, $default = null){
		return $this->attributes[strtolower($key)] ?? $default;
	}

	public function set_attributes(array $attributes): void {
		foreach($attributes as $key => $val){
			$this->set_attribute($key, $val);
		}
	}

	public function set_attribute(string $key, $val): void {
		$this->attributes[strtolower($key)] = $val;
	}

	public function get_invalid_params(): ?array {
		return count($this->invalid_params)? $this->invalid_params : null;
	}

	public function set_invalid_param(string $key, string $type, $value, string $error_code, string $error_message): void {
		$this->invalid_params[$key] = [
			'key' 			=> $key,
			'type'			=> $type,
			'value' 		=> $value,
			'error_code'	=> $error_code,
			'error_message'	=> $error_message,
		];
	}

	public function get_invalid_dependencies(): ?array {
		return count($this->invalid_dependencies)? $this->invalid_dependencies : null;
	}

	public function set_invalid_dependency(string $dependency, string $error_code, string $error_message): void {
		$this->invalid_dependencies[] = [
			'dependency' 	=> $dependency,
			'error_code'	=> $error_code,
			'error_message'	=> $error_message,
		];
	}

	public function get_api_key(): string {
		if($this->has_param('x-api-key')){
			return $this->get_param('x-api-key');
		}
		if($this->has_param('api-key')){
			return $this->get_param('api-key');
		}

		return '';
	}

	private function set_param(string $key, $value): void {
		$this->params[$key] = $value;
	}


}
