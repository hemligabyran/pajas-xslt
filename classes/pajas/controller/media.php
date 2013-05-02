<?php defined('SYSPATH') OR die('No direct access allowed.');

class Pajas_Controller_Media extends Controller
{

	public function action_css()
	{
		$path = $this->request->param('path');
		$file = Kohana::find_file('css', $path, 'css');
		if ($file)
		{
			$this->response->headers('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');
			$this->response->headers('Content-Type', 'text/css');
			echo file_get_contents($file);
		}
		else
		{
			throw new Http_Exception_404('File not found!');
		}
	}

	public function action_fonts()
	{
		$path                   = $this->request->param('path');
		$path_info              = pathinfo($path);
		$path_info['extension'] = strtolower($path_info['extension']);

		$file = Kohana::find_file('fonts', substr($path, 0, strlen($path) - (strlen($path_info['extension']) + 1)), $path_info['extension']);
		if ($file && in_array($path_info['extension'], array('eot', 'svg', 'ttf', 'woff')))
		{
			$this->response->headers('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');

			if     ($path_info['extension'] == 'eot')  $this->response->headers('Content-Type', 'application/vnd.ms-fontobject');
			elseif ($path_info['extension'] == 'svg')  $this->response->headers('Content-Type', 'image/svg+xml');
			elseif ($path_info['extension'] == 'ttf')  $this->response->headers('Content-Type', 'font/ttf');
			elseif ($path_info['extension'] == 'woff') $this->response->headers('Content-Type', 'application/x-font-woff');

			echo file_get_contents($file);
		}
		else throw new Http_Exception_404('File not found!');
	}

	public function action_img()
	{
		$path      = $this->request->param('path');
		$path_info = pathinfo($path);
		$mime      = File::mime_by_ext($path_info['extension']);

		$file = Kohana::find_file('img', substr($path, 0, strlen($path) - (strlen($path_info['extension']) + 1)), $path_info['extension']);
		if ($file && substr($mime, 0, 5) == 'image')
		{
			// Find the file ending
			$file_ending = pathinfo($file, PATHINFO_EXTENSION);

			// Check if it needs resizing
			$cache_ending = '';

			list($original_width, $original_height) = getimagesize($file);
			$wh_ratio = $original_width / $original_height;

			// Get params
			if ( ! (isset($_GET['width'])     && preg_match('/^\d+$/', $_GET['width']    ))) $_GET['width']     = FALSE;
			if ( ! (isset($_GET['height'])    && preg_match('/^\d+$/', $_GET['height']   ))) $_GET['height']    = FALSE;
			if ( ! (isset($_GET['maxwidth'])  && preg_match('/^\d+$/', $_GET['maxwidth'] ))) $_GET['maxwidth']  = FALSE;
			if ( ! (isset($_GET['maxheight']) && preg_match('/^\d+$/', $_GET['maxheight']))) $_GET['maxheight'] = FALSE;

			// Find out new dimensions
			if ($_GET['maxwidth'] && $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
			{
				if (($_GET['maxwidth'] / $_GET['maxheight']) < $wh_ratio) $_GET['width']  = $_GET['maxwidth'];
				else                                                      $_GET['height'] = $_GET['maxheight'];
			}
			elseif ($_GET['maxwidth'] && ! $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
				$_GET['width'] = $_GET['maxwidth'];
			elseif ( ! $_GET['maxwidth'] && $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
				$_GET['height'] = $_GET['maxheight'];

			if ($_GET['height'] && ! $_GET['width']) $_GET['width']  = round($wh_ratio * $_GET['height']);
			if ($_GET['width'] && ! $_GET['height']) $_GET['height'] = round($_GET['width'] / $wh_ratio);

			if ( ! $_GET['width'] && ! $_GET['height'])
			{
				$_GET['height'] = $original_height;
				$_GET['width']  = $original_width;
			}

			if ($_GET['width']  != $original_width)  $cache_ending .= '_width_'.$_GET['width'];
			if ($_GET['height'] != $original_height) $cache_ending .= '_height_'.$_GET['height'];

			$cached_filename = FALSE;
			if ($cache_ending != '')
			{
				// Resizing needed
				if (substr($path_info['dirname'], 0, 1) == '/')
					$cached_filename = Kohana::$cache_dir.'/user_content'.$path_info['dirname'].'/'.$path_info['basename'].$cache_ending;
				elseif ($path_info['dirname'] == '.')
					$cached_filename = Kohana::$cache_dir.'/user_content/'.$path_info['basename'].$cache_ending;
				else
					$cached_filename = Kohana::$cache_dir.'/user_content/'.$path_info['dirname'].'/'.$path_info['basename'].$cache_ending;

				if ( ! file_exists($cached_filename) || filemtime($file) < filemtime($cached_filename))
				{
					if ( ! file_exists(pathinfo($cached_filename, PATHINFO_DIRNAME)))
						exec('mkdir -p '.pathinfo($cached_filename, PATHINFO_DIRNAME));

					// Create a new cached resized file
					$this->resize_image($file, $cached_filename, $_GET['width'], $_GET['height']);
				}
			}

			$this->response->headers('Content-Type', 'content-type: '.$mime.'; encoding='.Kohana::$charset.';');

			// Getting headers sent by the client.
			$headers = apache_request_headers();

			if ($cached_filename)
				$file = $cached_filename;

			$this->response->headers('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');


			// Checking if the client is validating his cache and if it is current.
			if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file)))
			{
				// Client's cache IS current, so we just respond '304 Not Modified'.
				$this->response->status(304);
			}
			else
			{
				// Image not cached or cache outdated, we respond '200 OK' and output the image.
				$this->response->headers('Content-Length', strval(filesize($file)));
				$this->response->status(200);
				$this->response->body(file_get_contents($file));
			}
		}
		else
		{
			// File not found at all
			throw new Http_Exception_404('File not found!');
		}
	}

