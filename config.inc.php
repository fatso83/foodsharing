<?php

# TODO: sanitize env name
# TODO: maybe have a default env?
# TODO: check if there is not already a concept of app environment elsewhere

$FS_ENV= getenv('FS_ENV');
$env_filename = 'config.inc.'.$FS_ENV.'.php';

if (file_exists($env_filename)) {
	require_once $env_filename;
} else {
	die('no config found for env ['.$FS_ENV.']');
}
