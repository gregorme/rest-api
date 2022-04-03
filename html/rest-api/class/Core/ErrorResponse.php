<?php

namespace RestAPI\Core;

class ErrorResponse extends Response {


	public function __construct($error_code, string $error_message, int $http_code = 403, $data = null){

		if($error_code === E_WARNING){
			$error_code = 'php_warning';
		}
		if($error_code === E_NOTICE){
			$error_code = 'php_notice';
		}

		$error_data = [
			'code'	=> $error_code,
			'message' => $error_message,
		];

		if($data){
			$error_data['data'] = $data;
		}

		parent::__construct($error_data, $http_code);

		$this->send();
		exit;
	}

}
