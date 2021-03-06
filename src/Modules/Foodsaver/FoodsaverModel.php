<?php

namespace Foodsharing\Modules\Foodsaver;

use DateTime;
use Foodsharing\Lib\Db\Db;
use Foodsharing\Modules\Store\StoreModel;

class FoodsaverModel extends Db
{
	public function listFoodsaver($bezirk_id, $showOnlyInactive = false)
	{
		return $this->q('
		    SELECT
			fs.id,
			fs.name,
			fs.nachname,
			fs.photo,
			fs.sleep_status,
			CONCAT("#",fs.id) AS href
			 
		    FROM
			fs_foodsaver fs,
			fs_foodsaver_has_bezirk hb
			 
		    WHERE
			fs.id = hb.foodsaver_id
			 
		    AND
			fs.deleted_at IS NULL

		    AND
			hb.bezirk_id = ' . (int)$bezirk_id .
			($showOnlyInactive ? '
		    AND (
			fs.last_login <  "' . (new DateTime('NOW -6 MONTH'))->format('Y-m-d H:i:s') . '"
		    OR
			fs.last_login IS NULL)' : '') . '
		    ORDER BY
			fs.name ASC
		');
	}

	public function update_foodsaver($fsId, $data, StoreModel $storeModel)
	{
		$data['anmeldedatum'] = date('Y-m-d H:i:s');

		if (!isset($data['bezirk_id'])) {
			$data['bezirk_id'] = $this->session->getCurrentRegionId();
		}

		$orga = '';
		if (isset($data['orgateam'])) {
			$orga = '`orgateam` = ' . (int)$data['orgateam'] . ',';
		}

		$rolle = '';
		$quiz_rolle = '';
		$verified = '';
		if (isset($data['rolle'])) {
			$rolle = '`rolle` =  ' . (int)$data['rolle'] . ',';
			if ($data['rolle'] == 0 && $this->session->isOrgaTeam()) {
				$data['bezirk_id'] = 0;
				$quiz_rolle = '`quiz_rolle` = 0,';
				$verified = '`verified` = 0,';

				$storeIds = $this->q('
					SELECT 	bt.betrieb_id as id
					FROM 	fs_betrieb_team bt
					WHERE 	bt.foodsaver_id = ' . (int)$fsId . '
				');
				//Delete from Companies
				foreach ($storeIds as $storeId) {
					$storeModel->signout($storeId, $fsId);
				}

				//Delete Bells for Foodsaver
				$this->del('
					DELETE FROM  `fs_foodsaver_has_bell`
					WHERE 		`foodsaver_id` = ' . (int)$fsId . '
				');
				// Delete from Bezirke and Working Groups
				$this->del('
					DELETE FROM  `fs_foodsaver_has_bezirk`
					WHERE 		`foodsaver_id` = ' . (int)$fsId . '
				');
				//Delete from Bezirke and Working Groups (when Admin)
				$this->del('
					DELETE FROM  `fs_botschafter`
					WHERE 		`foodsaver_id` = ' . (int)$fsId . '
				');

				//Block Person for Quiz
				for ($i = 1; $i <= 7; ++$i) {
					$this->insert('
					INSERT INTO fs_quiz_session (
						foodsaver_id,
						quiz_id,
						`status`,
						time_start
					)
					VALUES
					(
						' . (int)$fsId . ',
						1,
						2,
						now()
					)
				');
				}
			}
		}

		$position = '';
		if (isset($data['position'])) {
			$position = '`position` =  ' . $this->strval($data['position']) . ',';
		}

		$email = '';
		if (isset($data['email'])) {
			$email = '`email` = ' . $this->strval($data['email']) . ',';
		}

		return $this->update('

		UPDATE 	`fs_foodsaver`

		SET
				`bezirk_id` =  ' . (int)$data['bezirk_id'] . ',
				`plz` =  ' . $this->strval(trim($data['plz'])) . ',
				`stadt` =  ' . $this->strval(trim($data['stadt'])) . ',
				`lat` =  ' . $this->strval(trim($data['lat'])) . ',
				`lon` =  ' . $this->strval(trim($data['lon'])) . ',
				`name` =  ' . $this->strval($data['name']) . ',
				`nachname` =  ' . $this->strval($data['nachname']) . ',
				`anschrift` =  ' . $this->strval($data['anschrift']) . ',
				`telefon` =  ' . $this->strval($data['telefon']) . ',
				`handy` =  ' . $this->strval($data['handy']) . ',
				`geschlecht` =  ' . (int)$data['geschlecht'] . ',
				' . $position . '
				' . $rolle . '
				' . $orga . '
				' . $email . '
				' . $quiz_rolle . '
				' . $verified . '
				`geb_datum` =  ' . $this->dateval($data['geb_datum']) . '

		WHERE 	`id` = ' . (int)$fsId);
	}
}
