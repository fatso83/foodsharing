<?php

namespace Foodsharing\Lib\Cache;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;

class Caching
{
	private $cacheRules;
	private $cacheMode;
	private $session;
	private $mem;

	public function __construct($cache_rules, Session $session, Mem $mem)
	{
		$this->session = $session;
		$this->mem = $mem;
		$this->cacheRules = $cache_rules;
		$this->cacheMode = $this->session->may() ? 'u' : 'g';
	}

	public function lookup()
	{
		if (isset($this->cacheRules[$_SERVER['REQUEST_URI']][$this->cacheMode]) && ($page = $this->mem->getPageCache()) !== false && !isset($_GET['flush'])) {
			echo $page;
			exit();
		}
	}

	public function shouldCache()
	{
		return isset($this->cacheRules[$_SERVER['REQUEST_URI']][$this->cacheMode]);
	}

	public function cache($content)
	{
		$this->mem->setPageCache(
			$content,
			$this->cacheRules[$_SERVER['REQUEST_URI']][$this->cacheMode]
		);
	}
}