	public function action_js()
	{
		$path = $this->request->param('path');

		$file = Kohana::find_file('js', $path, 'js');
		if ($file)
		{
			$this->response->headers('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');
			$this->response->headers('Content-Type', 'application/javascript');
			echo file_get_contents($file);
		}
		else throw new Http_Exception_404('File not found!');
	}

	public function action_user_content_image()
	{
		$path      = $this->request->param('file');
		$path_info = pathinfo($path);
		$mime      = File::mime_by_ext($path_info['extension']);
		$file      = Kohana::$config->load('user_content.dir').'/images/'.$path;
		if ($file && substr($mime, 0, 5) == 'image')
		{
			// Find the file ending
			$file_ending = pathinfo($file, PATHINFO_EXTENSION);

			// Check if it needs resizing
			$cache_ending = '';

			list($original_width, $original_height) = getimagesize($file);
			$wh_ratio = $original_width / $original_height;

			// Get params
			if ( ! (isset($_GET['width'])     && preg_match('/^\d+$/', $_GET['width']    ))) $_GET['width']     = FALSE;
			if ( ! (isset($_GET['height'])    && preg_match('/^\d+$/', $_GET['height']   ))) $_GET['height']    = FALSE;
			if ( ! (isset($_GET['maxwidth'])  && preg_match('/^\d+$/', $_GET['maxwidth'] ))) $_GET['maxwidth']  = FALSE;
			if ( ! (isset($_GET['maxheight']) && preg_match('/^\d+$/', $_GET['maxheight']))) $_GET['maxheight'] = FALSE;

			// Find out new dimensions
			if ($_GET['maxwidth'] && $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
			{
				if (($_GET['maxwidth'] / $_GET['maxheight']) < $wh_ratio) $_GET['width']  = $_GET['maxwidth'];
				else                                                      $_GET['height'] = $_GET['maxheight'];
			}
			elseif ($_GET['maxwidth'] && ! $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
				$_GET['width'] = $_GET['maxwidth'];
			elseif ( ! $_GET['maxwidth'] && $_GET['maxheight'] && ! $_GET['height'] && ! $_GET['width'])
				$_GET['height'] = $_GET['maxheight'];

			if ($_GET['height'] && ! $_GET['width']) $_GET['width']  = round($wh_ratio * $_GET['height']);
			if ($_GET['width'] && ! $_GET['height']) $_GET['height'] = round($_GET['width'] / $wh_ratio);

			if ( ! $_GET['width'] && ! $_GET['height'])
			{
				$_GET['height'] = $original_height;
				$_GET['width']  = $original_width;
			}

			if ($_GET['width']  != $original_width)  $cache_ending .= '_width_'.$_GET['width'];
			if ($_GET['height'] != $original_height) $cache_ending .= '_height_'.$_GET['height'];

			$cached_filename = FALSE;
			if ($cache_ending != '')
			{
				// Resizing needed
				if (substr($path_info['dirname'], 0, 1) == '/')
					$cached_filename = Kohana::$cache_dir.'/user_content'.$path_info['dirname'].'/'.$path_info['basename'].$cache_ending;
				elseif ($path_info['dirname'] == '.')
					$cached_filename = Kohana::$cache_dir.'/user_content/'.$path_info['basename'].$cache_ending;
				else
					$cached_filename = Kohana::$cache_dir.'/user_content/'.$path_info['dirname'].'/'.$path_info['basename'].$cache_ending;

				if ( ! file_exists($cached_filename) || filemtime($file) < filemtime($cached_filename))
				{
					if ( ! file_exists(pathinfo($cached_filename, PATHINFO_DIRNAME)))
						exec('mkdir -p '.pathinfo($cached_filename, PATHINFO_DIRNAME));

					// Create a new cached resized file
					$this->resize_image($file, $cached_filename, $_GET['width'], $_GET['height']);
				}
			}

			$this->response->headers('Content-Type', 'content-type: '.$mime.'; encoding='.Kohana::$charset.';');

			// Getting headers sent by the client.
			$headers = apache_request_headers();

			if ($cached_filename)
				$file = $cached_filename;

			$this->response->headers('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');


			// Checking if the client is validating his cache and if it is current.
			if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file)))
			{
				// Client's cache IS current, so we just respond '304 Not Modified'.
				$this->response->status(304);
			}
			else
			{
				// Image not cached or cache outdated, we respond '200 OK' and output the image.
				$this->response->headers('Content-Length', strval(filesize($file)));
				$this->response->status(200);
				$this->response->body(file_get_contents($file));
			}
		}
		else
		{
			// File not found at all
			throw new Http_Exception_404('File not found!');
		}
	}

	public function action_xsl()
	{
		$path = $this->request->param('path');

		$file = Kohana::find_file('xsl', $path, 'xsl');
		if ($file)
		{
			$this->response->headers('Content-type', 'text/xml; encoding='.Kohana::$charset.';');
			$this->response->body(file_get_contents($file));
		}
		else
		{
			throw new Http_Exception_404('File not found!');
		}
	}

	protected function resize_image($src, $dst, $new_width, $new_height)
	{
		if ($image_size = getimagesize($src))
			list($original_width, $original_height) = $image_size;
		else return FALSE;

		$type = strtolower(substr(strrchr($src,'.'),1));
		if ($type == 'jpeg') $type = 'jpg';
		switch ($type)
		{
			case 'bmp': $img = imagecreatefromwbmp($src); break;
			case 'gif': $img = imagecreatefromgif($src);  break;
			case 'jpg': $img = imagecreatefromjpeg($src); break;
			case 'png': $img = imagecreatefrompng($src);  break;
			default : return FALSE;
		}

		// resize
		$new_image = imagecreatetruecolor($new_width, $new_height);

		// preserve transparency
		if ($type == 'gif' or $type == 'png')
		{
			imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
			imagealphablending($new_image, FALSE);
			imagesavealpha($new_image, TRUE);
		}

		imagecopyresampled($new_image, $img, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

		switch ($type)
		{
			case 'bmp': imagewbmp($new_image, $dst); break;
			case 'gif': imagegif($new_image,  $dst); break;
			case 'jpg': imagejpeg($new_image, $dst); break;
			case 'png': imagepng($new_image,  $dst); break;
		}

		return TRUE;
	}

}