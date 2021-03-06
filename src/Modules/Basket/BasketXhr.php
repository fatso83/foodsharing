<?php

namespace Foodsharing\Modules\Basket;

use Flourish\fImage;
use Foodsharing\Helpers\TimeHelper;
use Foodsharing\Lib\Db\Db;
use Foodsharing\Lib\Xhr\Xhr;
use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status;
use Foodsharing\Modules\Message\MessageModel;
use Foodsharing\Lib\Xhr\XhrResponses;
use Foodsharing\Services\ImageService;

class BasketXhr extends Control
{
	private $status;
	private $basketGateway;
	private $messageModel;
	private $timeHelper;
	private $imageService;

	public function __construct(
		Db $model,
		BasketView $view,
		BasketGateway $basketGateway,
		MessageModel $messageModel,
		TimeHelper $timeHelper,
		ImageService $imageService
	) {
		$this->model = $model;
		$this->messageModel = $messageModel;
		$this->view = $view;
		$this->basketGateway = $basketGateway;
		$this->timeHelper = $timeHelper;
		$this->imageService = $imageService;

		$this->status = [
			'ungelesen' => Status::REQUESTED_MESSAGE_UNREAD,
			'gelesen' => Status::REQUESTED_MESSAGE_READ,
			'abgeholt' => Status::DELETED_PICKED_UP,
			'abgelehnt' => Status::DENIED,
			'nicht_gekommen' => Status::NOT_PICKED_UP,
			'wall_follow' => Status::FOLLOWED,
			'angeklickt' => Status::REQUESTED,
		];

		parent::__construct();

		/*
		 * allowed method for not logged in users
		 */
		$allowed = [
			'bubble' => true,
			'login' => true,
			'basketCoordinates' => true,
			'nearbyBaskets' => true,
		];

		if (!isset($allowed[$_GET['m']]) && !$this->session->may()) {
			echo json_encode(
				[
					'status' => 1,
					'script' => 'pulseError("' . $this->translationHelper->s('not_login_hint') . '");',
				],
				JSON_THROW_ON_ERROR
			);
			exit();
		}
	}

	public function basketCoordinates(): void
	{
		$xhr = new Xhr();
		if ($baskets = $this->basketGateway->getBasketCoordinates()) {
			$xhr->addData('baskets', $baskets);
		}

		$xhr->send();
	}

	public function newBasket(): array
	{
		$dia = new XhrDialog();
		$dia->setTitle($this->translationHelper->s('basket_offer'));

		$dia->addContent($this->v_utils->v_info($this->translationHelper->s('basket_reference_info'), $this->translationHelper->s('basket_reference')));

		$dia->addPictureField('picture');

		$foodsaver = $this->model->getValues(['telefon', 'handy'], 'foodsaver', $this->session->id());

		$dia->addContent($this->view->basketForm($foodsaver));

		$dia->addJs(
			'
				
		$("#tel-wrapper").hide();
		$("#handy-wrapper").hide();
		
		$("input.input.cb-contact_type[value=\'2\']").on("change", function(){
			if(this.checked)
			{
				$("#tel-wrapper").show();
				$("#handy-wrapper").show();	
			}
			else
			{
				$("#tel-wrapper").hide();
				$("#handy-wrapper").hide();
			}
		});
				
		$(".cb-food_art[value=3]").on("click", function(){
			if(this.checked)
			{
				$(".cb-food_art[value=2]")[0].checked = true;
			}
		});
		'
		);

		$dia->noOverflow();

		$dia->addOpt('width', '90%');

		$dia->addButton(
			$this->translationHelper->s('basket_publish'),
			'ajreq(\'publish\',{appost:0,app:\'basket\',data:$(\'#' . $dia->getId(
			) . ' .input\').serialize(),description:$(\'#description\').val(),picture:$(\'#' . $dia->getId(
			) . '-picture-filename\').val(),weight:$(\'#weight\').val()});'
		);

		return $dia->xhrout();
	}

