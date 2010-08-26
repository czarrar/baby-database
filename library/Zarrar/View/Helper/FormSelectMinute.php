<?php

require_once 'Zend/View/Helper/FormSelect.php';

/**
 * Helper to create 'select' options that have minutes in an hour
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_View_Helper_FormSelectMinute extends Zend_View_Helper_FormSelect
{
	/**
	 * Takes the same options as Zend_View_Helper_FormSelect
	 * except for $options, which for now is thrown away
	 *
	 * @param string|array $name If a string, the element name.  If an
     * array, all other parameters are ignored, and the array elements
     * are extracted in place of added parameters.
     *
     * @param mixed $value The option value to mark as 'selected'; if an
     * array, will mark all values in the array as 'selected' (used for
     * multiple-select elements).
     *
     * @param array|string $attribs Attributes added to the 'select' tag.
     *
     * @param array $options An array of key-value pairs where the array
     * key is the radio value, and the array value is the radio text. IGNORED.
     *
     * @param string $listsep When disabled, use this list separator string
     * between list values.
 	 *
	 * @return string The select tag and options XHTML.
	 * @author Zarrar Shehzad
	 **/
	public function formSelectMinute($name, $value = null, $html_options = null, $options = null, $listsep = "<br />\n")
	{
		$select_options = array();
		$select_options[''] = 'Minute';
		for ($x = 0; $x < 60; $x++)
			$select_options[str_pad($x, 2, '0', STR_PAD_LEFT)] = str_pad($x, 2, '0', STR_PAD_LEFT);
		
		return $this->formSelect($name, $value, $html_options, $select_options, $listsep);
	}
} // END class Zarrar_View_Helper_FormSelectHour