<?php

namespace Foodsharing\Lib;

class Routing
{
	private static $classes = array('activity' => 'Activity',
		'application' => 'Application',
		'basket' => 'Basket',
		'bell' => 'Bell',
		'blog' => 'Blog',
		'buddy' => 'Buddy',
		'bcard' => 'BusinessCard',
		'content' => 'Content',
		'dashboard' => 'Dashboard',
		'email' => 'Email',
		'event' => 'Event',
		'faq' => 'FAQAdmin',
		'fairteiler' => 'FoodSharePoint',
		'foodsaver' => 'Foodsaver',
		'index' => 'Index',
		'listFaq' => 'FAQList',
		'legal' => 'Legal',
		'login' => 'Login',
		'logout' => 'Logout',
		'mailbox' => 'Mailbox',
		'main' => 'Main',
		'map' => 'Map',
		'msg' => 'Message',
		'message' => 'Message',
		'passgen' => 'PassportGenerator',
		'profile' => 'Profile',
		'quiz' => 'Quiz',
		'bezirk' => 'Region',
		'region' => 'RegionAdmin',
		'relogin' => 'Relogin',
		'report' => 'Report',
		'search' => 'Search',
		'settings' => 'Settings',
		'statistics' => 'Statistics',
		'betrieb' => 'Store',
		'fsbetrieb' => 'StoreUser',
		'team' => 'Team',
		'wallpost' => 'WallPost',
		'groups' => 'WorkGroup');

	public static $fqcnPrefix = '\\Foodsharing\\Modules\\';

	public static function getClassName($appName, $type = 'Xhr')
	{
		if (!array_key_exists($appName, self::$classes)) {
			return null;
		}

		return self::$fqcnPrefix . self::$classes[$appName] . '\\' . self::$classes[$appName] . $type;
	}
}