	public function publish(): array
	{
		$data = false;

		parse_str($_GET['data'], $data);

		$desc = strip_tags($data['description']);

		$desc = trim($desc);

		if (empty($desc)) {
			return [
				'status' => 1,
				'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error_desc') . '");',
			];
		}

		$pic = '';
		$weight = (float)$data['weight'];

		if (isset($data['filename'])) {
			$pic = $this->preparePicture($data['filename']);
		}

		$lat = 0;
		$lon = 0;

		$location_type = 0;

		if ($location_type == 0) {
			$fs = $this->model->getValues(['lat', 'lon'], 'foodsaver', $this->session->id());
			$lat = $fs['lat'];
			$lon = $fs['lon'];
		}

		if ($lat == 0 && $lon == 0) {
			return [
				'status' => 1,
				'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error_address') . '");',
			];
		}

		$contact_type = 1;
		$tel = [
			'tel' => '',
			'handy' => '',
		];

		if (isset($data['contact_type']) && is_array($data['contact_type'])) {
			$contact_type = implode(':', $data['contact_type']);
			if (in_array(2, $data['contact_type'])) {
				$tel = [
					'tel' => preg_replace('/[^0-9\-\/]/', '', $data['tel']),
					'handy' => preg_replace('/[^0-9\-\/]/', '', $data['handy']),
				];
			}
		}

		//fix lifetime between 1 and 21 days and convert from days to seconds
		$lifetime = (int)$data['lifetime'];
		if ($lifetime < 1 || $lifetime > 21) {
			$lifetime = 7;
		}
		$lifetime *= 60 * 60 * 24;

		if (!empty($desc) && ($id = $this->basketGateway->addBasket(
				$desc,
				$pic,
				$tel,
				$contact_type,
				$weight,
				$location_type,
				$lat,
				$lon,
				$lifetime,
				$this->session->user('bezirk_id'),
				$this->session->id()
			))) {
			if (isset($data['food_type']) && is_array($data['food_type'])) {
				$types = array();
				foreach ($data['food_type'] as $foodType) {
					if ((int)$foodType > 0) {
						$types[] = (int)$foodType;
					}
				}

				$this->basketGateway->addTypes($id, $types);
			}

			if (isset($data['food_art']) && is_array($data['food_art'])) {
				$kinds = array();
				foreach ($data['food_art'] as $foodKind) {
					if ((int)$foodKind > 0) {
						$kinds[] = (int)$foodKind;
					}
				}

				$this->basketGateway->addKind($id, $kinds);
			}

			return [
				'status' => 1,
				'script' => '
					pulseInfo("' . $this->translationHelper->s('basket_publish_thank_you') . '");
					basketStore.loadBaskets();
					$(".xhrDialog").dialog("close");
					$(".xhrDialog").dialog("destroy");
					$(".xhrDialog").remove();',
			];
		}

		return [
			'status' => 1,
			'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error') . '");',
		];
	}

	public function resizePic($pic): void
	{
		copy('tmp/' . $pic, 'images/basket/' . $pic);

		$img = new fImage('images/basket/' . $pic);
		$img->resize(800, 800);
		$img->saveChanges();

		copy('images/basket/' . $pic, 'images/basket/medium-' . $pic);

		$img = new fImage('images/basket/medium-' . $pic);
		$img->resize(450, 450);
		$img->saveChanges();

		copy('images/basket/medium-' . $pic, 'images/basket/thumb-' . $pic);

		$img = new fImage('images/basket/thumb-' . $pic);
		$img->cropToRatio(1, 1);
		$img->resize(200, 200);
		$img->saveChanges();

		copy('images/basket/thumb-' . $pic, 'images/basket/75x75-' . $pic);

		$img = new fImage('images/basket/75x75-' . $pic);
		$img->cropToRatio(1, 1);
		$img->resize(75, 75);
		$img->saveChanges();

		copy('images/basket/75x75-' . $pic, 'images/basket/50x50-' . $pic);

		$img = new fImage('images/basket/50x50-' . $pic);
		$img->cropToRatio(1, 1);
		$img->resize(50, 50);
		$img->saveChanges();
	}

	public function nearbyBaskets(): void
	{
		$xhr = new Xhr();

		if (isset($_GET['coordinates']) && $basket = $this->basketGateway->listNearbyBasketsByDistance(
				$this->session->id(),
				[
					'lat' => $_GET['coordinates'][0],
					'lon' => $_GET['coordinates'][1],
				]
			)) {
			$xhr->addData('baskets', $basket);
		}

		$xhr->send();
	}

	public function bubble()
	{
		if ($basket = $this->basketGateway->getBasket($_GET['id'])) {
			if ($basket['fsf_id'] == 0) {
				$dia = new XhrDialog();

				/*
				 * What see the user if not logged in?
				 */
				if (!$this->session->may()) {
					$dia->setTitle($this->translationHelper->s('basket'));
					$dia->addContent($this->view->bubbleNoUser($basket));
				} else {
					$dia->setTitle($this->translationHelper->sv('basket_foodsaver', array('name' => $basket['fs_name'])));
					$dia->addContent($this->view->bubble($basket));
				}

				$dia->addButton($this->translationHelper->s('to_basket'), 'goTo(\'/essenskoerbe/' . (int)$basket['id'] . '\');');

				$modal = false;
				if (isset($_GET['modal'])) {
					$modal = true;
				}
				$dia->addOpt('modal', 'false', $modal);
				$dia->addOpt('resizeable', 'false', false);

				$dia->noOverflow();

				return $dia->xhrout();
			}

			return $this->fsBubble($basket);
		}

		return [
			'status' => 1,
			'script' => 'pulseError("' . $this->translationHelper->s('basket_error') . '");',
		];
	}

	public function fsBubble($basket): array
	{
		$dia = new XhrDialog();

		$dia->setTitle('Essenskorb von ' . BASE_URL);

		$dia->addContent($this->view->fsBubble($basket));
		$modal = false;
		if (isset($_GET['modal'])) {
			$modal = true;
		}
		$dia->addOpt('modal', 'false', $modal);
		$dia->addOpt('resizeable', 'false', false);

		$dia->addOpt('width', 400);
		$dia->noOverflow();

		$dia->addJs('$(".fsbutton").button();');

		return $dia->xhrout();
	}

	public function request()
	{
		if ($basket = $this->basketGateway->getBasket($_GET['id'])) {
			$this->basketGateway->setStatus($_GET['id'], Status::REQUESTED, $this->session->id());
			$dia = new XhrDialog();
			$dia->setTitle($this->translationHelper->sv('basket_foodsaver', array('name' => $basket['fs_name'])));
			$dia->addOpt('width', '90%');
			$dia->noOverflow();
			$dia->addContent($this->view->contactTitle($basket));

			$contact_type = [1];

			if (!empty($basket['contact_type'])) {
				$contact_type = explode(':', $basket['contact_type']);
			}

			if (in_array(2, $contact_type)) {
				$dia->addContent($this->view->contactNumber($basket));
			}
			if (in_array(1, $contact_type)) {
				$dia->addContent($this->view->contactMsg());
				$dia->addButton(
					$this->translationHelper->s('send_request'),
					'ajreq(\'sendreqmessage\',{appost:0,app:\'basket\',id:' . (int)$_GET['id'] . ',msg:$(\'#contactmessage\').val()});'
				);
			}

			return $dia->xhrout();
		}
	}

	public function sendreqmessage(): array
	{
		if ($fs_id = $this->model->getVal('foodsaver_id', 'basket', $_GET['id'])) {
			$msg = strip_tags($_GET['msg']);
			$msg = trim($msg);
			if (!empty($msg)) {
				$this->messageModel->message($fs_id, $msg);
				$this->mailMessage($this->session->id(), $fs_id, $msg, 'basket/request');
				$this->basketGateway->setStatus($_GET['id'], Status::REQUESTED_MESSAGE_UNREAD, $this->session->id());

				return [
					'status' => 1,
					'script' => '
						if($(".xhrDialog").length > 0){
							$(".xhrDialog").dialog("close");
						}
						pulseInfo("' . $this->translationHelper->s('sent_request') . '");',
				];
			}

			return [
				'status' => 1,
				'script' => 'pulseError("' . $this->translationHelper->s('basket_error_message') . '");',
			];
		}

		return [
			'status' => 1,
			'script' => 'pulseError("' . $this->translationHelper->s('error_default') . '");',
		];
	}

	public function infobar(): void
	{
		// TODO: rewrite this to an proper API endpoint
		// and update /client/src/api/baskets.js
		$this->session->noWrite();

		$xhr = new Xhr();

		$updates = $this->basketGateway->listUpdates($this->session->id());
		$baskets = $this->basketGateway->listMyBaskets($this->session->id());

		$xhr->addData('baskets', array_map(function ($b) use ($updates) {
			$basket = [
				'id' => (int)$b['id'],
				'description' => html_entity_decode($b['description']),
				'createdAt' => date('Y-m-d\TH:i:s', $b['time_ts']),
				'updatedAt' => date('Y-m-d\TH:i:s', $b['time_ts']),
				'requests' => []
			];
			foreach ($updates as $update) {
				if ((int)$update['id'] == $basket['id']) {
					$time = date('Y-m-d\TH:i:s', $update['time_ts']);
					$basket['requests'][] = [
						'user' => [
							'id' => (int)$update['fs_id'],
							'name' => $update['fs_name'],
							'avatar' => $update['fs_photo'],
							'sleepStatus' => $update['sleep_status'],
						],
						'description' => $update['description'],
						'time' => $time,
					];
					if (strcmp($time, $basket['updatedAt']) > 0) {
						$basket['updatedAt'] = $time;
					}
				}
			}

			return $basket;
		}, $baskets));

		$xhr->send();
	}

	public function answer()
	{
		$basketId = (int)$_GET['id'];
		$fsId = (int)$_GET['fid'];

		if (($id = $this->model->getVal('foodsaver_id', 'basket', $basketId)) && $id == $this->session->id()) {
			$this->basketGateway->setStatus($basketId, Status::REQUESTED_MESSAGE_READ, $fsId);

			return [
				'status' => 1,
				'script' => 'chat(' . $fsId . ');basketStore.loadBaskets();',
			];
		}
	}

	public function removeRequest()
	{
		if ($request = $this->basketGateway->getRequest($_GET['id'], $_GET['fid'], $this->session->id())) {
			$dia = new XhrDialog();
			$dia->addOpt('width', '400');
			$dia->noOverflow();
			$dia->setTitle($this->translationHelper->sv('basket_foodsaver_close', array('name' => $request['fs_name'])));
			$gender = $this->translationHelper->genderWord(
				$request['fs_gender'],
				'er',
				'sie',
				'er/sie'
			);
			$dia->addContent(
				'<div>
					<img src="' . $this->imageService->img($request['fs_photo']) . '" style="float:left;margin-right:10px;">
					<p>Anfragezeitpunkt: ' . $this->timeHelper->niceDate($request['time_ts']) . '</p>
					<div style="clear:both;"></div>
				</div>'
				. $this->v_utils->v_form_radio(
					'fetchstate',
					[
						'values' => [
							[
								'id' => Status::DELETED_PICKED_UP,
								'name' => $this->translationHelper->sv('basket_deleted_picked_up', array('gender' => $gender)),
							],
							[
								'id' => Status::NOT_PICKED_UP,
								'name' => $this->translationHelper->sv('basket_not_picked_up', array('gender' => $gender)),
							],
							[
								'id' => Status::DELETED_OTHER_REASON,
								'name' => $this->translationHelper->s('basket_deleted_other_reason'),
							],
						],
						'selected' => Status::DELETED_PICKED_UP,
					]
				)
			);
			$dia->addAbortButton();
			$dia->addButton(
				$this->translationHelper->s('continue'),
				'ajreq(\'finishRequest\',{app:\'basket\',id:' . (int)$_GET['id'] . ',fid:' . (int)$_GET['fid'] . ',sk:$(\'#fetchstate-wrapper input:checked\').val()});'
			);

			return $dia->xhrout();
		}
	}

	public function removeBasket(): array
	{
		$this->basketGateway->removeBasket($_GET['id'], $this->session->id());

		return [
			'status' => 1,
			'script' => 'basketStore.loadBaskets();pulseInfo("' . $this->translationHelper->s('basket_not_active') . '");',
		];
	}

	public function editBasket()
	{
		$basket = $this->basketGateway->getBasket($_GET['id']);

		if ($basket['fs_id'] !== $this->session->id()) {
			return XhrResponses::PERMISSION_DENIED;
		}

		$dia = new XhrDialog();
		$dia->setTitle($this->translationHelper->s('basket_edit'));

		$dia->addPictureField('picture');

		$dia->addContent($this->view->basketEditForm($basket));

		$dia->addOpt('width', '90%');
		$dia->noOverflow();

		$dia->addButton(
			$this->translationHelper->s('basket_publish'),
			'ajreq(\'publishEdit\',{appost:0,app:\'basket\',data:$(\'#' . $dia->getId(
			) . ' .input\').serialize(),description:$(\'#description\').val(),picture:$(\'#' . $dia->getId(
			) . '-picture-filename\').val(),basket_id:$(\'#basket_id\').val()});'
		);

		return $dia->xhrout();
	}

	public function publishEdit(): array
	{
		$data = false;

		parse_str($_GET['data'], $data);

		$id = strip_tags($_GET['basket_id']);
		if (empty($id)) {
			return [
				'status' => 1,
				'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error') . '");',
			];
		}

		$basket = $this->basketGateway->getBasket($id);
		if ($basket['fs_id'] != $this->session->id()) {
			return [
				'status' => 1,
				'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error_permission') . '");',
			];
		}

		$desc = strip_tags($data['description']);
		$desc = trim($desc);
		if (empty($desc)) {
			return [
				'status' => 1,
				'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error_desc') . '");',
			];
		}

		$pic = $basket['picture'];
		if (isset($data['filename']) && !empty($data['filename'])) {
			$pic = $this->preparePicture($data['filename']);
		}

		if (!empty($desc) && !empty($id) &&
			($this->basketGateway->editBasket($id, $desc, $pic, $basket['lat'], $basket['lon'], $this->session->id()))) {
			return [
				'status' => 1,
				'script' => '
					pulseInfo("' . $this->translationHelper->s('basket_publish_thank_you') . '");
					basketStore.loadBaskets();
					$(".xhrDialog").dialog("close");
					$(".xhrDialog").dialog("destroy");
					$(".xhrDialog").remove();
					window.reload()',
			];
		}

		return [
			'status' => 1,
			'script' => 'pulseInfo("' . $this->translationHelper->s('basket_publish_error') . '");',
		];
	}

	public function finishRequest()
	{
		if (isset($_GET['sk']) && (int)$_GET['sk'] > 0 && $this->basketGateway->getRequest(
				$_GET['id'],
				$_GET['fid'],
				$this->session->id()
			)) {
			$this->basketGateway->setStatus($_GET['id'], $_GET['sk'], $_GET['fid']);

			return [
				'status' => 1,
				'script' => '
						pulseInfo("' . $this->translationHelper->s('finish_request') . '");
						$(".xhrDialog").dialog("close");
						$(".xhrDialog").dialog("destroy");
						$(".xhrDialog").remove();
						',
			];
		}

		return [
			'status' => 1,
			'script' => 'pulseError("' . $this->translationHelper->s('error_default') . '");',
		];
	}

	private function preparePicture(string $filename): string
	{
		$pic = preg_replace('/[^a-z0-9\.]/', '', $filename);
		if (!empty($pic) && file_exists('tmp/' . $pic)) {
			$this->resizePic($pic);
		}

		return $pic;
	}
}
