<?php

namespace Foodsharing\Lib;

use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

class Twig
{
	/**
	 * @var Twig_Loader_Filesystem
	 */
	private $loader;

	/**
	 * @var Twig_Environment
	 */
	private $twig;

	public function __construct(TwigExtensions $twigExtensions)
	{
		$this->loader = new Twig_Loader_Filesystem(__DIR__ . '/../../views');

		$this->twig = new Twig_Environment($this->loader, [
			'debug' => defined('FS_ENV') && FS_ENV === 'dev',
			'cache' => __DIR__ . '/../../tmp/.views-cache',
			'strict_variables' => true
		]);

		$this->twig->addExtension($twigExtensions);
		$this->twig->addExtension(new Twig_Extension_Debug());
	}

	public function addGlobal($name, $value)
	{
		$this->twig->addGlobal($name, $value);
	}

	public function render($view, $data)
	{
		return $this->twig->render($view, $data);
	}
}