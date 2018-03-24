<?php

namespace Foodsharing;

use DebugBar\DataCollector\PDO\TraceablePDO;
use PDO;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DI
{
	/**
	 * @var \Foodsharing\DI
	 */
	public static $shared;

	private $cacheFile = __DIR__ . '/../tmp/di-cache.php';
	private $isDev;
	private $useCached = false;
	private $container;

	public static function initShared()
	{
		self::$shared = new self();
	}

	public function __construct()
	{
		$this->isDev = defined('FS_ENV') && FS_ENV === 'dev';

		$this->useCached = !$this->isDev && file_exists($this->cacheFile);

		if ($this->useCached) {
			require_once $this->cacheFile;
			$this->container = new \FoodsharingCachedContainer();
		} else {
			$this->container = new ContainerBuilder();
			$loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));

			$definition = new Definition();
			$definition
				->setAutowired(true)
				->setAutoconfigured(true)
				->setPublic(true);

			$loader->registerClasses($definition, 'Foodsharing\\', '*', '{Lib/Flourish,Lib/Cache,Lib/View/v*,Dev,Debug}');
		}
	}

	public function configureMysqli($host, $user, $password, $db)
	{
		if ($this->useCached) {
			return;
		}

		$this->container
			->register(\mysqli::class, \mysqli::class)
			->addArgument($host)
			->addArgument($user)
			->addArgument($password)
			->addArgument($db)
			->addMethodCall('query', ["SET NAMES 'utf8'"]);
	}

	public function useTraceablePDO($traceablePDO)
	{
		$this->container->set(PDO::class, $traceablePDO);

		if ($this->useCached) {
			return;
		}

		$this->container->register(PDO::class, TraceablePDO::class);
	}

	public function usePDO($dsn, $user, $password)
	{
		if ($this->useCached) {
			return;
		}

		$this->container
			->register(\PDO::class, \PDO::class)
			->addArgument($dsn)
			->addArgument($user)
			->addArgument($password)
			->addMethodCall('setAttribute', [PDO::ATTR_EMULATE_PREPARES, false])
			->addMethodCall('setAttribute', [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION])
			->addMethodCall('setAttribute', [PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES \'utf8\'']);
	}

	public function compile()
	{
		if ($this->useCached) {
			return;
		}

		$this->container->compile();

		if (!$this->isDev) {
			$dumper = new PhpDumper($this->container);
			file_put_contents($this->cacheFile, $dumper->dump(['class' => 'FoodsharingCachedContainer']));
		}
	}

	public function isCompiled()
	{
		return $this->container->isCompiled();
	}

	/**
	 * @throws \Exception
	 */
	public function get($id)
	{
		return $this->container->get($id);
	}
}

DI::initShared();