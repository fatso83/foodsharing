<?php

class BellGatewayTest extends \Codeception\Test\Unit
{
	/**
	 * @var \UnitTester
	 */
	protected $tester;

	/**
	 * @var \Foodsharing\Modules\Bell\BellGateway
	 */
	private $gateway;

	/**
	 * @var Faker\Generator
	 */
	private $faker;

	protected function _before()
	{
		$this->gateway = $this->tester->get(\Foodsharing\Modules\Bell\BellGateway::class);
		$this->faker = Faker\Factory::create('de_DE');
	}

	public function testAddBell()
	{
		$user1 = $this->tester->createFoodsaver();
		$user2 = $this->tester->createFoodsaver();
		/* addBell accepts different inputs: $id, [$id, $id], [['id' => $id]] */
		$title = 'title';
		$body = $this->faker->text(50);
		$this->gateway->addBell([$user1, $user2], $title, $body, '', [''], [], '', 1);
		$bid = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $title, 'body' => $body]);
		$this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bid, 'seen' => 0]);
		$this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bid, 'seen' => 0]);
		$title = 'title_zwei';
		$this->gateway->addBell([$user1, $user2], $title, $body, '', [''], [], '', 0);
		$bid = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $title, 'body' => $body]);
		$this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bid, 'seen' => 0]);
		$this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bid, 'seen' => 0]);
	}

	public function testRemoveBellWorksIfIdentifierIsCorrect()
	{
		$this->tester->clearTable('fs_bell');
		$this->tester->clearTable('fs_foodsaver_has_bell');

		$user1 = $this->tester->createFoodsaver();
		$user2 = $this->tester->createFoodsaver();

		$this->tester->addBells([$user1, $user2], ['identifier' => 'my-custom-identifier']);
		$this->tester->seeNumRecords(1, 'fs_bell');
		$this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');

		$this->gateway->delBellsByIdentifier('my-custom-identifier');

		$this->tester->seeNumRecords(0, 'fs_bell');
		$this->tester->seeNumRecords(0, 'fs_foodsaver_has_bell');
	}

	public function testRemoveBellDoesNotWorkIfIdentifierIsIncorrect()
	{
		$this->tester->clearTable('fs_bell');
		$this->tester->clearTable('fs_foodsaver_has_bell');

		$user1 = $this->tester->createFoodsaver();
		$user2 = $this->tester->createFoodsaver();

		$this->tester->addBells([$user1, $user2], ['identifier' => 'my-custom-identifier']);
		$this->tester->seeNumRecords(1, 'fs_bell');
		$this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');

		$this->gateway->delBellsByIdentifier('my-custom-wrong-identifier');

		$this->tester->seeNumRecords(1, 'fs_bell');
		$this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');
	}

	public function testGetOneByIdentifier()
	{
		$this->tester->clearTable('fs_bell');

		$user1 = $this->tester->createFoodsaver();
		$user2 = $this->tester->createFoodsaver();

		$identifier = 'my-custom-identifier';

		$this->tester->addBells([$user1, $user2], ['identifier' => $identifier]);

		$bellId = $this->gateway->getOneByIdentifier($identifier);

		$this->tester->seeInDatabase('fs_bell', ['id' => $bellId, 'identifier' => $identifier]);
	}

	public function testUpdateBell()
	{
		$this->tester->clearTable('fs_bell');

		$user1 = $this->tester->createFoodsaver();
		$user2 = $this->tester->createFoodsaver();

		$title = 'title';
		$body = $this->faker->text(50);
		$icon = 'some-icon';
		$identifier = 'some-identifier';
		$closable = 0;

		$this->gateway->addBell([$user1, $user2], $title, $body, $icon, [], [], $identifier, $closable);
		$bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $title, 'body' => $body]);

		$updatedData = [
			'name' => 'updated title',
			'body' => $this->faker->text(50),
			'icon' => 'some-updated-icon',
			'identifier' => 'some-updated-identifier',
			'closeable' => 1
		];

		$this->gateway->updateBell($bellId, $updatedData);

		$this->tester->seeInDatabase('fs_bell', $updatedData);
	}

	public function testGetStoreBells()
	{
		$this->tester->clearTable('fs_abholer');

		$user1 = $this->tester->createFoodsaver();
		$bids1 = $this->tester->createStore(0);
		$collPast = $this->tester->addCollector($user1['id'], $bids1['id'], ['confirmed' => 0, 'date' => $this->faker->dateTimeBetween($max = 'now')]);
		$collSoon = $this->tester->addCollector($user1['id'], $bids1['id'], ['confirmed' => 0, 'date' => $this->faker->dateTimeBetween('+1 days', '+2 days')]);
		$collFuture = $this->tester->addCollector($user1['id'], $bids1['id'], ['confirmed' => 0, 'date' => $this->faker->dateTimeBetween('+7 days', '+14 days')]);

		$this->tester->seeNumRecords(3, 'fs_abholer');
		$betrieb_bells = $this->gateway->getStoreBells([$bids1['id']]);
		$this->assertEquals(1, count($betrieb_bells));
		$bell = $betrieb_bells[0];
		$this->assertEquals($bids1['id'], $bell['id']);
		$this->assertEquals(2, $bell['count']);
		$this->assertEquals($collSoon['date'], $bell['date']);
	}
}
