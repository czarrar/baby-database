<?php

require_once 'Zend/View/Helper/FormSelect.php';

class Zarrar_View_Helper_FormSelectMonth extends Zend_View_Helper_FormSelect
{
	public function formSelectMonth($name, $value = null, $html_options = null, array $options = null)
	{
		$type = (empty($options['month_type'])) ? Zend_Date::MONTH : $option['month_type'] ;
		
		$select_options = array();
		$select_options[''] = 'Month';
		for ($k = 1; $k < 13; $k++) {
			$temp = str_pad($k, 2, '0', STR_PAD_LEFT);
			$select_options[$temp] = $temp;
	    }
	
		if($value == null)
			$value = 'Month';
		
		return $this->formSelect($name, $value, $html_options, $select_options);
	}
}