<?php

namespace Foodsharing\Modules\Login;

use Foodsharing\Lib\View\vMap;
use Foodsharing\Modules\Core\View;

class LoginView extends View
{
	public function join($email = '', $pass = '', $datenschutz, $rechtsvereinbarung)
	{
		$map = new vMap();
		$map->setSearchPanel('login_location');
		$params = array(
			'date_min' => date('Y-m-d', strtotime('-120 years')),
			'date_max' => date('Y-m-d', strtotime('-18 years')),
			'datenschutz' => $datenschutz,
			'rechtsvereinbarung' => $rechtsvereinbarung,
			'pass' => $pass,
			'email' => $email,
			'map' => $map->render()
		);

		return $this->twig->render('pages/Register/RegisterForm.twig', $params);
	}

	public function passwordRequest()
	{
		if (!$this->session->may()) {
			$params = array(
				'email' => $this->translationHelper->s('login_email'),
				'action' => $_SERVER['REQUEST_URI']
			);

			return $this->twig->render('pages/ForgotPassword/ForgotPasswordForm.twig', $params);
		}
	}

	public function newPasswordForm($key)
	{
		$key = preg_replace('/[^0-9a-zA-Z]/', '', $key);
		$cnt = $this->v_utils->v_info('Jetzt kannst Du Dein Passwort ändern.');
		$cnt .= '
			<form name="newPass" method="post" class="contact-form">
				<input type="hidden" name="k" value="' . $key . '" />
				' . $this->v_utils->v_form_passwd('pass1') . '
				' . $this->v_utils->v_form_passwd('pass2') . '
				' . $this->v_utils->v_form_submit($this->translationHelper->s('save'), 'submitted') . '
			</form>';

		return $this->v_utils->v_field($cnt, 'Neues Passwort setzen', array('class' => 'ui-padding'));
	}

	public function success($msg, $title = false)
	{
		$t = '';
		if ($title !== false) {
			$t = '<strong>' . $title . '</strong> ';
		}
		$_SESSION['msg']['success'][] = $t . $msg;
	}
}
