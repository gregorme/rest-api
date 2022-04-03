<?php
/**
 *
 * https://medium.com/isa-group/inter-parameter-dependencies-in-rest-apis-4664e901c124
 *
 */
namespace RestAPI\Core\Tools;

class Dependency {




	public static function contains_operators(string $text): bool {
		return !preg_match("/^[a-z0-9_]+$/", $text);
	}


	public static function format_fields(array $fields): string {
		$output = [];

		foreach($fields as $k => $v){
			if(is_numeric($k)){
				$output[] = sprintf('`%s`', $v);
			}else{
				$output[] = sprintf('`%s` to ***%s***', $k, value_toString($v));
			}
		}

		return natural_language_join($output, 'and');
	}


	/**
	 * Dependency: Or
	 * Out of two or more parameters, at least one must be used.
	 * @param array $parameter_keys
	 * @param array $field_range
	 * @return bool
	 */
	public static function validate_Or(array $parameter_keys, array $field_range): bool {
		return count(array_intersect($parameter_keys, $field_range)) >= 1;
	}




	public static function parse_operator(string $string): array {

		dump($string);
		dump(eval("($string);"));

		//$parts = explode();

		return [];
	}

}
