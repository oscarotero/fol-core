<?php
/**
 * Fol\Router\RouteFactory
 * 
 * Class to generate route classes
 * Based in PHP-Router library (https://github.com/dannyvankooten/PHP-Router)
 */
namespace Fol\Router;

use Fol\App;

class RouteFactory {
	private $app;

	public function __construct (App $app) {
		$this->app = $app;
	}

	private function getTarget ($target) {
		if (strpos($target, '::') === false) {
			$class = $target;
			$method = null;
		} else {
			list($class, $method) = explode('::', $target, 2);
		}

		$class = $this->app->namespace.'\\Controllers\\'.$class;

		return [$class, $method];
	}

	public function createRoute ($name, array $config = array()) {
		$config['target'] = $this->getTarget($config['target']);

		return new Route($name, $config, $this->app);
	}

	public function createFileRoute ($cachedPath, $originPath, $target) {
		return new FileRoute($cachedPath, $originPath, $this->getTarget($target), $this->app);
	}

	public function createErrorRoute ($target) {
		return new ErrorRoute($this->getTarget($target), $this->app);
	}
}
