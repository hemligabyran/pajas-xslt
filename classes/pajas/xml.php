<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Xml helper class.
 *
 * @author Lilleman (lilleman@larvit.se) and Jakob (jakob@vinnovera.se) and Olof Larsson (http://oloflarsson.se)
 */
class Pajas_Xml
{

	/**
	 * Convert an object to an array
	 *
	 * @param object $object The object to convert
	 * @return array
	 */
	public static function object_to_array($object)
	{
		if ( ! is_object($object) && ! is_array($object))
			return $object;
		if (is_object($object))
			$object = get_object_vars($object);

		return array_map(array(__CLASS__, __FUNCTION__), $object);
	}

	public static function strip_unprintable_chars($str)
	{
		// Previous stripper also removed newlines. :/
		//return preg_replace('/\p{Cc}+/u', '', $str);
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $str);
	}

	/**
	 * Strip unwanted tags (and all comments)
	 *
	 * @param obj $xml DomDocument
	 * @param arr $accepted_tags - List of tag names that are ok
	 *
	 * @return obj DomDocument
	 */
	public static function strip_unwanted_tags($dom, $accepted_tags)
	{
		// Remove comments
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//comment()') as $comment)
			$comment->parentNode->removeChild($comment);

		foreach ($dom->childNodes as $child_node)
		{
			if ($child_node->nodeType == 1)
			{
				if ( ! in_array($child_node->nodeName, $accepted_tags))
					$dom->removeChild($child_node);
				else
					self::_strip_unwanted_tags($child_node, $accepted_tags);
			}
		}

		return $dom;
	}
	public static function _strip_unwanted_tags($node, $accepted_tags)
	{
		foreach ($node->childNodes as $child_node)
		{
			if ($child_node->nodeType == 1)
			{
				if ( ! in_array($child_node->nodeName, $accepted_tags))
					$node->removeChild($child_node);
				else
					self::_strip_unwanted_tags($child_node, $accepted_tags);
			}
		}
	}

	/**
	 * Convert XML to array
	 *
	 * @param $XML - DOMDocument or string
	 * @return arr
	 */
	public static function to_array($XML)
	{
		if (is_string($XML))
		{
			// Strip XML declaration if any
			if (substr($XML, 0, 5) == '<?xml')
				$XML = trim(substr($XML, strpos($XML, '?>') + 2), "\n");

			$XML_string = '<x>'.$XML.'</x>';
			$XML = new DOMDocument('1.0', 'UTF-8');
			$XML->loadXML(Encoding::fixUTF8($XML_string));

			$array = array();

			// Tags
			foreach ($XML->documentElement->childNodes as $nr => $xml_child)
			{
				if (isset($xml_child->tagName))
				{
					if (isset($array[$xml_child->tagName]))
						$array[$nr.$xml_child->tagName] = self::_to_array($xml_child);
					else
						$array[$xml_child->tagName] = self::_to_array($xml_child);
				}
				elseif ($XML->documentElement->childNodes->length == 1)
					$array = $xml_child->nodeValue;
				elseif (trim($xml_child->nodeValue) != '')
					$array[$nr] = $xml_child->nodeValue;
			}
		}
		else
		{
			$root  = $XML->documentElement->tagName;
			$array = array($root => array());

			// Attributes
			foreach ($XML->documentElement->attributes as $attribute)
				$array[$root]['@'.$attribute->nodeName] = $attribute->nodeValue;

			// Tags
			foreach ($XML->documentElement->childNodes as $nr => $xml_child)
			{
				if (isset($xml_child->tagName))
					if (isset($array[$root][$xml_child->tagName]))
						$array[$root][$nr.$xml_child->tagName] = self::_to_array($xml_child);
					else
						$array[$root][$xml_child->tagName] = self::_to_array($xml_child);
				elseif ($XML->documentElement->childNodes->lenth == 1)
					$array = $xml_child->nodeValue;
				elseif (trim($xml_child->nodeValue) != '')
					$array[$nr] = $xml_child->nodeValue;
			}
		}

		return $array;
	}
	public static function _to_array($DOMNode)
	{
		$array = array();

		// Attributes
		foreach ($DOMNode->attributes as $attribute)
			$array['@'.$attribute->nodeName] = $attribute->nodeValue;

		foreach ($DOMNode->childNodes as $nr => $xml_child)
		{
			if (isset($xml_child->tagName))
				if (isset($array[$xml_child->tagName]))
					$array[$nr.$xml_child->tagName] = self::_to_array($xml_child);
				else
					$array[$xml_child->tagName] = self::_to_array($xml_child);
			elseif ($DOMNode->childNodes->length == 1)
				$array[] = $xml_child->nodeValue;
			elseif (trim($xml_child->nodeValue) != '')
				$array[$nr] = $xml_child->nodeValue;
		}

		return $array;
	}

	/**
	 * Creates a DOMNode or DOMDocument of your array, object or SQL
	 *
	 * Examples:
	 * ===============================================================
	 * Simple example of the two different return values.
	 * As DOMDocument:
	 * <?php
	 * $doc = xml::to_XML(array('root'=>array('fnupp'=>'dah')));
	 * $doc->formatOutput = TRUE;
	 *
	 * echo $doc->saveXML();
	 * ?>
	 *
	 *
	 * As DOMNode:
	 * <?php
	 * $doc = new DOMDocument('1.0', 'UTF-8');
	 * $container = $doc->appendChild($doc->createElement('root'));
	 *
	 * xml::to_XML(array('fnupp'=>'dah'), $container);
	 *
	 * echo $doc->saveXML();
	 * ?>
	 * ===============================================================
	 * If you pass an object it will be converted to an array first and
	 * then treated just like in the examples above.
	 * Example of Obejct to Array conversion:
	 *
	 * $obj = new stdClass;
	 * $obj->foo = new stdClass;
	 * $obj->foo->baz = 'baz';
	 * $obj->bar = 'bar';
	 *
	 * Array
	 * (
	 *     [foo] => Array
	 *     (
	 *         [baz] => baz
	 *     )
	 *     [bar] => bar
	 * )
	 *
	 *
	 * ===============================================================
	 * An SQL-statement, will be grouped like this:
	 * SQL-table (users):
	 * ID | name	| address
	 * -------------------------
	 * 1	| Smith | Nowhere 2
	 * 2	| Doe	 | Somestreet 4
	 *
	 * $data = 'SELECT * FROM users';
	 *
	 * will be transformed to:
	 *
	 * $data = array(
	 *	0 => array(
	 *		'ID'      => '1',
	 *		'name'    => 'Smith',
	 *		'address' => 'Nowhere 2',
	 *	),
	 *	1 => array(
	 *		'ID'      => '2',
	 *		'name'    => 'Doe',
	 *		'address' => 'Somestreet 4',
	 *	)
	 * )
	 * IMPORTANT! This needs Kohana database to be configured
	 * ===============================================================
	 * How the $container works:
	 * xml::to_XML(array('fnupp' => 'dah'))
	 * will output:
	 * <fnupp>dah</fnupp>
	 *
	 * xml::to_XML(array('fnupp' => 'dah'), 'root')
	 * will output:
	 * <root>
	 *	 <fnupp>dah</fnupp>
	 * </root>
	 *
	 * The $container can also be a DOMNode, see the examples with return values for more info
	 * ===============================================================
	 *
	 * Combine DOMNode and plain text sub node creation
	 *
	 * $DOM_document = new DOMDocument('1.0', 'UTF-8');
	 * $root_node    = $DOM_document->appendChild($DOM_document->createElement('root'));
	 *
	 * xml::to_XML(array('fnupp' => 'dah'), array('sub_node' => $root_node))
	 * will output:
	 * <root>
	 *	 <sub_node>
	 *		 <fnupp>dah</fnupp>
	 *	 </sub_node>
	 * </root>
	 *
	 * ===============================================================
	 * How the $group works
	 * IMPORTANT! $group requires $container
	 *
	 * SQL-table (users):
	 * ID | name	| address
	 * -------------------------
	 * 1	| Smith | Nowhere 2
	 * 2	| Doe	 | Somestreet 4
	 *
	 * xml::to_XML('SELECT * FROM users', 'users', 'user');
	 *
	 * will output:
	 *
	 *	<users>
	 *		<user>
	 *			<ID>1</ID>
	 *			<name>Smith</name>
	 *			<address>Nowhere 2</address>
	 *		</user>
	 *		<user>
	 *			<ID>2</ID>
	 *			<name>Doe</name>
	 *			<address>Somestreet 4</address>
	 *		</user>
	 *	</users>
	 * ===============================================================
	 * How the $attributes works
	 * xml::to_XML(array('user'=>array('id'=>2,'name'=>'nisse'),NULL,NULL,array('id'));
	 *
	 * will output:
	 *	<user id="2">
	 *		<name>nisse</name>
	 *	</user>
	 *
	 * This will work no matter how deep in the structure the attribute is
	 *
	 * Alternative to this is to begin the element name with "@", in this case the data would then be:
	 * array('user'=>array('@id'=>2,'name'=>'nisse')
	 * ===============================================================
	 * How $text_values works
	 * xml::to_XML(array('user'=>array('id'=>2,'name'=>'nisse'),NULL,NULL,array('id'),array('name'));
	 *
	 * will output:
	 *	<user id="2">nisse</user>
	 *
	 * This will also work no matter the depth of the element
	 *
	 * Alternative to this is to begin the element name with "$", in this case the data would then be:
	 * array('user'=>array('id'=>2,'$name'=>'nisse')
	 * ===============================================================
	 * How the $alter_code works
	 * This is very cool! For each element, you can execute a snippet of code on its data. For example:
	 * $data = array(
	 *		'blubb' => 'bla',
	 *		'strangeness' => 5,
	 * )
	 *
	 * xml::to_XML($data, 'root', NULL, array(), array(), array(), array('strangeness' => '$str = $name . ' is at level ' . $value; return $str;'));
	 *
	 * will return:
	 *	<root>
	 *		<blubb>bla</blubb>
	 *		<strangeness>strangeness is at level 5</strangeness>
	 *	</root>
	 *
	 * $name and $value is loaded with the element name and element value.
	 * The code snippet will work exactly as a function, hence the "return" in the example.
	 *
	 * To just use an existing function, this is the way to go:
	 * xml::to_XML($data, 'root', NULL, array(), array(), array('strangeness' => 'return substr($blubb,0,2);'));
	 * (Will change "bla" to "bl" in the "blubb"-element)
	 * ===============================================================
	 * Rule for making several identical elements
	 *
	 * $data = array(
	 *		'1blubb' => 233,
	 *		'2blubb' => 993,
	 * )
	 *
	 * xml::to_XML($data, 'root');
	 *
	 * will output:
	 *	<root>
	 *		<blubb>233</blubb>
	 *		<blubb>993</blubb>
	 *	</root>
	 *
	 * $data = array(
	 *		1 => 233,
	 *		2 => 993,
	 * )
	 *
	 * xml::to_XML($data, 'root');
	 *
	 * will output:
	 *	<root>233993</root>
	 *
	 *
	 *
	 * @param str or arr $data - if string, it will be treated as an SQL statement
	 * @param obj $container
	 * @param str $group - Container must be provided for this to work
	 * @param arr $attributes - Array of keys that should always be treated as attributes
	 * @param arr $text_values - Array of keys that should always have their value as value to the parent, ignoring the key
	 * @param arr $xml_fragments - Array of keys that should always have their value interpreted as xml fragments
	 * @param arr $alter_code - keys that should have their values altered by the code given as array value
	 * @return obj - DOMElement
	 */
	public static function to_XML($data, $container = NULL, $group = NULL, $attributes = array(), $text_values = array(), $xml_fragments = array(), $alter_code = array())
	{
		if (is_string($attributes))  $attributes  = array($attributes);
		if (is_string($text_values)) $text_values = array($text_values);

		// Make sure the data is always an array
		if (is_string($data))
		{
			// SQL statement - make it an array
			$pdo  = Kohana_pdo::instance();
			$data = $pdo->query($data)->fetchAll(PDO::FETCH_ASSOC);
		}
		elseif (is_object($data))
		{
			$data = self::object_to_array($data);
		}
		elseif ( ! is_array($data))
		{
			// Neither string or object or array. Humbug!
			return FALSE;
		}

		if ($container === NULL)
			$DOM_document = new DOMDocument('1.0', 'UTF-8');
		elseif (is_string($container))
		{
			$DOM_document  = new DOMDocument('1.0', 'UTF-8');
			$alt_container = $DOM_document->appendChild($DOM_document->createElement($container));
		}
		elseif (is_array($container))
		{
			$parent_container = reset($container);
			$DOM_document     = $parent_container->ownerDocument;
			$alt_container    = $parent_container->appendChild($DOM_document->createElement(key($container)));
		}
		else
			$DOM_document = $container->ownerDocument;

		foreach ($data as $key => $value)
		{

			// Fix the key to a tag
			$tag                = NULL;
			$element_attributes = array();
			foreach (explode(' ',$key) as $part)
			{
				if ( ! $tag)
				{
					$tag = $part;
					while (preg_match('/^[0-9]/', $tag))
					{
						// The first character can not be a numeric char
						// So we strip them off
						$tag = substr($tag, 1);
					}
				}
				else
				{
					// This should be an attribute
					$attribute_name  = NULL;
					$attribute_value = NULL;
					list($attribute_name, $attribute_value) = explode('=', $part);
					if (($attribute_name) && ($attribute_value))
					{
						// Both must exist to make a valid attribute

						// Set the element attributes, strip " or ' from beginning and end of attribute value
						$element_attributes[$attribute_name] = substr($attribute_value, 1, strlen($attribute_value) - 2);
					}
				}
			}

			if ($container === NULL && ! isset($alt_container))
			{
				// If we have no container, the tag must be the root element
				if ($tag == '')
				{
					// And as such, it must be a valid tag
					$tag = 'root';
				}
				$DOM_element = $DOM_document->createElement($tag);
				$DOM_document->appendChild($DOM_element);
				if ( ! is_array($value))
				{
					if (in_array($key,array_keys($alter_code)))
					{
						$func_name = create_function('$value,$name', $alter_code[$key]);
						$value     = $func_name($value, $key);
					}
					$DOM_element->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
				}
				else
					$DOM_element = self::to_XML($value, $DOM_element, NULL, $attributes, $text_values, $xml_fragments, $alter_code);
			}
			else
			{
				// Grouping is activated, lets group this up
				if (isset($group))
				{
					if (isset($alt_container))
						$group_element = $alt_container->appendChild($DOM_document->createElement($group));
					else
						$group_element = $container->appendChild($DOM_document->createElement($group));
				}

				// We have a container, create everything in it
				if ($tag != '')
				{
					// This is a tag, parse and create

					if (substr($tag, 0, 1) == '@' || in_array($tag, $attributes))
					{
						// This is an attribute

						$tag       = str_replace('@','',$tag);
						$attribute = $DOM_document->createAttribute($tag);
						if (in_array($tag, array_keys($alter_code)))
						{
							$func_name = create_function('$value,$name', $alter_code[$tag]);
							$value     = $func_name($value, $tag);
						}
						$attribute->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));

						if (isset($group_element))
							$group_element->appendChild($attribute);
						elseif (isset($alt_container))
							$alt_container->appendChild($attribute);
						else
							$container->appendChild($attribute);
					}
					elseif (substr($tag, 0, 1) == '$' || in_array($tag, $text_values))
					{
						// This tag should be ignored, and its value should be inline text instead
						if (in_array($tag, array_keys($alter_code)))
						{
							$func_name = create_function('$value, $name', $alter_code[$tag]);
							$value     = $func_name($value, $tag);
						}
						if (isset($group_element))
							$group_element->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
						elseif (isset($alt_container))
							$alt_container->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
						else
							$container->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
					}
					elseif (substr($tag, 0, 1) == '?' || in_array($tag, $xml_fragments))
					{
						// This tag should be interpreted as an XML fragment
						$tag = str_replace('?', '', $tag);
						$DOM_element = $DOM_document->createElement($tag);

						if (in_array($tag, array_keys($alter_code)))
						{
							$func_name = create_function('$value,$name', $alter_code[$tag]);
							$value     = $func_name($value, $tag);
						}

						$fragment = $DOM_document->createDocumentFragment();
						$fragment->appendXML($value);
						$DOM_element->appendChild($fragment);

						if (isset($group_element))
							$group_element->appendChild($DOM_element);
						elseif (isset($alt_container))
							$alt_container->appendChild($DOM_element);
						else
							$container->appendChild($DOM_element);
					}
					else
					{
						// This is just a normal tag
						$DOM_element = $DOM_document->createElement($tag);

						if (in_array($tag, array_keys($alter_code)))
						{
							$func_name = create_function('$value,$name', $alter_code[$tag]);
							$value     = $func_name($value, $tag);
						}
						if (!is_array($value))
							$DOM_element->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
						else
							$DOM_element = self::to_XML($value, $DOM_element, NULL, $attributes, $text_values, $xml_fragments, $alter_code);

						if (isset($group_element))
							$group_element->appendChild($DOM_element);
						elseif (isset($alt_container))
							$alt_container->appendChild($DOM_element);
						else
							$container->appendChild($DOM_element);
					}
				}
				else
				{
					/**
					 * When the tag is an empty string (can also be cuz of the array being non-associative i.e. numbers as keys),
					 * it should fold down to the above tag as inline text:
					 * array(
					 *		'foo' => array('blubb')
					 * )
					 * produces:
					 * <foo>blubb</foo>
					 */
					if (!is_array($value))
					{
						// This is a simple string value, just add it
						if (isset($group_element))
							$group_element->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
						elseif (isset($alt_container))
							$alt_container->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
						else
							$container->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
					}
					else
					{
						// This is children-stuff :)
						if (isset($group_element))
							$group_element = self::to_XML($value, $group_element, NULL, $attributes, $text_values, $xml_fragments, $alter_code);
						elseif (isset($alt_container))
							$alt_container = self::to_XML($value, $alt_container, NULL, $attributes, $text_values, $xml_fragments, $alter_code);
						else
							$container     = self::to_XML($value, $container, NULL, $attributes, $text_values, $xml_fragments, $alter_code);
					}
				}
			}

			// Add the attributes
			foreach ($element_attributes as $attribute => $value)
			{
				$attribute = $DOM_element->appendChild($DOM_document->createAttribute($attribute));
				$attribute->appendChild($DOM_document->createTextNode(self::strip_unprintable_chars($value)));
			}

		}

		if (is_object($container)) return $container;
		else                       return $DOM_document;
	}

	/**
	 * Load an XML string and attach it to a DOMNode
	 *
	 * @param str $xml
	 * @param obj $DOM_node
	 */
	public static function XML_to_DOM_node($xml, $DOM_node)
	{
		$xml_inc                     = new DOMDocument('1.0', 'UTF-8');
		$xml_inc->resolveExternals   = TRUE;
		$xml_inc->substituteEntities = TRUE;
		$xml_inc->preserveWhiteSpace = FALSE;

		// The x-tag will be removed in the loop
		$xml_inc->loadXML('<x>'.$xml.'</x>');

		foreach ($xml_inc->documentElement->childNodes as $xml_child)
		{
			$xml_child = $DOM_node->ownerDocument->importNode($xml_child, TRUE);
			$DOM_node->appendChild($xml_child);
		}

		return TRUE;
	}

}