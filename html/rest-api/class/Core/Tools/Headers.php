<?php

namespace RestAPI\Core\Tools;

class Headers {


	private static $types = ['json', 'html', 'file', 'download', 'options', 'custom'];
	private static $file_types = ['file', 'download'];

	private static $headers = [
		'default' => [
			'Access-Control-Allow-Origin' => '%allow-origin%',
			'Access-Control-Allow-Credentials' => 'true',
			'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Api-Key',
			'Access-Control-Allow-Methods' => '%allow-methods%',
		],
		'robots' => [
			'X-Robot-Tag' => 'noindex',
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
		],
		'json' => [
			'Content-Type'	=> 'application/json; charset=utf-8',
		],
		'html' => [],
		'file' => [],
		'download' => [],
		'options' => [
			'Content-Type'	=> 'application/json; charset=utf-8',
			'Access-Control-Max-Age' => 60,
		],
		'custom' => [],
	];




	public static function get_types(): array {
		return self::$types;
	}

	public static function type_exists(string $type): bool {
		return in_array($type, self::$types);
	}

	public static function is_file_type(string $type): bool {
		return in_array($type, self::$file_types);
	}

	public static function get_headers(string $type): array {

		// default headers are always sent
		$headers = self::$headers['default'];

		if(!self::type_exists($type)){
			Logfile::warning(sprintf('unknown response headers for type "%s", fallback to default', $type));
		}

		// add robots and caching headers
		if($type !== 'options'){
			$headers = array_merge($headers, self::$headers['robots']);
		}

		// add type headers
		$headers = array_merge($headers, self::$headers[$type]);

		return self::prepare_headers($headers);
	}

	public static function prepare_headers(array $headers): array {
		global $api_settings;

		$placeholders = [
			'%allow-origin%' 	=> $api_settings['headers.origin'],
			'%allow-methods%'	=> $api_settings['headers.methods'],
		];

		foreach($headers as $key => $val){
			$headers[$key] = str_replace(array_keys($placeholders), array_values($placeholders), $val);
		}

		return $headers;
	}

}
