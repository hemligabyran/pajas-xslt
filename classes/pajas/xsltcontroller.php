<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Kohana Controller class. The controller class must be extended to work
 * properly, so this class is defined as abstract.
 */
abstract class Pajas_Xsltcontroller extends Controller
{

	/**
	 * URL to redirect to if this URL is restricted
	 * If set to FALSE will give 403 instead
	 */
	public $acl_redirect_url = FALSE;

	/**
	 * If set to TRUE, render() will automaticly be ran
	 * when the controller is done.
	 */
	public $auto_render = TRUE;

	/**
	 * This informs us of the type of client, normally 'desktop'
	 * or 'mobile'. The choice is detected in the class
	 * constructor. For now default is 'desktop',
	 */
	public $client_type = 'desktop';

	/**
	 * Generic errors to put in the XML
	 */
	public $errors = array();

	/**
	 * If set will show page even if the user has no access to it
	 * according to the ACL
	 */
	public $ignore_acl = FALSE;

	/**
	 * Generic messages to put in the XML
	 */
	public $messages = array();

	/**
	 * Meta data to be put in the XML
	 */
	public $meta = array();

	/**
	 * Decides where the transformation of XSLT->HTML
	 * should be done
	 * ATTENTION! This setting is configurable in xslt.php
	 *
	 * options:
	 * 'auto' = Normally sends XML+XSLT, but sometimes HTML,
	 *          depending on the HTTP_USER_AGENT
	 * TRUE   = Always send HTML
	 * FALSE  = Always send XML+XSLT
	 * XML    = Send only XML without XSLT processing instruction
	 * JSON   = Send JSON
	 *
	 */
	public $transform;

	/**
	 * Where to look for the XSLT stylesheets
	 */
	public $xslt_path;

	/**
	 * The filename of the XSLT stylesheet, excluding .xsl
	 */
	public $xslt_stylesheet = FALSE;

	/**
	 * Creates a new controller instance. Each controller must be constructed
	 * with the request object that created it.
	 *
	 * @param   Request   $request  Request that created the controller
	 * @param   Response  $response The request's response
	 * @return  void
	 */
	public function __construct(Request $request, Response $response)
	{
		parent::__construct($request, $response);

		// Set transformation
		if (isset($_GET['transform']))
		{
			if     (strtolower($_GET['transform']) == 'true')  $this->transform = TRUE;
			elseif (strtolower($_GET['transform']) == 'false') $this->transform = FALSE;
			elseif (strtolower($_GET['transform']) == 'xml')   $this->transform = 'XML';
			elseif (strtolower($_GET['transform']) == 'json')  $this->transform = 'JSON';
			else                                               $this->transform = 'auto';
		}
		else $this->transform = Kohana::$config->load('xslt.transform');

		// Set XSLT path
		$this->xslt_path = Kohana::$base_url.'xsl/';

		// Create the XML DOM
		$this->dom = new DomDocument('1.0', 'UTF-8');
		$this->dom->formatOutput = TRUE;

		// Create the XML root
		$this->xml = $this->dom->appendChild($this->dom->createElement('root'));

		// Create the meta node
		$this->xml_meta = $this->xml->appendChild($this->dom->createElement('meta'));

		// Create the content node
		$this->xml_content = $this->xml->appendChild($this->dom->createElement('content'));

		// Add sticky errors and messages
		if (Session::instance()->get('xsltcontroller_errors', NULL))
		{
			$this->errors = Session::instance()->get('xsltcontroller_errors');
			Session::instance()->delete('xsltcontroller_errors');
		}
		else $this->errors = array();

		if (Session::instance()->get('xsltcontroller_messages', NULL))
		{
			$this->messages = Session::instance()->get('xsltcontroller_messages');
			Session::instance()->delete('xsltcontroller_messages');
		}
		else $this->messages = array();

		// Find out what type of client we're dealing with.
		// Todo: Perhaps 'm.' should not be hardcoded.
		// Todo: rename client_type to something more intelligent, like presentation_mode.
		if (substr($_SERVER['HTTP_HOST'], 0, 2) == 'm.')
			$this->client_type = 'mobile';
		else
			$this->client_type = 'desktop';

	}

