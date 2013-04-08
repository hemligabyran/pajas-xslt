<?php defined('SYSPATH') or die('No direct script access.');

return array(
//	'driver' => 'mysql',
	'driver' => Kohana::$config->load('pdo.default.driver'),
	'dir'    => APPPATH.'user_content',
);
