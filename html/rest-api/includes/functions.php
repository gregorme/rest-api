<?php


use RestAPI\Core\ErrorResponse;

if(!function_exists('getallheaders')){
	function getallheaders(): array{
		$headers = [];
		foreach($_SERVER as $name => $value){
			if(substr($name, 0, 5) == 'HTTP_'){
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}



/**
 * Retrieve a datetime string for the local timezone.
 *
 * @param string $time		Datetime string or timestamp, default 'now'.
 * @param string $format	Format of the output, default database format.
 * @return string
 */
function datetime(string $time='now', string $format='Y-m-d H:i:s'): string {
	global $api_settings;
	$output = '';
	try{
		$datetime = new DateTime($time, new DateTimeZone($api_settings['rest.timezone']));
		$output = $datetime->format($format);
	}catch(Exception $e){
		new ErrorResponse('timezone_error', $e->getMessage());
	}
	return $output;
}

function trailing_slash_it(string $string): string {
	return untrailing_slash_it($string).'/';
}

function untrailing_slash_it(string $string): string {
	return rtrim($string, '/\\');
}

function is_integer_string($value): bool {
	return strval((int)$value) === $value;
}

function is_float_string($value): bool {
	return strval((float)$value) === $value;
}

function is_bool_value($value): bool {

	if((is_int($value) || is_integer_string($value)) && in_array((int)$value, [0,1])){
		return true;
	}

	return (is_string($value) && in_array($value, ['true', 'false']));
}

function strip_tabs(string $value): string {
	return str_replace("\t", '', $value);
}

function singular_plural(int $no, string $singular, string $plural): string {
	return $no === 1 ? $singular : $plural;
}

function value_toString($value): string {

	switch(gettype($value)){
		case 'boolean':
			return (int)$value;
		case 'integer':
		case 'float':
		case 'double':
			return (string)$value;
		case 'string':
			return $value ?: "''";
		case 'array':
		case 'object':
			return json_encode($value);
		case 'resource':
			return 'resource';
		case 'NULL':
			return 'null';
		case 'unknown type':
		default:
			return 'unknown type';
	}
}

function natural_language_join(array $list, string $conjunction = 'and'): string {
	$last = array_pop($list);
	if(count($list)){
		return implode(', ', $list).' '.$conjunction.' '.$last;
	}
	return $last;
}

function sanitize_parameter_key(string $key): string {

	// replace spaces and dashes
	$key = str_replace([' ', '-', '.'], '_', $key);
	// remove all invalid characters
	$key = preg_replace("/[^a-z0-9_]/i", '', $key);
	// replace duplicate underscore
	$key = preg_replace("/_+/", '_', $key);
	// variables must not start with a number
	if(is_numeric(substr($key, 0, 1)))	$key = '_'.$key;

	return rtrim($key, '_');
}

