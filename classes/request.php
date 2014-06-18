<?php defined('SYSPATH') or die('No direct script access.');

class Request extends Kohana_Request
{
	public static function factory($uri = TRUE, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		$request = parent::factory($uri, $cache, $injected_routes);

		//
		// MrFriday frontend server sets HTTP_X_FORWARDED_PROTO to
		// tell us if the incoming connection was secure.
		//
		if ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) AND (strtolower($_SERVER['HTTP_X_FORWARDED_PROTOCOL'] == 'https')))
		{
			$request->secure(TRUE);
		}

		return $request;
	}

}