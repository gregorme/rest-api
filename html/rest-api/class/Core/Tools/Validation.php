<?php

namespace RestAPI\Core\Tools;

use RestAPI\Core\ErrorResponse;
use Valitron\Validator;

class Validation {


	public static function type_exists(string $type): bool {
		return in_array($type, ['string', 'number', 'integer', 'float', 'array', 'object', 'bool']);
	}

	public static function type_validation($value, string $type): bool {

		switch($type){
			case 'integer': return is_integer($value) || is_integer_string($value);
			case 'number': return is_numeric($value);
			case 'float': return is_float($value) || is_float_string($value);
			case 'array': return is_array($value);
			case 'object': return is_object($value) || is_array($value);
			case 'bool': return is_bool($value) || is_bool_value($value);
			case 'string':
			default: return is_string($value);
		}
	}

	public static function regex_validation($value, string $regex): bool {

		if(strpos($regex, '/') !== 0){
			$regex = sprintf('/%s/', $regex);
		}

		return preg_match($regex, $value);
	}

	public static function valitron_validation($value, array $rules){
		$result 		= false;
		$errors			= [];
		$clean_rules 	= [];

		try{
			$validator = new Validator(['value' => $value]);
			//$validator->setPrependLabels(false);

			foreach($rules as $rule){
				if(is_string($rule) && $rule){
					$clean_rules[$rule] = ['value'];
				}
				else if(is_array($rule)){
					$length = count($rule);
					if(!$length) continue;

					if($length === 1){
						$clean_rules[$rule[0]] = ['value'];
					}else{
						$clean_rules[$rule[0]] = [array_merge(['value'], array_slice($rule, 1))];
					}
				}
			}

			$validator->rules($clean_rules);

			$result = $validator->validate();
			$errors = $validator->errors('value');
		}catch(\Exception $e){
			Logfile::error($e->getMessage());
			new ErrorResponse('valitron_error', $e->getMessage(), 403, explode(PHP_EOL, $e->getTraceAsString()));
		}

		return $result ? true : implode(', ', $errors);
	}

	public static function cast_type($value, string $type){
		switch($type){
			case 'integer': return (int)$value;
			case 'number': return (int)$value;
			case 'float': return (float)$value;
			case 'array': return (array)$value;
			case 'object': return (object)$value;
			case 'bool': return (bool)$value;
			case 'string':
			default: return trim((string)$value);
		}
	}

}
