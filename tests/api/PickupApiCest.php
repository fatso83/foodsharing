<?php

namespace Foodsharing\api;

use Carbon\Carbon;

class PickupApiCest
{
	private $user;
	private $store;
	private $region;

	public function _before(\ApiTester $I)
	{
		$this->user = $I->createFoodsaver();
		$this->region = $I->createRegion();
		$this->store = $I->createStore($this->region['id']);
		$I->addStoreTeam($this->store['id'], $this->user['id']);
	}

	public function acceptsDifferentIsoFormats(\ApiTester $I)
	{
		$I->login($this->user['email']);
		$pickupBaseDate = Carbon::now()->add('2 days');
		$pickupBaseDate->hours(13)->minutes(45)->seconds(0);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->format('Y-m-d\TH:i:s') . '+0000/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->seeResponseIsJson();
		$pickupBaseDate->minutes(50);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->format('Y-m-d\TH:i:s') . '+01:00/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->seeResponseIsJson();
		$pickupBaseDate->minutes(55);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->format('Y-m-d\TH:i:s') . '-01:00/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->seeResponseIsJson();
		$pickupBaseDate->minutes(35);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->format('Y-m-d\TH:i:s') . 'Z/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->seeResponseIsJson();
	}

	public function signupReturnsPickupConfirmationState(\ApiTester $I)
	{
		$I->login($this->user['email']);
		$pickupBaseDate = Carbon::now()->add('2 days');
		$pickupBaseDate->hours(14)->minutes(45)->seconds(0);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->toIso8601String() . '/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->canSeeResponseContainsJson([
			'confirmed' => false
		]);
		$coordinator = $I->createStoreCoordinator();
		$I->addStoreTeam($this->store['id'], $coordinator['id'], true, false, true);
	}

	public function signupAsCoordinarIsPreconfirmed(\ApiTester $I)
	{
		$pickupBaseDate = Carbon::now()->add('2 days');
		$pickupBaseDate->hours(16)->minutes(45)->seconds(0);
		$coordinator = $I->createStoreCoordinator();
		$I->addStoreTeam($this->store['id'], $coordinator['id'], true, false, true);
		$I->login($coordinator['email']);
		$I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
		$I->sendPOST('api/stores/' . $this->store['id'] . '/' . $pickupBaseDate->toIso8601String() . '/signup');
		$I->seeResponseIsJson();
		$I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
		$I->canSeeResponseContainsJson([
			'confirmed' => true
		]);
	}
}