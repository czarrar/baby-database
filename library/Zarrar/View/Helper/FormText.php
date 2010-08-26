<?php

/**
 * Class for extension
 */
require_once 'Zend/View/Helper/FormText.php';


/**
 * Helper to generate a "text" element
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage Helper
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zarrar_View_Helper_FormText extends Zend_View_Helper_FormText
{
    /**
     * Generates a 'text' element.
     * 
     * Extends Zend..FormText in setting the id and value parameter according
	 *	to format of Zarrar_Controller_Action_Helper_Form_Abstract if null.
	 *	Also adds option to specify text for a label.
     *
     * @access public
     *
     * @param string|array $name If a string, the element name.  If an
     * array, all other parameters are ignored, and the array elements
     * are used in place of added parameters.
     * 
     * @param string $labelText The label text for element.
     * 
     * @param mixed $value The element value.
     *
     * @param array $attribs Attributes for the element tag.
     *
     * @return string The element XHTML.
     */
    public function formText($name, $labelText = null, $value = null, $attribs = null)
    {
		// For now if $name is array will let normal formText do processing
		if(is_array($name))
			return parent::formText($name, $value, $attribs);

		// Check if the name is an array (e.g. $sectionName[$id])
		$start = strpos($name, '[');
		if ($start !== false) {
			if (empty($attribs['id'])) {
				$id = substr($name, $start);
				$id = trim($id, "[]");
				$attribs['id'] = $id;
			}
			if ($value == null) {
				$sectionName = substr($name, 0, $start);
				$value = $this->view->{$sectionName}[$id];
			}
		} else {
			if (empty($attribs['id']))
				$attribs['id'] = $name;
			if ($value == null)
				$value = $this->view->$name;
		}
		
		// Get form label
		if (isset($labelText)) {
			$formLabel = $this->view->formLabel($attribs['id'], $labelText);
		} else {
			$formLabel = '';
		}
		
		// Get form text
		$formText = parent::formText($name, $value, $attribs);
		
		return $formLabel . $formText;
    }
}