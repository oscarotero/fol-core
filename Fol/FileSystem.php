<?php
/**
 * Fol\Filesystem
 * 
 * Simple class to manage files and folders
 */
namespace Fol;

class FileSystem {
	private $path;

	/**
	 * static function to resolve '//' or '/./' or '/foo/../' in a path
	 * 
	 * @param  string $path Path to resolve
	 * 
	 * @return string
	 */
	public static function fixPath ($path) {
		$replace = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];

		do {
			$path = preg_replace($replace, '/', $path, -1, $n);
		} while ($n > 0);

		return $path;
	}


	/**
	 * Constructor
	 * 
	 * @param string $path Base path
	 */
	public function __construct ($path = null) {
		$this->path = BASE_PATH;

		if ($path !== null) {
			$this->cd($path);
		}
	}


	/**
	 * Returns the current path or a relative path
	 * 
	 * @param  string $path The relative path. If it's not defined, returns the current path
	 * 
	 * @return string
	 */
	public function getPath ($path = null) {
		if (empty($path)) {
			return $this->path;
		}

		if ($path[0] !== '/') {
			$path = "/$path";
		}

		return self::fixPath($this->path.$path);
	}


	/**
	 * Open a file and returns a splFileObject instance.
	 * 
	 * @param  string $path The file path (relative to the current path)
	 * @param  string $openMode The open mode. See fopen function to get all available modes
	 * 
	 * @return SplFileObject
	 * 
	 * @see  SplFileObject class
	 */
	public function openFile ($path = null, $openMode = 'r') {
		return new \SplFileObject($this->getPath($path));
	}


	/**
	 * Returns a SplFileInfo instance to access to the file info
	 * 
	 * @param  string $path The file path (relative to the current path)
	 * 
	 * @return SplFileInfo
	 *
	 * @see  SplFileInfo class
	 */
	public function getInfo ($path = null) {
		return new \SplFileInfo($this->getPath($path));
	}


	/**
	 * Change the current directory
	 * 
	 * @param string $path Relative path with the new position
	 * @param  bool $create Create the directory if doesn't exist
	 * 
	 * @return $this
	 */
	public function cd ($path, $create = false) {
		$this->path = $this->getPath($path);

		if ($create === true) {
			$this->mkdir();
		}

		return $this;
	}


	/**
	 * Returns a recursive iterator to explore all directories and subdirectories
	 * 
	 * @return RecursiveIteratorIterator
	 */
	public function getRecursiveIterator ($path = null) {
		return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getPath($path), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
	}


	/**
	 * Returns an iterator to explore the current path
	 * 
	 * @return FilesystemIterator
	 */
	public function getIterator ($path = null) {
		return new \FilesystemIterator($this->getPath($path));
	}


	/**
	 * Remove all files and subdirectories of the current path
	 * 
	 * @return $this
	 */
	public function clear ($path = null) {
		foreach ($this->getRecursiveIterator($path) as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}

		return $this;
	}


	/**
	 * Remove the current path and all its content
	 * 
	 * @return $this
	 */
	public function remove ($path) {
		if ($this->getInfo($path)->isDir()) {
			$this->clear($path);

			rmdir($this->getPath($path));
		} else {
			unlink($this->getPath($path));
		}

		return $this;
	}


	/**
	 * Creates a new directory
	 * 
	 * @param  string  $name Directory name. If it's not specified, use the current defined path
	 * @param  integer $mode Permissions assigned to the directory
	 * @param  boolean $recursive Creates the directory in recursive mode or not. True by default
	 * 
	 * @return $this
	 */
	public function mkdir ($name = '', $mode = 0777, $recursive = true) {
		$path = $this->getPath($name);

		if (!is_dir($path)) {
			mkdir($this->getPath($name), $mode, $recursive);
		}

		return $this;
	}


	/**
	 * Copy a file
	 * 
	 * @param  mixed $original The original file. It can be an array (from $_FILES), an url, a base64 file or a path
	 * @param  string $name The name of the created file. If it's not specified, use the same name of the original file. For base64 files, this parameter is required.
	 * 
	 * @return string The created filename or false if there was an error
	 */
	public function copy ($original, $name = null) {
		if (is_array($original)) {
			return $this->saveFromUpload($original, $name);
		}

		if (substr($original, 0, 5) === 'data:') {
			return $this->saveFromBase64($original, $name);
		}

		if (strpos($original, '://')) {
			return $this->saveFromUrl($original, $name);
		}

		$destination = $this->getPath($name);

		if (!@copy($original, $destination)) {
			throw new \Exception("Unable to copy '$original' to '$destination'");
		}

		return $name;
	}


	/**
	 * Private function to save a file from upload ($_FILES)
	 * 
	 * @param  array $original Original file data
	 * @param  string $name Name used for the new file
	 * 
	 * @return string The created filename or false if there was an error
	 */
	private function saveFromUpload (array $original, $name) {
		if (empty($input['tmp_name']) || !empty($input['error'])) {
			return false;
		}

		if ($name === null) {
			$name = $original['name'];
		} elseif (!pathinfo($name, PATHINFO_EXTENSION) && ($extension = pathinfo($original['name'], PATHINFO_EXTENSION))) {
			$name .= ".$extension";
		}

		$destination = $this->getPath($name);

		if (!@rename($original, $destination)) {
			throw new \Exception("Unable to copy '$original' to '$destination'");
		}

		return $name;
	}


	/**
	 * Private function to save a file from base64 string
	 * 
	 * @param  array $original Original data
	 * @param  string $name Name used for the new file
	 * 
	 * @return string The created filename or false if there was an error
	 */
	private function saveFromBase64 ($original, $name) {
		if (empty($name)) {
			throw new \Exception("The argument 'name' is required for base64 saving files");
		}

		$fileData = explode(';base64,', $original, 2);

		if (!pathinfo($name, PATHINFO_EXTENSION) && preg_match('|data:\w+/(\w+)|', $fileData[0], $match)) {
			$name .= '.'.$match[1];
		}

		$destination = $this->getPath($name);

		if (!@file_put_contents($destination, base64_decode($fileData[1]))) {
			throw new \Exception("Unable to copy base64 to '$destination'");
		}

		return $name;
	}


	/**
	 * Private function to save a file from an url
	 * 
	 * @param  array $original Original file url
	 * @param  string $name Name used for the new file
	 * 
	 * @return string The created filename or false if there was an error
	 */
	private function saveFromUrl ($original, $name) {
		if ($name === null) {
			$name = pathinfo($original, PATHINFO_BASENAME);
		} else if (!pathinfo($name, PATHINFO_EXTENSION) && ($extension = pathinfo(parse_url($original, PHP_URL_PATH), PATHINFO_EXTENSION))) {
			$name .= ".$extension";
		}

		$destination = $this->getPath($name);

		if (!($content = @file_get_contents($original)) || !@file_put_contents($destination, $content)) {
			throw new \Exception("Unable to copy '$original' to '$destination'");
		}

		return $name;
	}
}
