<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Helpers\FlashMessageHelper;
use Foodsharing\Helpers\RouteHelper;
use Foodsharing\Helpers\EmailHelper;
use Foodsharing\Helpers\PageHelper;
use Foodsharing\Helpers\TranslationHelper;
use Foodsharing\Lib\Db\Db;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use ReflectionClass;

abstract class Control
{
	protected $isControl = false;
	protected $isXhrControl = false;
	/**
	 * @var Db
	 */
	protected $model;
	protected $view;
	private $sub;
	private $sub_func;

	/**
	 * @var PageHelper
	 */
	protected $pageHelper;

	/**
	 * @var Mem
	 */
	protected $mem;

	/**
	 * @var \Foodsharing\Lib\Session
	 */
	protected $session;

	/**
	 * @var Utils
	 */
	protected $v_utils;

	/**
	 * @var \Twig\Environment
	 */
	private $twig;

	/**
	 * @var Db
	 */
	private $legacyDb;

	/**
	 * @var FoodsaverGateway
	 */
	private $foodsaverGateway;

	/**
	 * @var InfluxMetrics
	 */
	private $metrics;

	/**
	 * @var EmailHelper
	 */
	protected $emailHelper;

	/**
	 * @var RouteHelper
	 */
	protected $routeHelper;

	/**
	 * @var TranslationHelper
	 */
	protected $translationHelper;

	/**
	 * @var FlashMessageHelper
	 */
	protected $flashMessageHelper;

	public function __construct()
	{
		global $container;
		$this->mem = $container->get(Mem::class);
		$this->session = $container->get(Session::class);
		$this->v_utils = $container->get(Utils::class);
		$this->legacyDb = $container->get(Db::class);
		$this->foodsaverGateway = $container->get(FoodsaverGateway::class);
		$this->metrics = $container->get(InfluxMetrics::class);
		$this->pageHelper = $container->get(PageHelper::class);
		$this->emailHelper = $container->get(EmailHelper::class);
		$this->routeHelper = $container->get(RouteHelper::class);
		$this->translationHelper = $container->get(TranslationHelper::class);
		$this->flashMessageHelper = $container->get(FlashMessageHelper::class);

		$reflection = new ReflectionClass($this);
		$dir = dirname($reflection->getFileName()) . DIRECTORY_SEPARATOR;
		$className = $reflection->getShortName();

		$this->sub = false;
		$this->sub_func = false;
		if (isset($_GET['sub'])) {
			$parts = explode('/', $_GET['sub']);
			foreach ($parts as $i => $p) {
				if (empty($p)) {
					unset($parts[$i]);
				}
			}
			$sub = $parts[0];
			$sub_func = end($parts);

			if (method_exists($this, $sub) && method_exists($this, $sub_func)) {
				$this->sub = $sub;
				$this->sub_func = $sub_func;
			}
		}

		if (($pos = strpos($className, 'Control')) !== false) {
			$this->isControl = true;
		} elseif (($pos = strpos($className, 'Xhr')) !== false) {
			$this->isXhrControl = true;
		}
		$moduleName = substr($className, 0, $pos);

		if (file_exists(ROOT_DIR . 'lang/DE/' . $moduleName . '.lang.php')) {
			require_once ROOT_DIR . 'lang/DE/' . $moduleName . '.lang.php';
		}
		if (isset($_GET['lang']) && $_GET['lang'] == 'en') {
			$fn = ROOT_DIR . 'lang/EN/' . $moduleName . '.lang.php';
			if (file_exists($fn)) {
				require_once $fn;
			}
		}
		if ($this->isControl) {
			$webpackModules = $dir . '../../../assets/modules.json';
			$manifest = json_decode(file_get_contents($webpackModules), true);
			$entry = 'Modules/' . $moduleName;
			if (isset($manifest[$entry])) {
				foreach ($manifest[$entry] as $asset) {
					if (substr($asset, -3) === '.js') {
						$this->pageHelper->addWebpackScript($asset);
					} elseif (substr($asset, -4) === '.css') {
						$this->pageHelper->addWebpackStylesheet($asset);
					}
				}
			}
		}
		$this->mem->updateActivity($this->session->id());
		$this->metrics->addPageStatData(['controller' => $className]);
	}

	/**
	 * @required
	 */
	public function setTwig(\Twig\Environment $twig)
	{
		$this->twig = $twig;
	}

	protected function render($template, $data)
	{
		$global = $this->pageHelper->generateAndGetGlobalViewData();
		$viewData = array_merge($global, $data);

		return $this->twig->render($template, $viewData);
	}

	public function setTemplate($template)
	{
		global $g_template;
		$g_template = $template;
	}

	public function getSubFunc()
	{
		return $this->sub_func;
	}

	public function getSub()
	{
		return $this->sub;
	}

	public function setSub($sub, $func = false)
	{
		if ($func === false) {
			$func = $sub;
		}
		$this->sub = $sub;
		$this->sub_func = $func;
	}

