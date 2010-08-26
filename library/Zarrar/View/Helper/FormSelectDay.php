<?php

require_once 'Zend/View/Helper/FormSelect.php';

class Zarrar_View_Helper_FormSelectDay extends Zend_View_Helper_FormSelect
{
	public function formSelectDay($name, $value = null, $html_options = null, $options = null)
	{	
		$select_options = array();
		$select_options[''] = 'Day';
		for ($x = 1; $x < 32; $x++)
			$select_options[str_pad($x, 2, '0', STR_PAD_LEFT)] = str_pad($x, 2, '0', STR_PAD_LEFT);
		
		return $this->formSelect($name, $value, $html_options, $select_options);
	}
	
	/* include a function to be able to return the day of the week */
}
