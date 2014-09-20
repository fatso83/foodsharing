<?php

$protocol = 'http';
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
{
	$protocol = 'https';
}

define('PROTOCOL',$protocol);
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','pw');
define('DB_DB','lmr3');
define('PREFIX','fs_');
define('ERROR_REPORT',E_ALL);
define('BASE_URL', $protocol . '://fs.local/');
define('URL_INTERN',$protocol . '://fs.local/');
define('DEFAULT_EMAIL','noreply@foodsharing.de');
define('DEFAULT_EMAIL_NAME','Foodsharing Freiwillige');
define('VERSION','0.8.1');
define('EMAIL_PUBLIC', 'info@foodsharing.de');
define('EMAIL_PUBLIC_NAME','Foodsharing');
define('DEFAULT_HOST','foodsharing.de');
define('API_ID','c1d9a69515d63194b4329a5359a4c40a'); // foodsharing api key

define('SMTP_HOST','');
define('SMTP_USER','');
define('SMTP_PASS','');
define('SMTP_PORT',25);

if(!defined('ROOT_DIR'))
{
	define('ROOT_DIR','./');
}