	/**
	 * Add a simple error message
	 *
	 * @param str $error
	 * @param str $identifier - an ID to give to this message for reference
	 * @param bol $sticky - sticks around for one redirect
	 * @return boolean
	 */
	public function add_error($error, $identifier = FALSE, $sticky = FALSE)
	{
		if ($sticky)
		{
			$current_messages = Session::instance()->get('xsltcontroller_errors');
			if ( ! $current_messages) $current_messages = array();
			$current_messages[] = array('identifier' => $identifier, 'message' => $error);
			Session::instance()->set('xsltcontroller_errors', $current_messages);
		}
		else $this->errors[] = array('identifier' => $identifier, 'message' => $error);

		return TRUE;
	}

	/**
	 * Add simple message
	 *
	 * @param str $message
	 * @param str $identifier - an ID to give to this message for reference
	 * @param bol $sticky - sticks around for one redirect
	 * @return boolean
	 */
	public function add_message($message, $identifier = FALSE, $sticky = FALSE)
	{
		if ($sticky)
		{
			$current_messages = Session::instance()->get('xsltcontroller_messages');
			if ( ! $current_messages) $current_messages = array();
			$current_messages[] = array('identifier' => $identifier, 'message' => $message);
			Session::instance()->set('xsltcontroller_messages', $current_messages);
		}
		else $this->messages[] = array('identifier' => $identifier, 'message' => $message);


		return TRUE;
	}