	public function getRequest($name)
	{
		if (isset($_REQUEST[$name])) {
			return $_REQUEST[$name];
		}

		return false;
	}

	public function wallposts($table, $id): string
	{
		$this->pageHelper->addJsFunc('
			function u_delPost(id, module, wallId)
				{
					var id = id;
					$.ajax({
						url: "/xhrapp.php?app=wallpost&m=delpost&table=' . $table . '&id=' . $id . '&post=" + id,
						dataType: "JSON",
						success: function(data)
						{
							if(data.status == 1)
							{
								$(".wallpost-" + id).remove();
							}
						}
					});
				}
				function mb_finishImage(file)
				{
					$("#wallpost-attach").append(\'<input type="hidden" name="attach[]" value="image-\'+file+\'" />\');
					$("#attach-preview div:last").remove();
					$(".attach-load").remove();
					$("#attach-preview").append(\'<a rel="wallpost-gallery" class="preview-thumb" href="/images/wallpost/\'+file+\'"><img src="/images/wallpost/thumb_\'+file+\'" height="60" /></a>\');
					$("#attach-preview").append(\'<div style="clear:both;"></div>\');
					$("#attach-preview a").fancybox();
					mb_clear();
				}
				function mb_clear()
				{
					$("#wallpost-loader").html(\'\');
					$("a.attach-load").remove();
				}
			');
		$this->pageHelper->addJs('
				$("#wallpost-text").autosize();
			$("#wallpost-text").on("focus", function(){
				$("#wallpost-submit").show();
			});

				$("#wallpost-attach-trigger").on("change", function(){
					$("#attach-preview div:last").remove();
					$("#attach-preview").append(\'<a rel="wallpost-gallery" class="preview-thumb attach-load" href="#" onclick="return false;">&nbsp;</a>\');
					$("#attach-preview").append(\'<div style="clear:both;"></div>\');
					$("#wallpost-attachimage-form").trigger("submit");
				});

			$("#wallpost-text").on("blur", function(){
				$("#wallpost-submit").show();
			});
			$("#wallpost-post").on("submit", function(ev){
				ev.preventDefault();

			});
			$("#wallpost-attach-image").button().on("click", function(){
				$("#wallpost-attach-trigger").trigger("click") ;
			});
				$("#wall-submit").button().on("click", function(ev){
					ev.preventDefault();
					if(($("#wallpost-text").val() != "" && $("#wallpost-text").val() != "' . $this->translationHelper->s('write_teaser') . '") || $("#attach-preview a").length > 0)
					{
						$(".wall-posts table tr:first").before(\'<tr><td colspan="2" class="load">&nbsp;</td></tr>\');

						attach = "";
						$("#wallpost-attach input").each(function(){
							attach = attach + ":" + $(this).val();
						});
						if(attach.length > 0)
						{
							attach = attach.substring(1);
						}

						text = $("#wallpost-text").val();
						if(text == "' . $this->translationHelper->s('write_teaser') . '")
						{
							text = "";
						}

						$.ajax({
						url: "/xhrapp.php?app=wallpost&m=post&table=' . $table . '&id=' . $id . '",
						type: "POST",
						data: {
								text: text,
							attach: attach
							},
						dataType: "JSON",
						success: function(data)
						{
							$("#wallpost-attach").html("");
							if(data.status == 1)
							{
								$(".wall-posts").html(data.html);
								$(".preview-thumb").fancybox();
								if(data.script != undefined)
								{
									$.globalEval(data.script);
								}
							}
						}
					});
					$("#wallpost-text").val("");
					$("#attach-preview").html("");
					$("#wallpost-attach").html("");
						$("#wallpost-text")[0].focus();
						$("#wallpost-text").css("height","33px");
				}
				});
			$("#wallpost-attach-trigger").on("focus", function(){
					$("#wall-submit")[0].focus();
				});
			$.ajax({
					url: "/xhrapp.php?app=wallpost&m=update&table=' . $table . '&id=' . $id . '&last=0",
					dataType: "JSON",
					success: function(data)
					{
						if(data.status == 1)
						{
							$(".wall-posts").html(data.html);
							$(".preview-thumb").fancybox();
						}
					}
			});

		');
		$posthtml = '';

		/* disable food basket comments during migration period (max. 3 weeks after release) until there are no pre existing baskets with comments left.
		 * #todo @jo remove this check and food basket comment section entirely afterwards
		 */
		if ($this->session->may() && $table != 'basket') {
			$posthtml = '
				<div class="tools ui-padding">
				<textarea id="wallpost-text" name="text" title="' . $this->translationHelper->s('write_teaser') . '" class="comment textarea inlabel"></textarea>
				<div id="attach-preview"></div>
				<div style="display:none;" id="wallpost-attach" /></div>

				<div id="wallpost-submit" align="right">

					<span id="wallpost-loader"></span><span id="wallpost-attach-image"><i class="far fa-image"></i> ' . $this->translationHelper->s('attach_image') . '</span>
					<a href="#" id="wall-submit">' . $this->translationHelper->s('send') . '</a>
					<div style="overflow:hidden;height:0;">
						<form id="wallpost-attachimage-form" action="/xhrapp.php?app=wallpost&m=attachimage&table=' . $table . '&id=' . $id . '" method="post" enctype="multipart/form-data" target="wallpost-frame">
							<input id="wallpost-attach-trigger" type="file" maxlength="100000" size="chars" name="etattach" />
						</form>
					</div>

				</div>
				<div style="clear:both"></div>
				<div style="visibility:hidden;">
				<iframe name="wallpost-frame" style="height:1px;" frameborder="0"></iframe>
				</div>
			</div>';
		}

		return '
		<div id="wallposts">
			' . $posthtml . '
			<div class="wall-posts">

			</div>
		</div>';
	}

	public function submitted(): bool
	{
		return isset($_POST) && !empty($_POST);
	}

	public function isSubmitted($form = false): bool
	{
		if (isset($_POST) && !empty($_POST)) {
			return $form === false || $_POST['submitted'] == $form;
		}

		return false;
	}

	public function getPostHtml($name)
	{
		if ($val = $this->getPost($name)) {
			$val = strip_tags($val, '<p><ul><li><ol><strong><span><i><div><h1><h2><h3><h4><h5><br><img><table><thead><tbody><th><td><tr><i><a>');
			$val = trim($val);
			if (!empty($val)) {
				return $val;
			}
		}

		return false;
	}

	public function getPostDate($name)
	{
		if ($date = $this->getPostString($name)) {
			$date = explode(' ', $date);
			$date = trim($date[0]);
			if (!empty($date)) {
				$date = explode('-', $date);
				if (count($date) == 3 && (int)$date[0] > 0 && (int)$date[1] > 0 && (int)$date[2] > 0) {
					return mktime(0, 0, 0, (int)$date[1], (int)$date[2], (int)$date[0]);
				}
			}
		}

		return false;
	}

	public function getPostTime($name)
	{
		if (isset($_POST[$name]['hour'], $_POST[$name]['min'])) {
			return array(
				'hour' => (int)$_POST[$name]['hour'],
				'min' => (int)$_POST[$name]['min']
			);
		}

		return false;
	}

	public function getPostString($name)
	{
		if ($val = $this->getPost($name)) {
			$val = strip_tags($val);
			$val = trim($val);

			if (!empty($val)) {
				return $val;
			}
		}

		return false;
	}

	public function getPostInt($name)
	{
		if ($val = $this->getPost($name)) {
			$val = trim($val);

			return (int)$val;
		}

		return false;
	}

	public function getPost($name)
	{
		if (isset($_POST[$name]) && !empty($_POST[$name])) {
			return $_POST[$name];
		}

		return false;
	}

	public function mailMessage($sender_id, $recip_id, $msg, $tpl_id = 'new_message')
	{
		$info = $this->legacyDb->getVal('infomail_message', 'foodsaver', $recip_id);
		if ((int)$info > 0) {
			if (!isset($_SESSION['lastMailMessage'])) {
				$_SESSION['lastMailMessage'] = array();
			}

			if (!$this->mem->userIsActive($recip_id)) {
				if (!isset($_SESSION['lastMailMessage'][$recip_id]) || (time() - $_SESSION['lastMailMessage'][$recip_id]) > 600) {
					$_SESSION['lastMailMessage'][$recip_id] = time();
					$foodsaver = $this->foodsaverGateway->getOne_foodsaver($recip_id);
					$sender = $this->foodsaverGateway->getOne_foodsaver($sender_id);

					$this->emailHelper->tplMail($tpl_id, $foodsaver['email'], array(
						'anrede' => $this->translationHelper->genderWord($foodsaver['geschlecht'], 'Lieber', 'Liebe', 'Liebe/r'),
						'sender' => $sender['name'],
						'name' => $foodsaver['name'],
						'message' => $msg,
						'link' => BASE_URL . '/?page=msg&u2c=' . (int)$sender_id
					));
				}
			}
		}
	}

	public function appout($data)
	{
		header('content-type: application/json; charset=utf-8');
		if (isset($_GET['callback']) && strlen($_GET['callback']) > 1) {
			echo strip_tags($_GET['callback']) . '(' . json_encode($data) . ');';
		} else {
			echo json_encode($data);
		}
		exit();
	}

	public function setContentWidth($left, $right)
	{
		global $content_left_width;
		global $content_right_width;
		$content_right_width = $right;
		$content_left_width = $left;
	}

	public function uri($index)
	{
		if (isset($_GET['uri'])) {
			$uri = explode('/', $_SERVER['REQUEST_URI']);
			if (isset($uri[$index])) {
				return $uri[$index];
			}
		}

		return false;
	}

	public function uriInt($index)
	{
		if (($val = (int)$this->uri($index)) !== false) {
			return $val;
		}

		return false;
	}

	public function uriStr($index)
	{
		if (($val = $this->uri($index)) !== false) {
			return preg_replace('/[^a-z0-9\-]/', '', $val);
		}

		return false;
	}
}
