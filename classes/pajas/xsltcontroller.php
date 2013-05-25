<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Kohana Controller class. The controller class must be extended to work
 * properly, so this class is defined as abstract.
 */
abstract class Pajas_Xsltcontroller extends Controller
{

	/**
	 * If set to TRUE, render() will automaticly be ran
	 * when the controller is done.
	 */
	public $auto_render = TRUE;

	/**
	 * If set will show page even if the user has no access to it
	 * according to the ACL
	 */
	public $ignore_acl = FALSE;

	/**
	 * URL to redirect to if this URL is restricted
	 * If set to FALSE will give 403 instead
	 */
	public $acl_redirect_url = FALSE;

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
		$session = Session::instance();

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

		xml::to_XML(
			array(
				'protocol'    => (isset($_SERVER['HTTPS'])) ? 'https' : 'http',
				'domain'      => $_SERVER['SERVER_NAME'],
				'base'        => URL::base(),
				'path'        => $this->request->uri(),
				'action'      => $this->request->action(),
				'controller'  => $this->request->controller(),
				'url_params'  => $url_params,
				'is_ajax'     => ($this->request->is_ajax()) ? 'true' : 'false',
			),
			$this->xml_meta
		);

		// Create the content node
		$this->xml_content = $this->xml->appendChild($this->dom->createElement('content'));

		// If any delayed messages exists, add them and clean the session
		if ( ! empty($_SESSION['messages']))
		{
			foreach ($_SESSION['messages'] as $message)
				$this->add_message($message);

			$_SESSION['messages'] = array();
		}
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
			$this->response->body(json_encode(xml::to_Array($this->dom->saveXML())));
		}

		// Check client cache (ETag) and return 304 if not modified
		$this->response->check_cache(NULL, $this->request);

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

		if (Kohana::$profiling === TRUE)
		{
			xml::to_XML(
				array('benchmark' => Profiler::application()),
				$this->xml_meta
			);
		}

		if ($this->auto_render == TRUE)
		{
			// Render the template immediately after the controller method
			$this->render();
		}
	}

	/**
	 * Add a simple error message
	 *
	 * @param str $error
	 * @return boolean
	 */
	public function add_error($error, $identifier = FALSE)
	{
		if ( ! isset($this->xml_content_errors))
			$this->xml_content_errors = $this->xml_content->appendChild($this->dom->createElement('errors'));

		if ($identifier) $error = array('@id' => $identifier, $error);

		xml::to_XML(array('error' => $error), $this->xml_content_errors);
		return TRUE;
	}

	/**
	 * Add form errors
	 *
	 * @param arr $errors - as from Validate::errors()
	 * @return boolean
	 */
	public function add_form_errors($errors)
	{
/*
Array
(
    [username] => Array
        (
            [0] => Valid::not_empty
            [1] => User::username_available
        )

    [password] => Array
        (
            [0] => Valid::not_empty
        )

    // To add a message:
    [username] => 'Username is to ugly'

)*/


		if ( ! isset($this->xml_content_errors))
			$this->xml_content_errors = $this->xml_content->appendChild($this->dom->createElement('errors'));

		if ( ! isset($this->xml_content_errors_form_errors))
			$this->xml_content_errors_form_errors = $this->xml_content_errors->appendChild($this->dom->createElement('form_errors'));

		foreach ($errors as $field => $field_errors)
		{
			if (is_array($field_errors))
			{
				foreach ($field_errors as $field_error)
					xml::to_XML(array($field => $field_error), $this->xml_content_errors_form_errors);
			}
			else xml::to_XML(array($field => array('message' => $field_errors)), $this->xml_content_errors_form_errors);
		}

		return TRUE;
	}

	/**
	 * Add simple message
	 *
	 * @param str $message
	 * @param bol $sticky - sticks around for one redirect
	 * @return boolean
	 */
	public function add_message($message, $sticky = FALSE)
	{
		if ($sticky)
		{
			if ( ! isset($_SESSION['messages']))
				$_SESSION['messages'] = array();

			$_SESSION['messages'][] = $message;
		}
		else
		{
			if ( ! isset($this->xml_content_messages))
				$this->xml_content_messages = $this->xml_content->appendChild($this->dom->createElement('messages'));

			xml::to_XML(array('message' => $message), $this->xml_content_messages);
		}

		return TRUE;
	}

	/**
	 * Redirect to another URI. All further execution is terminated
	 *
	 * @param str $uri - If left out, redirects to previous uri.
	 */
	public function redirect($uri = FALSE)
	{

		if ($uri == FALSE)
		{
			if (isset($_SESSION['redirect']))
			{
				$redirect = $_SESSION['redirect'];
				unset($_SESSION['redirect']);
				$uri = $redirect;
			}
			elseif (isset($_SERVER['HTTP_REFERER']))
				$uri = $_SERVER['HTTP_REFERER'];
			else
				$uri = Kohana::$base_url;
		}

		if (URL::base().$this->request->uri() != $uri)
			$this->request->redirect($uri);
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
		foreach ($formdata as $field => $data)
		{
			if (is_array($data))
			{
				$formatted_formdata[] = array(
					'@id'			 => $field,
					'field' => $this->format_array($data),
				);
			}
			else
			{
				$formatted_formdata[] = array('field' => array(
					'@id'      => $field,
					'$content' => $data,
				));
			}
		}

		return $formatted_formdata;
	}

}