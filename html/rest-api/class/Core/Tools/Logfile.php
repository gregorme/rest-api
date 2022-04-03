<?php

namespace RestAPI\Core\Tools;

class Logfile {

	private static $log = [];

	public static function info(string $message, string $area = ''): void {
		self::add_log_entry('info', $message, $area);
	}

	public static function notice(string $message, string $area = ''): void {
		self::add_log_entry('notice', $message, $area);
	}

	public static function success(string $message, string $area = ''): void {
		self::add_log_entry('success', $message, $area);
	}

	public static function warning(string $message, string $area = ''): void {
		self::add_log_entry('warning', $message, $area);
	}

	public static function error(string $message, string $area = ''): void {
		self::add_log_entry('error', $message, $area);
	}

	public static function debug(string $message, string $area = ''): void {
		self::add_log_entry('debug', $message, $area);
	}


	public static function to_string(): string {
		return join(PHP_EOL, self::get_entries());
	}

	public static function get_entries(): array {
		return array_map('self::format', self::$log);
	}

	public static function clear(): void {
		self::$log = [];
	}

	private static function add_log_entry(string $level, string $message, string $area = ''): void {
		global $api_settings;

		$log_levels = array_map('trim', explode(',', $api_settings['debug.loglevel']));

		if(!in_array($level, $log_levels)) return;

		self::$log[] = [
			'area'		=> $area,
			'level'		=> $level,
			'message' 	=> $message,
		];
	}

	private static function format(array $entry): string {
		return sprintf(
			'%s: %s%s',
			strtoupper($entry['level']),
			$entry['area']? '['.$entry['area'].'] ' : '',

			$entry['message']
		);
	}

}
