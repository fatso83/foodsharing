<?php

namespace Foodsharing\Controller;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Mobile_Detect;

class UserRestController extends AbstractFOSRestController
{
	private $session;
	private $loginGateway;
	private $foodsaverGateway;

	public function __construct(Session $session, LoginGateway $loginGateway, FoodsaverGateway $foodsaverGateway)
	{
		$this->session = $session;
		$this->loginGateway = $loginGateway;
		$this->foodsaverGateway = $foodsaverGateway;
	}

	/**
	 * @Rest\Get("user/current")
	 */
	public function userAction(): Response
	{
		if (!$this->session->may()) {
			throw new HttpException(404);
		}

		return $this->handleUserView();
	}

	/**
	 * @Rest\Post("user/login")
	 * @Rest\RequestParam(name="email")
	 * @Rest\RequestParam(name="password")
	 * @Rest\RequestParam(name="remember_me", default=false)
	 */
	public function loginAction(ParamFetcher $paramFetcher): Response
	{
		$email = $paramFetcher->get('email');
		$password = $paramFetcher->get('password');
		$rememberMe = (bool)$paramFetcher->get('remember_me');
		$fs_id = $this->loginGateway->login($email, $password);
		if ($fs_id) {
			$this->session->login($fs_id, $rememberMe);

			$mobdet = new Mobile_Detect();
			if ($mobdet->isMobile()) {
				$_SESSION['mob'] = 1;
			}

			return $this->handleUserView();
		}

		throw new HttpException(401, 'email or password are invalid');
	}

	/**
	 * @Rest\Delete("user/{userId}", requirements={"userId" = "\d+"})
	 */
	public function deleteUserAction(int $userId): Response
	{
		if ($userId !== $this->session->id() && !$this->session->may('orga')) {
			throw new HttpException(403);
		}

		if ($userId === $this->session->id()) {
			$this->session->logout();
		}
		$this->foodsaverGateway->del_foodsaver($userId);

		return $this->handleView($this->view());
	}

	private function handleUserView(): Response
	{
		$user = $this->session->get('user');

		return $this->handleView($this->view([
			'id' => $this->session->id(),
			'name' => $user['name']
		], 200));
	}
}
