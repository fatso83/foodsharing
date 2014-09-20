<?php
class BezirkModel extends Model
{
	private $themes_per_page;
	private $bezirk_id;
	private $bezirk;
	
	public function __construct($tpp = 15)
	{
		$this->themes_per_page = $tpp;
		parent::__construct();
		
		$this->bezirk = false;
		$this->bezirk_id = 0;
	}
	
	public function setBezirk($bezirk)
	{
		$this->bezirk_id = $bezirk['id'];
		$this->bezirk = $bezirk;
	}
	
	public function getEvent($id)
	{
		if($event = $this->qRow('
			SELECT 	
				e.id,	
				fs.`id` AS fs_id, 
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				e.`bezirk_id`, 
				e.`location_id`, 
				e.`name`, 
				e.`start`, 
				UNIX_TIMESTAMP(e.`start`) AS start_ts,
				e.`end`, 
				UNIX_TIMESTAMP(e.`end`) AS end_ts,
				e.`description`, 
				e.`bot`, 
				e.`online`

			FROM 	
				`'.PREFIX.'event` e,
				`'.PREFIX.'foodsaver` fs
				
			WHERE
				e.foodsaver_id = fs.id
				
			AND
				e.id = '.(int)$id.'
		'))
		{
			$event['location'] = false;
			if($event['location_id'] > 0)
			{
				$event['location'] = $this->getLocation($event['location_id']);
			}
			return $event;
		}
		return false;
	}
	
	public function getActiveFoodsaver($bezirk_id)
	{
		return $this->q('
				
			SELECT 	fs.id,
					CONCAT(fs.`name`, " ", fs.`nachname`) AS `name`,
					fs.`name` AS vorname,
					fs.`anschrift`,
					fs.`email`,
					fs.`telefon`,
					fs.`handy`,
					fs.`plz`,
					fs.`geschlecht`
	
			FROM 	'.PREFIX.'foodsaver_has_bezirk fb,
					`'.PREFIX.'foodsaver` fs
	
			WHERE 	fb.foodsaver_id = fs.id
			AND 	fb.bezirk_id = '.(int)$bezirk_id.'
			AND 	fb.`active` = 1
		');
	}
	
	public function getReports()
	{
		$ret = $this->q('
			SELECT 	DISTINCT
				r.`time`,
				UNIX_TIMESTAMP(r.`time`) AS time_ts,
				r.`msg`,
				r.`tvalue`,
				r.`reporttype`,
				r.foodsaver_id AS fs_id,
				CONCAT(fs.name," ",fs.nachname)	AS `name`
			FROM 
				`'.PREFIX.'report` r,
				`'.PREFIX.'foodsaver_has_bezirk` hb,
				`'.PREFIX.'foodsaver` fs
				
			WHERE
				r.foodsaver_id = hb.foodsaver_id
			AND
				r.foodsaver_id = fs.id
			AND
				hb.bezirk_id IN('.implode(',',$this->getChildBezirke($this->bezirk['id'])).')
			AND
				r.foodsaver_id != '.(int)fsid().'
		');
		
		return $ret;
	}
	
	public function addEvent($event)
	{
		$location_id = 0;
		if(isset($event['location_id']))
		{
			$location_id = (int)$event['location_id'];
		}
		return $this->insert('
			INSERT INTO 	`fs_event`
			(
				`foodsaver_id`, 
				`bezirk_id`, 
				`location_id`, 
				`name`, 
				`start`, 
				`end`, 
				`description`, 
				`bot`, 
				`online`
			) 
			VALUES 
			(
				'.(int)fsId().',
				'.(int)$this->bezirk_id.',
				'.(int)$location_id.',
				'.$this->strval($event['name']).',
				'.$this->dateval($event['start']).',
				'.$this->dateval($event['end']).',
				'.$this->strval($event['description']).',
				0,
				'.(int)$event['online_type'].'
			)		
		');
	}
	
	public function getThemes($bezirk_id,$bot_theme = 0,$page = 0,$last = 0)
	{
		$ret = $this->q('
			SELECT 		t.id,
						t.name,
						t.`time`,
						UNIX_TIMESTAMP(t.`time`) AS time_ts,
						fs.id AS foodsaver_id,
						fs.name AS foodsaver_name,
						fs.photo AS foodsaver_photo,
						p.body AS post_body,
						p.`time` AS post_time,
						UNIX_TIMESTAMP(p.`time`) AS post_time_ts,
						t.last_post_id
				
			FROM 		'.PREFIX.'theme t,
						'.PREFIX.'theme_post p,
						'.PREFIX.'bezirk_has_theme bt,
						'.PREFIX.'foodsaver fs
				
			WHERE 		t.last_post_id = p.id
			AND 		p.foodsaver_id = fs.id
			AND 		bt.theme_id = t.id
			AND 		bt.bezirk_id = '.(int)$bezirk_id.'
			AND 		bt.bot_theme = '.(int)$bot_theme.'
			AND 		t.`active` = 1
				
			ORDER BY t.last_post_id DESC
				
			LIMIT '.(int)($page*$this->themes_per_page).', '.(int)$this->themes_per_page.'
						
		');
		
		if($last > 0)
		{
			$ll = end($ret);
			if($ll['id'] == $last)
			{
				return false;
			}
		}
		
		return $ret;
	}
	
	public function getPosts($thread_id)
	{
		return $this->q('

			SELECT 		fs.id AS fs_id,
						fs.name AS fs_name,
						fs.photo AS fs_photo,
						p.body,
						p.`time`,
						p.id,
						UNIX_TIMESTAMP(p.`time`) AS time_ts
		
			FROM 		'.PREFIX.'theme_post p,
						'.PREFIX.'foodsaver fs
		
			WHERE 		p.foodsaver_id = fs.id
			AND 		p.theme_id = '.(int)$thread_id.'	
			
			ORDER BY 	p.`time`
		');
	}
	
	public function deletePost($id)
	{
		$theme_id = $this->getVal('theme_id', 'theme_post', $id);
		$this->del('DELETE FROM `'.PREFIX.'theme_post` WHERE `id` = '.(int)$id);
		
		if($last_post_id = $this->qOne('SELECT MAX(`id`) FROM `'.PREFIX.'theme_post` WHERE `theme_id` = '.(int)$theme_id))
		{
			$this->update('UPDATE `'.PREFIX.'theme` SET `last_post_id` = '.(int)$last_post_id.' WHERE `id` = '.(int)$theme_id);
		}
		else
		{
			$this->del('DELETE FROM `'.PREFIX.'theme` WHERE `id` = '.(int)$theme_id);
		}
		
		return true;
	}
	
	public function activateTheme($theme_id)
	{
		$this->update('
			UPDATE '.PREFIX.'theme SET active = 1 WHERE id = '.(int)$theme_id.'	
		');
	}
	
	public function deleteTheme($theme_id)
	{
		$this->del('
			DELETE FROM '.PREFIX.'theme_post
			WHERE theme_id = '.(int)$theme_id.'
		');
		$this->del('
			DELETE FROM '.PREFIX.'theme
			WHERE id = '.(int)$theme_id.'
		');
	}
	
	public function getThread($bezirk_id,$thread_id,$bot_theme = 0)
	{
		return $this->qRow('
			SELECT 		t.id,
						t.name,
						t.`time`,
						UNIX_TIMESTAMP(t.`time`) AS time_ts,
						fs.id AS foodsaver_id,
						fs.name AS foodsaver_name,
						fs.photo AS foodsaver_photo,
						p.body AS post_body,
						p.`time` AS post_time,
						UNIX_TIMESTAMP(p.`time`) AS post_time_ts,
						t.last_post_id,
						t.`active`
		
			FROM 		'.PREFIX.'theme t,
						'.PREFIX.'theme_post p,
						'.PREFIX.'bezirk_has_theme bt,
						'.PREFIX.'foodsaver fs
		
			WHERE 		t.last_post_id = p.id
			AND 		p.foodsaver_id = fs.id
			AND 		bt.theme_id = t.id
			AND 		bt.bezirk_id = '.(int)$bezirk_id.'
			AND 		t.id = '.(int)$thread_id.'
			AND 		bt.bot_theme = '.(int)$bot_theme.'
				
			LIMIT 1
		
		');
	}
	
	public function getBezirkRequests($bezirk_id)
	{
		return $this->q('
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`nachname`,
					fs.`photo`,
					fb.application,
					fb.active,
					UNIX_TIMESTAMP(fb.added) as `time`

			FROM 	`'.PREFIX.'foodsaver_has_bezirk` fb,
					`'.PREFIX.'foodsaver` fs
				
			WHERE 	fb.foodsaver_id = fs.id
			AND 	fb.bezirk_id = '.(int)$bezirk_id.'
			AND 	fb.active = 0
		');
	}
	
	public function addTheme($bezirk_id,$name,$body,$bot_theme = 0,$active)
	{
		$theme_id = $this->insert('
			INSERT INTO '.PREFIX.'theme (`foodsaver_id`, `name`, `time`,`active`) 
			VALUES(	
				'.(int)fsId().',
				'.$this->strval($name).',
				NOW(),
				'.(int)$active.'
			)
		');
		
		$this->followTheme($theme_id);
		
		$this->insert('
			INSERT INTO `fs_bezirk_has_theme`
			(
				`bezirk_id`,
				`theme_id`,
				`bot_theme`
			)
			VALUES('.$bezirk_id.','.$theme_id.','.(int)$bot_theme.')
		');
		
		$this->addThemePost($theme_id, $body);
		
		return $theme_id;
	}
	
	public function getThreadFollower($theme_id)
	{
		return $this->q('
			SELECT 	fs.name,
					fs.geschlecht,
					fs.email

			FROM 	'.PREFIX.'foodsaver fs,
					'.PREFIX.'theme_follower tf
			WHERE 	tf.foodsaver_id = fs.id
			AND 	tf.theme_id = '.(int)$theme_id.'
			AND 	tf.foodsaver_id != '.(int)fsId().'
		');
	}
	
	public function followTheme($theme_id)
	{
		return $this->insert('
			REPLACE INTO `'.PREFIX.'theme_follower`(`foodsaver_id`, `theme_id`, `infotype`)
			VALUES
			(
				'.(int)fsId().',
				'.(int)$theme_id.',
				1
			)
		');
	}
	
	public function addThemePost($theme_id,$body,$reply = 0,$bezirk = false)
	{
		$post_id = $this->insert('
			INSERT INTO '.PREFIX.'theme_post (`theme_id`, `foodsaver_id`, `reply_post`, `body`, `time`) 
			VALUES(
				'.(int)$theme_id.',
				'.(int)fsId().',
				'.$reply.',
				'.$this->strval($body,'<p><a><ul><strong><b><i><ol><li><br>').',
				NOW()
			)
		');
		
		$this->update('
			UPDATE 	'.PREFIX.'theme 
			SET 	`last_post_id` = '.(int)$post_id.'
			WHERE 	`id` = '.(int)$theme_id.'
		');
		
		if($reply > 0)
		{
			$fs_id = $this->getVal('foodsaver_id', 'theme_post', $reply);
			$this->addGlocke($fs_id, $this->getVal('name', 'foodsaver', fsId()).' hat Dir geantwortet','Forum '.$bezirk['name'],'?page=bezirk&bid='.$bezirk['id'].'&sub=forum&tid='.$theme_id.'&pid='.$post_id.'#post'.$post_id);
		}
		
		return $post_id;
	}
	
	public function listFairteiler($bezirk_id)
	{
		$bids = $this->getChildBezirke($bezirk_id);
		if($fairteiler = $this->q('
	
			SELECT 	`id`,
					`name`,
					`picture`
			FROM 	`'.PREFIX.'fairteiler`
			WHERE 	`bezirk_id` IN( '.implode(',', $bids).' )
			AND 	`status` = 1
		'))
		{
			foreach ($fairteiler as $key => $ft)
			{
				$fairteiler[$key]['pic'] = false;
				if(!empty($ft['picture']))
				{
					$fairteiler[$key]['pic'] = array(
							'thumb' => 'images/'.str_replace('/', '/crop_1_60_', $ft['picture']),
							'head' => 'images/'.str_replace('/', '/crop_0_528_', $ft['picture']),
							'orig' => 'images/'.($ft['picture'])
					);
				
				}
			}
			return $fairteiler;
		}
		return false;
	}
	
	public function getBezirk($id = false)
	{
		$bezirk = $this->qRow('
			SELECT 	
				`id`, 
				`name`,
				`email`,
				`email_name`, 
				`type`, 
				`stat_fetchweight`,
				`stat_fetchcount`,
				`stat_fscount`,
				`stat_botcount`,
				`stat_postcount`,
				`stat_betriebcount`,
				`stat_korpcount`
				
			FROM 	`'.PREFIX.'bezirk`
				
			WHERE 	`id` = '.(int)$id.'	
			LIMIT 1
		');
		
		$bezirk['foodsaver'] = $this->q('
			SELECT 	fs.`id`,
					fs.`photo`,
					fs.`name`,
					fs.`nachname`

			FROM 	`'.PREFIX.'foodsaver` fs,
					`'.PREFIX.'foodsaver_has_bezirk` c
				
			WHERE 	c.`foodsaver_id` = fs.id
			AND 	c.bezirk_id = '.(int)$id.'
			AND 	c.active = 1
				
			ORDER BY fs.`name`
		');
		
		
		$bezirk['fs_count'] = count($bezirk['foodsaver']);
		
		$bezirk['botschafter'] = $this->q('
			SELECT 	fs.`id`,
					fs.`photo`,
					fs.`name`,
					fs.`nachname`

			FROM 	`'.PREFIX.'foodsaver` fs,
					`'.PREFIX.'botschafter` c
				
			WHERE 	c.`foodsaver_id` = fs.id
			AND 	c.bezirk_id = '.(int)$id.'
		');
		
		return $bezirk;
	}
	
	public function listEvents($bot = 0)
	{
		return $this->q('
			SELECT 	
				e.`id`,
				e.`name`,
				e.`start`, 
				UNIX_TIMESTAMP(e.`start`) AS start_ts,
				e.`end`, 
				UNIX_TIMESTAMP(e.`end`) AS end_ts
				
			FROM 	
				`'.PREFIX.'event` e
				
			WHERE 	
				e.bezirk_id = '.(int)$this->bezirk_id.'
				
			AND e.start > NOW()
				
			ORDER BY
				e.start
		');
	}
	
	public function getFsCount($bid)
	{
		return (int)$this->qOne('
			SELECT
				COUNT(hb.foodsaver_id)
				
			FROM
				'.PREFIX.'foodsaver_has_bezirk hb
				
			WHERE
				hb.bezirk_id = '.(int)$bid.'
				
			AND
				hb.active = 1
		');
	}
	
	public function getBotCount($bid)
	{
		return (int)$this->qOne('
			SELECT 	
				COUNT(b.foodsaver_id)
				
			FROM
				'.PREFIX.'botschafter b

			WHERE
				b.bezirk_id = '.(int)$bid.'
		');
	}
	
	public function updateRequestNote($bid,$fid,$note)
	{
		return $this->update('
			UPDATE 	'.PREFIX.'foodsaver_has_bezirk
			SET 	`note` = '.$this->strval($note).'		
		');
	}
}