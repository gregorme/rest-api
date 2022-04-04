<?php
/**
 * Asset Controller
 *
 * @author        ...
 * @since        ...
 * @package        ...
 * @version    ...
 */

namespace RestAPI\Core\Endpoints;

use RestAPI\Core\ErrorResponse;
use RestAPI\Core\Request;
use RestAPI\Core\Response;
use RestAPI\Core\RouteController;
use RestAPI\Core\Tools\ChunkUpload;


class Asset extends RouteController{

	public function __construct(){

		$this->set_name('Asset API');
		$this->set_description('Handles all asset tasks including chunk upload, media library, folder and more.');
		$this->set_namespace('asset');

		$this->add_route('setup', [
			'GET' => [
				'name' => 'Setup',
				'description' => 'Retrieve the setup for the library',
				'access' => 'assets',
				'callback' => [&$this, 'prepare_setup'],
			],
		]);

		$this->add_route('upload', [
			'POST' => [
				'name' => 'Upload',
				'description' => 'Chunk file upload endpoint',
				'access' => 'assets',
				'callback' => [&$this, 'process_upload'],
				'parameters' => [
					'file_id' => [
						'type' => 'string',
						'in' => 'body',
						'required' => false,
						'default' => '',
						'allow-empty' => true,
						'description' => 'The temporary upload file ID / name',
					],
					'file' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The file name',
					],
					'file_type' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The file mime type',
					],
					'file_data' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The base64 string of the file chunk',
					],
				],
			],
		]);

		$this->add_route('save', [
			'POST' => [
				'name' => 'Move File',
				'description' => 'Store file in database and move the file to the target directory',
				'access' => 'assets',
				'callback' => [&$this, 'save_file'],
				'parameters' => [
					'tmp_name' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The temporary file name from chunk upload',
					],
					'tmp_path' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The temporary file path from chunk upload',
					],
					'src_name' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The original file name',
					],
					'file_type' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The file mime type',
					],
					'file_size' => [
						'type' => 'integer',
						'in' => 'body',
						'required' => true,
						'default' => 0,
						'description' => 'The file size in byte',
					],
					'folder' => [
						'type' => 'integer',
						'in' => 'body',
						'required' => true,
						'default' => 0,
						'description' => 'The asset folder ID',
					],
				],
			],
		]);

		$this->add_route('folder', [
			'POST' => [
				'name' => 'Create Folder',
				'description' => 'Create a new asset folder',
				'access' => 'assets',
				'callback' => [&$this, 'create_folder'],
				'parameters' => [
					'name' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The folder name',
					],
				],
			],
		]);

		$this->add_route('folder/:id', [
			'PUT' => [
				'name' => 'Update Folder',
				'description' => 'Update an existing asset folder',
				'access' => 'assets',
				'callback' => [&$this, 'update_folder'],
				'parameters' => [
					'id' => [
						'type' => 'integer',
						'in' => 'variables',
						'required' => true,
						'default' => 0,
						'description' => 'The folder ID, required for update',
					],
					'name' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The folder name',
					],
				],
			],
		]);

		$this->add_route('folder/:id/:truncate', [
			'DELETE' => [
				'name' => 'Delete Folder',
				'description' => 'Delete an asset folder, and truncate it optionally',
				'access' => 'assets',
				'callback' => [&$this, 'delete_folder'],
				'parameters' => [
					'id' => [
						'type' => 'integer',
						'in' => 'variables',
						'required' => true,
						'default' => 0,
						'description' => 'The folder ID, required for update',
					],
					'truncate' => [
						'type' => 'bool',
						'in' => 'variables',
						'required' => true,
						'default' => 0,
						'description' => 'Should the folder be truncated before deletion',
					],
				],
			],
		]);

		$this->add_route('/:id', [
			'PUT' => [
				'name' => 'Update Asset',
				'description' => 'Update the details of an an existing asset',
				'access' => 'assets',
				'callback' => [&$this, 'update_asset'],
				'parameters' => [
					'id' => [
						'type' => 'integer',
						'in' => 'variables',
						'required' => true,
						'default' => 0,
						'description' => 'The asset ID',
					],
					'folder_id' => [
						'type' => 'integer',
						'in' => 'body',
						'required' => true,
						'default' => 0,
						'description' => 'The folder ID',
					],
					'title' => [
						'type' => 'string',
						'in' => 'body',
						'required' => true,
						'default' => '',
						'description' => 'The asset title',
					],
				],
			],
			'DELETE' => [
				'name' => 'Delete Asset',
				'description' => 'Delete an existing asset and its file variations',
				'access' => 'assets',
				'callback' => [&$this, 'delete_asset'],
				'parameters' => [
					'id' => [
						'type' => 'integer',
						'in' => 'variables',
						'required' => true,
						'default' => 0,
						'description' => 'The asset ID',
					],
				],
			],
		]);

	}

	/**
	 * GET asset/setup
	 *
	 * @param Request $request
	 * @return void
	 */
	public function prepare_setup(Request $request): void {

		$response_data = [
			'files'		=> $this->get_user_assets(),
			'folders'	=> $this->get_user_folders(),
		];

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * POST asset/upload
	 *
	 * @param Request $request
	 * @return void
	 */
	public function process_upload(Request $request): void {

		$worker = new ChunkUpload();

		$response_data = $worker->handle_chunk_data($request->get_params());;

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * POST asset/save
	 *
	 * @param Request $request
	 * @return void
	 */
	public function save_file(Request $request): void {

		$response_data = $this->move_uploaded_file($request->get_params());

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * POST asset/folder
	 *
	 * @param Request $request
	 * @return void
	 */
	public function create_folder(Request $request): void {
		global $api_db, $current_user;

		$api_db->insert(
			'rest_api_folders',
			[
				'account_id' => $current_user['id'],
				'name' => $request->get_param('name')
			]
		);

		$response_data = [
			'folder' => $this->get_folder($api_db->insert_id),
		];

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * PUT asset/folder/:id
	 *
	 * @param Request $request
	 * @return void
	 */
	public function update_folder(Request $request): void {
		global $api_db, $current_user;

		$folder_id = $request->get_param('id');

		$api_db->update(
			'rest_api_folders',
			['name' => $request->get_param('name')],
			['ID' => $folder_id, 'account_id' => $current_user['id']]
		);

		$response_data = [
			'folder' => $this->get_folder($folder_id),
		];

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * DELETE asset/folder/:id
	 *
	 * @param Request $request
	 * @return void
	 */
	public function delete_folder(Request $request): void {
		global $api_db;

		$folder_id 	= $request->get_param('id');
		$truncate 		= $request->get_param('truncate');

		$folder = $this->get_folder($folder_id);

		if(!$folder){
			new ErrorResponse('invalid_folder', 'The folder ID is unknown or invalid', 400);
		}

		$deleted_assets = 0;

		if($truncate){
			// empty folder >> delete assets
			$deleted_assets = $this->empty_folder($folder_id);
		}else{
			// keep images >> move them to default folder
			$api_db->update('rest_api_assets', ['folder_id' => 0], ['folder_id' => $folder_id]);
		}

		$api_db->delete('rest_api_folders', ['ID' => $folder_id]);

		if($truncate){
			$message = sprintf(
				'Folder &raquo; %s &laquo; and %s assets deleted. Content elements cleaned up.',
				$folder['name'],
				$deleted_assets
			);
		}else{
			$message = sprintf(
				'Folder &raquo; %s &laquo; deleted and all assets moved to the Default folder.',
				$folder['name']
			);
		}

		$response_data = [
			'message' => $message,
		];


		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * PUT asset/:id
	 *
	 * @param Request $request
	 * @return void
	 */
	public function update_asset(Request $request): void {
		global $api_db, $current_user;

		$asset_id 	= $request->get_param('id');
		$folder_id 	= $request->get_param('folder_id');
		$title 		= $request->get_param('title');

		$api_db->update(
			'rest_api_assets',
			['title' => $title, 'folder_id' => $folder_id],
			['ID' => $asset_id, 'account_id' => $current_user['id']]
		);

		$response_data = [
			'asset' => $this->get_asset($asset_id),
		];

		$response = new Response($response_data);
		$response->send();
	}

	/**
	 * DELETE asset/:id
	 *
	 * @param Request $request
	 * @return void
	 */
	public function delete_asset(Request $request): void{
		global $api_db, $current_user;

		$asset_id = $request->get_param('id');

		$sql = "SELECT `ID`, `file` FROM rest_api_assets WHERE `ID` = %s AND `account_id` = %s";
		$asset = $api_db->get_row($api_db->prepare($sql, [$asset_id, $current_user['id']]), ARRAY_A);

		if(empty($asset['ID'])){
			new ErrorResponse('unknown_asset', 'The given asset is unknown', 400);
		}

		$this->delete_asset_files($asset['ID'], $asset['file']);

		$response_data = [
			'success' => true,
		];

		$response = new Response($response_data);
		$response->send();
	}







	/** ------ PRIVATE METHODS ------ **/

	public function get_asset(int $asset_id){
		global $api_db;

		$sql = "SELECT * FROM rest_api_assets WHERE `ID` = %s";
		$asset = $api_db->get_row($api_db->prepare($sql, [$asset_id]), ARRAY_A);

		return !empty($asset['ID'])? $this->prepare_asset_data($asset) : false;
	}

	public function get_user_assets(): array {
		global $api_db, $current_user;

		$sql = "SELECT * FROM rest_api_assets WHERE `account_id` = %s ORDER BY `created` DESC";
		$results = $api_db->get_results($api_db->prepare($sql, [$current_user['id']]), ARRAY_A);

		$assets = [];

		foreach($results as $asset){
			$assets[] = $this->prepare_asset_data($asset);
		}

		return $assets;
	}

	public function get_user_folders(): array {
		global $api_db, $current_user;

		$sql = "SELECT `ID`, `name` FROM rest_api_folders WHERE `account_id` = %s ORDER BY `name` ASC";
		$results = $api_db->get_results($api_db->prepare($sql, [$current_user['id']]));

		$folders = [['ID' => 0, 'name' => 'Default']];

		foreach($results as $folder){
			$folders[] = [
				'ID'	=> (int)$folder->ID,
				'name'	=> $folder->name,
			];
		}

		return $folders;
	}

	public function get_folder(int $folder_id){
		global $api_db, $current_user;

		$sql = "SELECT `ID`, `name` FROM rest_api_folders WHERE `ID` = %s AND `account_id` = %s";
		$folder = $api_db->get_row($api_db->prepare($sql, [$folder_id, $current_user['id']]));

		if(empty($folder->ID)) return false;

		return [
			'ID'	=> (int)$folder->ID,
			'name'	=> $folder->name,
		];

	}

	public function asset_url(string $path) : string {
		return $_ENV['APP_URL'].$path;
	}

	public function preview_url(string $path) : string {
		if(pathinfo($path, PATHINFO_EXTENSION) === 'svg'){
			return $path;
		}
		if(pathinfo($path, PATHINFO_EXTENSION) === 'mp4'){
			return sprintf(
				'%s%s/preview/%s.jpg',
				$_ENV['APP_URL'],
				dirname($path),
				pathinfo($path, PATHINFO_FILENAME)
			);
		}
		return sprintf(
			'%s%s/preview/%s',
			$_ENV['APP_URL'],
			dirname($path),
			basename($path)
		);
	}

	public function poster_url(string $path) : string {

		return sprintf(
			'%s%s/poster/%s.jpg',
			$_ENV['APP_URL'],
			dirname($path),
			pathinfo($path, PATHINFO_FILENAME)
		);
	}

	public function prepare_asset_data(array $asset): array {

		$data = [
			'ID'		=> (int)$asset['ID'],
			'title'		=> $asset['title'],
			'folder_id' => (int)$asset['folder_id'],
			'type'		=> $asset['type'],
			'mime'		=> $asset['mime'],
			'size'		=> $asset['size'],
			'created'	=> datetime($asset['created'], 'd.m.Y H:i'),
			'file'		=> basename($asset['file']),
			'url'		=> $this->asset_url($asset['file']),
		];

		if($asset['width'] && $asset['height']){
			$data['width'] = $asset['width'];
			$data['height'] = $asset['height'];
		}
		if($asset['type'] === 'image'){
			$data['preview'] = $this->preview_url($asset['file']);
		}
		if($asset['type'] === 'video'){
			$data['duration'] = $asset['duration'];
			$data['preview'] = $this->preview_url($asset['file']);
			$data['poster'] = $this->poster_url($asset['file']);
		}

		return $data;
	}

	/**
	 * Create new file entry and move the uploaded file to the upload directory.
	 *
	 * @param array $post_params		Request Json data.
	 * @return array
	 */
	public function move_uploaded_file(array $post_params): array {
		global $api_db, $current_user, $api_settings;

		$tmp_name 	= $post_params['tmp_name'];
		$tmp_path 	= $post_params['tmp_path'];
		$src_name 	= $post_params['src_name'];
		$file_type 	= $post_params['file_type'];
		$file_size 	= $post_params['file_size'];
		$folder_id  = $post_params['folder'];

		if(!$file_type && file_exists($tmp_path)){
			$file_type = mime_content_type($tmp_path);
		}

		$file_name 		= pathinfo($src_name, PATHINFO_FILENAME);
		$file_ext 		= pathinfo($src_name, PATHINFO_EXTENSION);
		$file_name 		= $this->sanitize_file_name($file_name);
		$target_dir 	= sprintf('%s/html/uploads/%s/%s/', REST_API_DOC_ROOT, $current_user['id'], date('Y'));
		$poster_dir		= $target_dir.'poster/';
		$preview_dir	= $target_dir.'preview/';
		$file_name 		= $this->get_unique_file_name($file_name, '.'.$file_ext, $target_dir);
		$target_path 	= $target_dir.$file_name;
		$file_url		= sprintf('/uploads/%s/%s/%s', $current_user['id'], date('Y'), $file_name);

		if(!file_exists($tmp_path)){
			new ErrorResponse('file_not_found', 'File not found - file not saved', 400);
		}

		if(!file_exists($target_dir)){
			mkdir($target_dir, 0777, true);
			mkdir($preview_dir, 0777, true);
			mkdir($poster_dir, 0777, true);
		}

		exec("mv $tmp_path $target_path", $output, $return_var);

		if($return_var !== 0){
			new ErrorResponse('file_move_failed', 'Failed to move file to uploads directory');
		}

		$width = $height = 0;
		$duration = '';
		$type = 'file';

		switch($file_type){
			case 'image/jpg':
			case 'image/jpeg':
			case 'image/png':
				\Tinify\setKey($api_settings['asset.tinify']);

				$source = \Tinify\fromFile($target_path);
				$source->toFile($target_path);

				$file_size = filesize($target_path);
				$size = getimagesize($target_path);
				$width = $size[0];
				$height = $size[1];
				$type = 'image';

				$resized = $source->resize(array(
					'method' => 'scale',
					'height' => 200
				));
				$resized->toFile($preview_dir.$file_name);
				break;

			case 'video/mp4':
				$getID3 = new \getID3();
				$details = $getID3->analyze($target_path);
				$width = $details['video']['resolution_x'];
				$height = $details['video']['resolution_y'];
				$duration = $details['playtime_string'];
				$type = 'video';

				$cmd = 'ffmpeg -i %s -deinterlace -an -ss 1 -t 00:00:01 -r 1 -y -vcodec mjpeg -f mjpeg %s 2>&1';
				$poster_path = $poster_dir.pathinfo($file_name, PATHINFO_FILENAME).'.jpg';
				$preview_path = $preview_dir.pathinfo($file_name, PATHINFO_FILENAME).'.jpg';
				shell_exec(sprintf($cmd, escapeshellarg($target_path), escapeshellarg($poster_path)));

				\Tinify\setKey($api_settings['asset.tinify']);

				$source = \Tinify\fromFile($poster_path);
				$source->toFile($poster_path);

				$resized = $source->resize(array(
					'method' => 'scale',
					'height' => 200
				));
				$resized->toFile($preview_path);

				break;
		}

		$title = str_replace(['_', '-'], ' ',pathinfo($file_name, PATHINFO_FILENAME));

		$insert_data = array(
			'title' 	=> ucwords($title),
			'account_id'	=> $current_user['id'],
			'folder_id'	=> $folder_id,
			'type' 		=> $type,
			'mime' 		=> $file_type,
			'width' 	=> $width,
			'height' 	=> $height,
			'duration' 	=> $duration,
			'size' 		=> $file_size,
			'created'	=> datetime(),
			'file' 		=> $file_url,
		);

		$api_db->insert('rest_api_assets', $insert_data);

		$insert_data['ID'] = $api_db->insert_id;

		return $this->prepare_asset_data($insert_data);
	}


	public function delete_asset_files(int $asset_id, string $path): void {
		global $api_db, $api_settings;

		if(is_callable($api_settings['asset.delete.hook'])){
			call_user_func_array($api_settings['asset.delete.hook'], [$asset_id, $path]);
		}

		$file_dir 		= dirname($path);
		$file_name 		= basename($path);
		$root_dir		= REST_API_DOC_ROOT.'/html';
		$asset_path 	= $root_dir.$path;
		$poster_path 	= sprintf('%s%s/poster/%s', $root_dir, $file_dir, $file_name);
		$preview_path 	= sprintf('%s%s/preview/%s', $root_dir, $file_dir, $file_name);

		if(file_exists($asset_path))	@unlink($asset_path);
		if(file_exists($poster_path))	@unlink($poster_path);
		if(file_exists($preview_path))	@unlink($preview_path);

		$api_db->delete('rest_api_assets', ['ID' => $asset_id]);
	}



	/**
	 * Sanitize a file name for display or saving tasks.
	 * This will remove/replace all invalid chars and symbols.
	 *
	 * @param string $title File name.
	 * @param string $context Usage type.
	 * @return string
	 */
	private function sanitize_file_name(string $title, string $context = 'display'): string {
		$title = strip_tags($title);
		$title = str_replace('_', '-', $title);
		// Preserve escaped octets.
		$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
		// Remove percent signs that are not part of an octet.
		$title = str_replace('%', '', $title);
		// Restore octets.
		$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

		if(mb_detect_encoding($title, 'UTF-8')){
			if(function_exists('mb_strtolower')){
				$title = mb_strtolower($title, 'UTF-8');
			}
		}

		$title = strtolower($title);
		$title = preg_replace('/&.+?;/', '', $title); // kill entities
		$title = str_replace('.', '-', $title);

		if('save' == $context){
			// Convert nbsp, ndash and mdash to hyphens
			$title = str_replace(array('%c2%a0', '%e2%80%93', '%e2%80%94'), '-', $title);

			// Strip these characters entirely
			$title = str_replace(array(
				// iexcl and iquest
				'%c2%a1', '%c2%bf',
				// angle quotes
				'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
				// curly quotes
				'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
				'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
				// copy, reg, deg, hellip and trade
				'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
				// acute accents
				'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
				// grave accent, macron, caron
				'%cc%80', '%cc%84', '%cc%8c',
			), '', $title);

			// Convert times to x
			$title = str_replace('%c3%97', 'x', $title);
		}

		$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
		$title = preg_replace('/\s+/', '-', $title);
		$title = preg_replace('|-+|', '-', $title);
		$title = trim($title, '-');

		if(strlen($title) > 80){
			$title = substr($title, 0,80);
		}

		return $title;
	}

	/**
	 * Retrieve a unique file name by adding a rev number if required.
	 *
	 * @param string $file_name	File name without file extension.
	 * @param string $file_ext	File extension including dot.
	 * @param string $path		Absolute path to the target directory of the file.
	 * @return string
	 */
	private function get_unique_file_name(string $file_name, string $file_ext, string $path): string {

		$rev = 1;
		$org_name = $file_name;
		while(file_exists($path.$file_name.$file_ext)){
			$rev++;
			$file_name = sprintf('%s-rev%s', $org_name, $rev);
		}

		return $file_name.$file_ext;
	}

	private function empty_folder(int $folder_id): int {
		global $api_db;

		$sql = "SELECT `ID`, `file` FROM rest_api_assets WHERE `folder_id` = %s";
		$results = $api_db->get_results($api_db->prepare($sql, [$folder_id]));

		foreach($results as $asset){
			$this->delete_asset_files($asset->ID, $asset->file);
		}

		return count($results);
	}

}
