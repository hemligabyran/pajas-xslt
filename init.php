<?php defined('SYSPATH') or die('No direct script access.');

// Set version
define('PAJAS_XSLT_VERSION', '1.0');

// Check and set up user content directory
if ( ! is_writable(Kohana::$config->load('user_content.dir')))
{
	throw new Kohana_Exception('Directory :dir must be writable',
		array(':dir' => Debug::path(Kohana::$config->load('user_content.dir'))));
}
if (Kohana::$environment === Kohana::DEVELOPMENT && ! is_dir(Kohana::$config->load('user_content.dir').'/images'))
{
	if ( ! mkdir(Kohana::$config->load('user_content.dir').'/images'))
	{
		throw new Kohana_Exception('Failed to create :dir',
			array(':dir' => Debug::path(Kohana::$config->load('user_content.dir').'/images')));
	}
}

// Media routes
foreach (Kohana::$config->load('media') as $name => $URL)
{
	Route::set($name, $URL,
		array(
			'path' => '[a-zA-Z0-9_/\.-]+',
		))
		->defaults(array(
			'controller' => 'media',
			'action'     => substr($name, 6),
		));
}

// User content
Route::set('user_content', 'user_content/<file>',
	array(
		'file' => '.*',
	))
	->defaults(array(
		'controller' => 'media',
		'action'     => 'user_content',
	));

// Favicon in the application/img folder
Route::set('favicon', 'favicon.ico')
	->defaults(array(
		'controller' => 'media',
		'action'     => 'favicon',
	));