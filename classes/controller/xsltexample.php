<?php defined('SYSPATH') OR die('No direct access allowed.');

class Controller_Xsltexample extends Xsltcontroller
{

	public function action_index()
	{
		// Pass some example data to the XML
		xml::to_XML(array('title' => 'This is an example!'), $this->xml_content);
	}

}
