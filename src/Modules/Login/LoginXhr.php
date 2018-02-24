<?php

namespace Foodsharing\Modules\Login;

use Exception;
use Flourish\fImage;
use Flourish\fUpload;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session\S;
use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Services\SearchService;

class LoginXhr extends Control
{
	private $searchService;

	public function __construct(LoginModel $model, LoginView $view, SearchService $searchService)
	{
		$this->model = $model;
		$this->view = $view;
		$this->searchService = $searchService;

		parent::__construct();
	}

	/**
	 * Method to add some user specific vars to the memcache for more performance and less db access.
	 */
	private function fillMemcacheUserVars()
	{
		$info = $this->model->getVal('infomail_message', 'foodsaver', $this->func->fsId());

		if ((int)$info > 0) {
			Mem::userSet($this->func->fsId(), 'infomail', true);
		} else {
			Mem::userSet($this->func->fsId(), 'infomail', false);
		}
	}

	public function login()
	{
		if (!S::may()) {
			$dia = new XhrDialog();

			$dia->setTitle($this->func->s('login'));

			$dia->addContent($this->view->loginForm());

			$dia->addButton('Registrieren', 'ajreq(\'join\',{app:\'login\',e:$(\'#email_adress\').val(),p:$(\'#password\').val()});');
			$dia->addButton('Einloggen', "ajreq('loginsubmit',{app:'login',u:$('#email_adress').val(),p:$('#password').val()});");

			$dia->addJs('
				$("#forgotpasswordlink").focus(function(){
					$(".ui-dialog-buttonpane button:last")[0].focus();
				});
				$("#password").keydown(function(ev){
					if(ev.which == 13)
					{
						ajreq("loginsubmit",{app:"login",u:$("#email_adress").val(),p:$("#password").val()});
					}
				});
			');

			return $dia->xhrout();
		}
	}

	public function loginsubmit()
	{
		if ($this->model->login($_GET['u'], $_GET['p'])) {
			$token_js = '';
			if ($token = $this->searchService->writeSearchIndexToDisk(S::id(), S::user('token'))) {
				$token_js = 'user.token = "' . $token . '";';
			}

			$this->fillMemcacheUserVars();

			$menu = $this->func->getMenu();
			$msgbar = $this->v_utils->v_msgBar();

			return array(
				'status' => 1,
				'script' => '
					' . $token_js . '
					pulseSuccess("' . $this->func->s('login_success') . '");
					reload();'
			);
		} else {
			return array(
				'status' => 1,
				'script' => 'pulseError("' . $this->func->s('login_failed') . '");'
			);
		}
	}

	/**
	 * here arrives the photo what user cann upload in the quick join form.
	 */
	public function photoupload()
	{
		try {
			$uploader = new fUpload();
			$uploader->setMIMETypes(
				array(
					'image/gif',
					'image/jpeg',
					'image/pjpeg',
					'image/png'
				),
				$this->func->s('upload_no_image')
			);
			$uploader->setMaxSize('5MB');

			if (($error = $uploader->validate('photo', true)) !== null) {
				$func = 'parent.join.photoUploadError(\'' . $error . '\');';
			} else {
				// move the uploaded file in a temp folder
				$image = $uploader->move(ROOT_DIR . 'tmp/', 'photo');

				// generate an unique name for the photo
				$name = uniqid() . '.' . strtolower($image->getExtension());

				$image->rename($name, true);

				$image = new fImage(ROOT_DIR . 'tmp/' . $name);

				$image->resize(800, 0);
				$image->saveChanges();

				$func = 'parent.join.readyUpload(\'' . $name . '\');';
			}
		} catch (Exception $e) {
			$func = 'parent.join.photoUploadError(\'' . $this->func->s('error_image') . '\');';
		}

		echo '<html>
<head><title>Upload</title></head><body onload="' . $func . '"></body>
</html>';
		exit();
	}

	/**
	 * execute the registation process.
	 */
	public function joinsubmit()
	{
		$data = $this->joinValidate($_POST);
		if (!is_array($data)) {
			echo json_encode(array(
				'status' => 0,
				'error' => $data
			));
			exit();
		} else {
			$token = uniqid('', true);
			if ($id = $this->model->insertNewUser($data, $token)) {
				$activationUrl = BASE_URL . '/?page=login&sub=activate&e=' . urlencode($data['email']) . '&t=' . urlencode($token);

				$this->func->tplMail(25, $data['email'], array(
					'name' => $data['name'],
					'link' => $activationUrl,
					'anrede' => $this->func->s('anrede_' . $data['gender'])
				));

				echo json_encode(array(
					'status' => 1
				));
				exit();
			}
		}

		echo json_encode(array(
			'status' => 0,
			'error' => $this->func->s('error')
		));
		exit();
	}

	/**
	 * validates the xhr request.
	 *
	 * @param array $data
	 *
	 * @return array || string error
	 */
	private function joinValidate($data)
	{
		/*
		 [iam] => org
		[name] => Peter
		[email] => peter@pan.de
		[pw] => 12345
		[avatar] => 5427fb55f3a5d.jpg
		[phone] => 02261889971
		[lat] => 48.0649838
		[lon] => 7.885475300000053
		[str] => Bauerngasse
		[nr] => 6
		[plz] => 79211
		[country] => DE
		*/

		$check = true;

		$data['type'] = 0;

		if ($data['iam'] == 'org') {
			$data['type'] = 1;
		}

		if ($data['avatar'] != '') {
			$data['avatar'] = $this->resizeAvatar($data['avatar']);
		}

		$data['name'] = strip_tags($data['name']);
		$data['name'] = trim($data['name']);

		$data['surname'] = strip_tags($data['surname']);
		$data['surname'] = trim($data['surname']);

		if ($data['name'] == '') {
			return $this->func->s('error_name');
		}

		if (!$this->func->validEmail($data['email'])) {
			return $this->func->s('error_email');
		}

		if ($this->model->emailExists($data['email'])) {
			return $this->func->s('email_exists');
		}

		if (strlen($data['pw']) < 5 && strlen($data['pw']) > 30) {
			return $this->func->s('error_password');
		}

		$data['gender'] = (int)$data['gender'];

		if ($data['gender'] > 2 || $data['gender'] < 0) {
			$data['gender'] = 0;
		}
		$birthdate = \DateTime::createFromFormat('Y-m-d', $data['birthdate']);
		$min_birthdate = new \DateTime();
		$min_birthdate->modify('-18 years');
		if (!$birthdate || $birthdate > $min_birthdate) {
			return $this->func->s('error_birthdate');
		}
		$data['birthdate'] = $birthdate->format('Y-m-d');
		$data['mobile_phone'] = strip_tags($data['mobile_phone']);
		$data['lat'] = floatval($data['lat']);
		$data['lon'] = floatval($data['lon']);
		$data['str'] = strip_tags($data['str']);
		$data['plz'] = preg_replace('[^0-9]', '', $data['plz']) . '';
		$data['city'] = strip_tags($data['city']);
		$data['city'] = trim($data['city']);
		$data['country'] = strip_tags($data['country']);
		$data['country'] = strtolower($data['country']);
		$data['country'] = trim($data['country']);

		$data['newsletter'] = (int)$data['newsletter'];
		if (!in_array($data['newsletter'], array(0, 1), true)) {
			$data['newsletter'] = 0;
		}

		return $data;
	}

	/**
	 * Fancy ajax registration formular.
	 */
	public function join()
	{
		if (!S::may()) {
			$dia = new XhrDialog();

			$dia->setTitle($this->func->s('join'));

			$email = '';
			$pass = '';
			if (isset($_GET['p']) && isset($_GET['e'])) {
				if ($this->func->validEmail($_GET['e'])) {
					$email = strip_tags($_GET['e']);
				}
				$pass = strip_tags($_GET['p']);
			}

			$datenschutz = $this->model->getContent(28);
			$rechtsvereinbarung = $this->model->getContent(29);

			$rechtsvereinbarung['body'] = strip_tags(str_replace(array('<br>', '<br />', '<p>', '</p>'), "\n", $rechtsvereinbarung['body']));
			$datenschutz['body'] = strip_tags(str_replace(array('<br>', '<br />', '<p>', '</p>'), "\n", $datenschutz['body']));

			$dia->addContent($this->view->join($email, $pass, $datenschutz, $rechtsvereinbarung));
			$dia->addOpt('height', 420);
			$dia->addOpt('width', 700);

			$dia->setResizeable(false);

			$dia->addJsBefore('

				var date = new Date();
				$("<link>").attr("rel","stylesheet").attr("type","text/css").attr("href","/fonts/octicons/octicons.css").appendTo("head");
				$("<link>").attr("rel","stylesheet").attr("type","text/css").attr("href","/css/join.css?" + date.getTime()).appendTo("head");
			');

			$dia->addJsAfter('
				( typeof L !== "undefined" ? $.Deferred().resolve() : $.getScript( "/js/leaflet/leaflet.js" ) )
				.then( function() {
					return typeof join !== "undefined" ? $.Deferred().resolve() : $.getScript( "/js/join.js" );
				} ).then( function() {
					join.init( "' . GOOGLE_API_KEY . '" );
				} );
			');

			return $dia->xhrout();
		}
	}

	private function resizeAvatar($img)
	{
		$folder = ROOT_DIR . 'tmp/';
		if (file_exists($folder . $img)) {
			$image = new fImage($folder . $img);

			try {
				$folder = ROOT_DIR . 'images/';

				$image->move($folder, false);
				// make 35x35
				copy($folder . $img, $folder . 'mini_q_' . $img);
				$image = new fImage($folder . 'mini_q_' . $img);
				$image->cropToRatio(1, 1);
				$image->resize(35, 35);
				$image->saveChanges();

				// make 75x75
				copy($folder . $img, $folder . 'med_q_' . $img);
				$image = new fImage($folder . 'med_q_' . $img);
				$image->cropToRatio(1, 1);
				$image->resize(75, 75);
				$image->saveChanges();

				// make 50x50
				copy($folder . $img, $folder . '50_q_' . $img);
				$image = new fImage($folder . '50_q_' . $img);
				$image->cropToRatio(1, 1);
				$image->resize(75, 75);
				$image->saveChanges();

				// make 130x130
				copy($folder . $img, $folder . '130_q_' . $img);
				$image = new fImage($folder . '130_q_' . $img);
				$image->cropToRatio(1, 1);
				$image->resize(130, 130);
				$image->saveChanges();

				// make 150x150
				copy($folder . $img, $folder . 'q_' . $img);
				$image = new fImage($folder . 'q_' . $img);
				$image->cropToRatio(1, 1);
				$image->resize(150, 150);
				$image->saveChanges();

				return $img;
			} catch (Exception $e) {
				$this->func->info('Dein Foto konnte nicht gespeichert werden');

				return '';
			}
		}

		return '';
	}

	private function validate_phone_number($phone)
	{
		/*********************************************************************/
		/*   Purpose:   To determine if the passed string is a valid phone  */
		/*              number following one of the establish formatting        */
		/*                  styles for phone numbers.  This function also breaks    */
		/*                  a valid number into it's respective components of:      */
		/*                          3-digit area code,                                      */
		/*                          3-digit exchange code,                                  */
		/*                          4-digit subscriber number                               */
		/*                  and validates the number against 10 digit US NANPA  */
		/*                  guidelines.                                                         */
		/*********************************************************************/
		$format_pattern = '/^(?:(?:\((?=\d{3}\)))?(\d{3})(?:(?<=\(\d{3})\))' .
			'?[\s.\/-]?)?(\d{3})[\s\.\/-]?(\d{4})\s?(?:(?:(?:' .
			'(?:e|x|ex|ext)\.?\:?|extension\:?)\s?)(?=\d+)' .
			'(\d+))?$/';
		$nanpa_pattern = '/^(?:1)?(?(?!(37|96))[2-9][0-8][0-9](?<!(11)))?' .
			'[2-9][0-9]{2}(?<!(11))[0-9]{4}(?<!(555(01([0-9]' .
			'[0-9])|1212)))$/';

		// Init array of variables to false
		$valid = array('format' => false,
			'nanpa' => false,
			'ext' => false,
			'all' => false);

		//Check data against the format analyzer
		if (preg_match($format_pattern, $phone, $matchset)) {
			$valid['format'] = true;
		}

		//If formatted properly, continue
		//if($valid['format']) {
		if (!$valid['format']) {
			return false;
		} else {
			//Set array of new components
			$components = array('ac' => $matchset[1], //area code
				'xc' => $matchset[2], //exchange code
				'sn' => $matchset[3] //subscriber number
			);
			//              $components =   array ( 'ac' => $matchset[1], //area code
			//                                              'xc' => $matchset[2], //exchange code
			//                                              'sn' => $matchset[3], //subscriber number
			//                                              'xn' => $matchset[4] //extension number
			//                                              );

			//Set array of number variants
			$numbers = array('original' => $matchset[0],
				'stripped' => substr(preg_replace('[\D]', '', $matchset[0]), 0, 10)
			);

			//Now let's check the first ten digits against NANPA standards
			if (preg_match($nanpa_pattern, $numbers['stripped'])) {
				$valid['nanpa'] = true;
			}

			//If the NANPA guidelines have been met, continue
			if ($valid['nanpa']) {
				if (!empty($components['xn'])) {
					if (preg_match('/^[\d]{1,6}$/', $components['xn'])) {
						$valid['ext'] = true;
					}   // end if if preg_match
				} else {
					$valid['ext'] = true;
				}   // end if if  !empty
			}   // end if $valid nanpa

			//If the extension number is valid or non-existent, continue
			if ($valid['ext']) {
				$valid['all'] = true;
			}   // end if $valid ext
		}   // end if $valid
		return $valid['all'];
	}
}
