<?php
/**
 * Class File Upload Chunk
 *
 * This is the worker who receives all upload chunks and join and save them to a temporary file.
 * The unique name is returned and is used as unique identifier for the next chunks.
 **
 * @author		Greogr Müller-Elmau @ schalk&friends
 * @since		04/2021
 * @package 	Rest API
 */

namespace RestAPI\Core\Tools;

use RestAPI\Core\ErrorResponse;

class ChunkUpload {

	private $path;

	public function __construct(){

		$this->path = REST_API_DOC_ROOT.'/html/uploads/tmp/';

	}

	/** ------ PUBLIC METHODS ------ **/

	/**
	 * Ajax: process a chunk of the current file upload
	 *
	 * @param array $post_data
	 */
	public function handle_chunk_data(array $post_data): array {

		$file_name 	= $post_data['file'];
		$file_id 	= $post_data['file_id']?: $this->create_file_id($file_name);

		$file_path = $this->get_file_path($file_id);
		$file_data = $this->decode_chunk($post_data['file_data']);

		if(!$file_data){
			new ErrorResponse('invalid_file_chunk', 'Invalid file chunk received', 400);
		}

		file_put_contents($file_path, $file_data, FILE_APPEND);

		return ['file_id' => $file_id, 'path' => $file_path];
	}



	/** ------ PRIVATE METHODS ------ **/

	/**
	 * Create a unique file id as a substitute for the file name.
	 *
	 * @param string $file_name Original file name.
	 * @return string
	 */
	private function create_file_id(string $file_name): string {

		if(!$file_name) return '';

		$unique = sha1($file_name.microtime(true));
		$path = pathinfo($file_name);

		return $unique.'.'.$path['extension'];
	}

	/**
	 * Retrieve the file path to store the file data.
	 * Check if the upload directory exits and create it if necessary.
	 *
	 * @param string $file_id File ID
	 * @return string
	 */
	private function get_file_path(string $file_id): string {

		if(!$file_id) return '';

		if(!file_exists($this->path)){
			mkdir($this->path, 0777, true);
		}

		return $this->path.$file_id;
	}

	/**
	 * Decode a base64 encoded file chunk and validate the data.
	 *
	 * @param string $data File chunk.
	 * @return bool|string            false on failure.
	 */
	private function decode_chunk(string $data){

		$data = explode(';base64,', $data);

		if(!is_array($data) || !isset($data[1])) return false;

		$data = base64_decode($data[1]);

		if(!$data) return false;

		return $data;
	}

}
