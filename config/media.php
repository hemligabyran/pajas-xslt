<?php defined('SYSPATH') or die('No direct script access.');

/*
 * This config file is used to configure the
 * media controller
 *
 */

return array(
	// Name of route and action to use => URL as a route, use multiple by splitting with |
	// Name (array key) will also be the action name in the media controller
	'media_css'   => 'css/<path>.css',
	'media_fonts' => 'fonts/<path>',
	'media_img'   => 'img/<path>',
	'media_js'    => 'js/<path>.js',
	'media_xsl'   => 'xsl/<path>.xsl',
);
