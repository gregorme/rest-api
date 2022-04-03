<?php

namespace RestAPI\Core;

use RestAPI\Core\Tools\Headers;
use RestAPI\Core\Tools\Logfile;

class Response {

	private $type			= 'json';
	private $status 		= 200;
	private $headers 		= [];
	private $data 			= null;
	private $file			= null;


	public function __construct($data = null, int $status = 200, string $type = 'json'){

		$this->set_status($status);
		$this->set_type($type);

		if(Headers::is_file_type($type)){
			$this->set_file($data);
		}else{
			$this->set_data($data);
		}
	}

	public function set_type(string $type): void {
		if(!Headers::type_exists($type)){
			Logfile::warning(sprintf('unknown response type "%s", fallback to "custom". Check headers!', $type));
			$type = 'custom';
		}
		$this->type = $type;
		$this->set_headers(Headers::get_headers($type), true);
	}

	public function get_type(): string {
		return $this->type;
	}

	public function set_headers(array $headers, $replace = true): void {

		if($replace) $this->headers = [];

		foreach($headers as $key => $val){
			$this->set_header($key, $val);
		}
	}

	public function set_header(string $key, string $value): void {
		$this->headers[$key] = $value;
	}

	public function get_headers(): array {
		return $this->headers;
	}

	public function remove_header(string $key): void {
		unset($this->headers[$key]);
	}

	public function get_status(): int {
		return $this->status;
	}

	public function set_status(int $code): void {
		$this->status = $code;
	}

	public function get_data(){
		return $this->data;
	}

	public function set_data($data): void {
		$this->data = $data;
	}

	public function set_file(): void {

	}

	public function get_file(): ?string {
		return $this->file;
	}

	// raw_file_data ?
	// file name
	// file_size
	// download_name


	public function send($append_logfile = true){
		global $api_settings;

		if(!headers_sent()){
			foreach($this->headers as $key => $val){
				header(sprintf('%s: %s', $key, $val));
			}
		}

		http_response_code($this->status);

		$response_data = $this->data;

		// add logfile entries
		if($api_settings['debug.response.logfile'] && $append_logfile){
			$response_data['logfile'] = Logfile::get_entries();
		}

		// TODO add output switch by type

		echo json_encode($response_data);
		exit;
	}

}
