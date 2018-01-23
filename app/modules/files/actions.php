<?php

class FilesActions {

	protected $uploads_dir;

	private $ignored_files = ['.', '..', '.htaccess'];


	/** ----------------------------------------------------------------------------
	 * Constructor
	 */

	public function __construct($dependencies) {
		$dependencies->register($this);

		$this->uploads_dir = _STORAGE_DIR . 'uploads/';
	}


	/** ----------------------------------------------------------------------------
	 * Get files list
	 */

	public function get_files_list($sub_dir = '') {
		$files_dir = trim($this->uploads_dir . $sub_dir, '/') . '/';

		if (!is_dir($files_dir)) {
			return false;
		}

		$files_list = scandir($files_dir);
		$files_data = [];

		foreach ($files_list as $key => $file_name) {
			if (!in_array($file_name, $this->ignored_files)) {
				$file_path = $files_dir . $file_name;
				$file_type = mime_content_type($file_path);

				$arr['type'] = $file_type;
				$arr['full-name'] = $file_name;

				// Entries for directories
				if ($file_type == 'directory') {
					$arr['name'] = $file_name;
					$arr['children'] = count(scandir($files_dir . $file_name)) - 2;
				}

				// Entries for files
				else {
					$extension = pathinfo($file_name, PATHINFO_EXTENSION);

					$arr['name'] = str_replace('.' . $extension, '', $file_name);
					$arr['extension'] = $extension;
					$arr['size'] = filesize($file_path);
					$arr['path'] = $file_path;
					$arr['full-path'] = ROOT_URL . $file_path;
				}

				$files_data[] = $arr;
			}
		}
		return $files_data;
	}


	/** ----------------------------------------------------------------------------
	 * Remove file
	 */

	public function remove_file($file) {
		$file_path = $this->uploads_dir . $file;

		if (!file_exists($file_path)) {
			throw new Exception("File {$file} does not exist in specified location of uploads.");
		}
		else {
			$result = unlink($file_path);

			if ($result) return true;
			else {
				$last_error = error_get_last();
				if (is_array($last_error)) {
					throw new Exception("Error occured while trying to remove file `{$file_path}`: {$last_error['message']}");
				}
				else {
					throw new Exception("Unknown error occured while trying to remove file `{$file_path}`");
				}
			}
		}
	}
}