<?php

namespace Foodsharing\Modules\API;

use Foodsharing\Modules\Core\BaseGateway;

class APIGateway extends BaseGateway
{
	public function getOrgaGroups(): array
	{
		$stm = 'SELECT id, name, parent_id FROM fs_bezirk WHERE type = 7 ORDER BY parent_id';

		return $this->db->fetchAll($stm);
	}

	public function allBaskets(): array
	{
		$stm = '
			SELECT
				b.id AS i,
				b.lat AS a,
				b.lon AS o
			FROM
				fs_basket b
		
			WHERE
				b.status = 1
		
			AND
				b.fs_id = 0
		';

		return $this->db->fetchAll($stm);
	}

	public function nearBaskets($lat, $lon, $distance = 50): array
	{
		$stm = '
			SELECT 	
				b.id AS i,
				b.lat AS a, 
				b.lon AS o, 
				(6371 * acos( cos( radians( :latitude ) ) * cos( radians( b.lat ) ) * cos( radians( b.lon ) - radians( :longitude ) ) + sin( radians( :latitude_dup ) ) * sin( radians( b.lat ) ) ))
				AS d
			FROM 	
				fs_basket b
				
			WHERE
				b.status = 1
				
			AND
				b.fs_id = 0
				
			HAVING 
				d <= :distance
		';

		return $this->db->fetchAll(
			$stm,
			[
				':latitude' => (float)$lat,
				':longitude' => (float)$lon,
				':latitude_dup' => (float)$lat,
				':distance' => (int)$distance,
			]
		);
	}

	public function getBasket($id)
	{
		$stm = '
				SELECT
					b.id,
					b.description,
					b.picture,
					b.contact_type,
					b.tel,
					b.handy,
					b.fs_id AS fsf_id,
					b.foodsaver_id,
					b.lat,
					b.lon
	
				FROM
					fs_basket b
	
				WHERE
					b.id = :id
		';
		$basket = $this->db->fetch($stm, [':id' => (int)$id]);

		$stm = '
				SELECT
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				fs.id AS fs_id
						
				FROM
				fs_foodsaver fs
						
				WHERE
				fs.id = :foodsaver_id						
			';
		if ('0' === $basket['fsf_id'] && $fs = $this->db->fetch(
				$stm,
				[':foodsaver_id' => (int)$basket['foodsaver_id']]
			)) {
			$basket = array_merge($basket, $fs);
		}

		return $basket;
	}
}