	public function after()
	{
		if (class_exists('User'))
		{
			// If page is restricted, check if visitor is logged in, and got access
			// Check if the page is restricted
			$user = User::instance();

			if ( ! isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '';

			if ( ! $user->has_access_to($_SERVER['REQUEST_URI']) && $this->ignore_acl == FALSE)
			{
				if ($this->acl_redirect_url) $this->redirect($this->acl_redirect_url);
				else                         throw new HTTP_Exception_403('403 Forbidden');
			}
		}

		// Format URL params
		$url_params = $_GET;
		foreach ($url_params as $key => $url_param)
		{
			if (is_array($url_param))
			{
				foreach ($url_param as $nr => $data)
				{
					$url_params[$nr.$key] = $data;
					unset($url_params[$key]);
				}
			}
		}

		if ($this->request->secure())
			$protocol = 'https';
		else
			$protocol = 'http';

		$this->meta = array_merge(
			array(
				'protocol'     => $protocol,
				'domain'       => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'],
				'base'         => URL::base(),
				'path'         => $this->request->uri(),
				'action'       => $this->request->action(),
				'controller'   => $this->request->controller(),
				'url_params'   => $url_params,
				'query_string' => $_SERVER['QUERY_STRING'],
				'is_ajax'      => ($this->request->is_ajax()) ? 'true' : 'false',
			),
			$this->meta
		);

		xml::to_XML($this->meta, $this->xml_meta);

		if (class_exists('User'))
		{
			$user = User::instance();

			if ($user->logged_in())
			{
				$user_data = array(
					'@id'      => $user->id,
					'username' => $user->get_username(),
					'data'     => array(),
				);
				foreach ($user->get_user_data() as $field_name => $field_value)
				{
					if ( ! is_array($field_value))
						$field_value = array($field_value);

					foreach ($field_value as $nr => $single_value)
						$user_data['data'][$nr.'field name="'.$field_name.'"'] = $single_value;
				}

				xml::to_XML(array('user_data' => $user_data), $this->xml_meta);
			}
		}

		if (Kohana::$profiling === TRUE)
		{
			xml::to_XML(
				array('benchmark' => Profiler::application()),
				$this->xml_meta
			);
		}

		// Add errors
		foreach ($this->errors as $error)
		{
			if ( ! isset($this->xml_content_errors))
				$this->xml_content_errors = $this->xml_content->appendChild($this->dom->createElement('errors'));

			if ($error['identifier']) $error = array('@id' => $error['identifier'], $error['message']);

			xml::to_XML(array('error' => $error), $this->xml_content_errors);
		}

		// Add messages
		foreach ($this->messages as $message)
		{
			if ( ! isset($this->xml_content_messages))
				$this->xml_content_messages = $this->xml_content->appendChild($this->dom->createElement('messages'));

			if ($message['identifier']) $message = array('@id' => $message['identifier'], $message['message']);

			xml::to_XML(array('message' => $message), $this->xml_content_messages);
		}

		if ($this->auto_render == TRUE)
		{
			// Render the template immediately after the controller method
			$this->render();
		}
	}

	/**
	 * Redirect to another URI. All further execution is terminated
	 *
	 * @param str $uri - If left out, redirects to previous uri.
	 */
	public function redirect($uri = FALSE)
	{
		// Never redirect AJAX calls
		if (Request::current()->is_ajax()) return TRUE;

		if ($uri == FALSE)
		{
			if (Session::instance()->get('redirect', FALSE))
			{
				$redirect = Session::instance()->get('redirect');
				Session::instance()->delete('redirect');
				$uri = $redirect;
			}
			elseif (isset($_SERVER['HTTP_REFERER']))
				$uri = $_SERVER['HTTP_REFERER'];
			else
				$uri = Kohana::$base_url;
		}

		$current_url = URL::base().$this->request->uri();

		if (strlen($_SERVER['QUERY_STRING'])) $current_url .= '?'.$_SERVER['QUERY_STRING'];

		if ($current_url != $uri)
			$this->request->redirect($uri);
	}

	/**
	 * Render the page - this is ran automaticly
	 *
	 * @return Boolean
	 */
	public function render()
	{
		if ($this->xslt_stylesheet == FALSE) $this->xslt_stylesheet = $this->request->controller();

		if ($this->transform === TRUE || $this->transform === FALSE || $this->transform == 'auto')
		{
			$this->dom->insertBefore(
				$this->dom->createProcessingInstruction(
					'xml-stylesheet', 'type="text/xsl" href="' . $this->xslt_path . $this->xslt_stylesheet . '.xsl"'
				),
				$this->xml
			);

			// If the stylesheet name includes an additional path, we need to extract it
			$extra_xslt_path = '';
			$extra_path_parts = explode('/', $this->xslt_stylesheet);
			foreach ($extra_path_parts as $nr => $extra_path_part)
				if ($nr < (count($extra_path_parts) - 1))
					$extra_xslt_path .= $extra_path_part . '/';

			// See if we have a user agent that triggers the server side HTML generation
			$user_agent_trigger = FALSE;
			if (isset($_SERVER['HTTP_USER_AGENT']))
				foreach (Kohana::$config->load('xslt.user_agents') as $user_agent)
					if (strpos($_SERVER['HTTP_USER_AGENT'], $user_agent)) $user_agent_trigger = TRUE;

			if ($this->transform === TRUE || ($this->transform == 'auto' && $user_agent_trigger == TRUE))
			{
				$xslt = new DOMDocument;
				$xslt->load('http://'.$_SERVER['HTTP_HOST'].$this->xslt_path.$this->xslt_stylesheet.'.xsl');

				$proc = new xsltprocessor();
				$proc->importStyleSheet($xslt);

				$this->response->body($proc->transformToXML($this->dom));
			}
			else
			{
				$this->response->headers('Content-Type', 'application/xml; encoding='.Kohana::$charset);
				$this->response->body($this->dom->saveXML());
			}
		}
		elseif ($this->transform == 'XML')
		{
			$this->response->headers('Content-Type', 'application/xml; encoding='.Kohana::$charset);
			$this->response->body($this->dom->saveXML());
		}
		elseif ($this->transform == 'JSON')
		{
			$this->response->headers('Content-type', 'application/json; encoding='.Kohana::$charset);
			$this->response->body(json_encode(xml::to_array($this->dom->saveXML())));
		}

		// Check client cache (ETag) and return 304 if not modified
		//Disabled, since this should be handeled by the application depending on what it wants to do
		//$this->response->check_cache(NULL, $this->request);

		return TRUE;
	}

	/**
	 * Set form data - the data that should fill out forms
	 *
	 * @param arr - form data
	 * @return boolean
	 */
	public function set_formdata($formdata)
	{
		if ( ! isset($this->xml_content_formdata))
			$this->xml_content_formdata = $this->xml_content->appendChild($this->dom->createElement('formdata'));

		$formatted_formdata = $this->format_array($formdata);
		xml::to_XML($formatted_formdata, $this->xml_content_formdata);
		return TRUE;
	}
	private function format_array($formdata)
	{
		$formatted_formdata = array();
		$counter            = 0;
		foreach ($formdata as $field => $data)
		{
			$counter++;
			if (is_array($data))
			{
				$formatted_formdata[$counter.'field'] = array_merge(
					array('@id' => $field),
					$this->format_array($data)
				);
			}
			else
			{
				$formatted_formdata[$counter.'field'] = array(
					'@id'      => $field,
					'$content' => $data,
				);
			}
		}

		return $formatted_formdata;
	}

}