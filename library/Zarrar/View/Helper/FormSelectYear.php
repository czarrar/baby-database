<?php

require_once 'Zend/View/Helper/FormSelect.php';

class Zarrar_View_Helper_FormSelectYear extends Zend_View_Helper_FormSelect
{
	public function formSelectYear($name, $value = null, $html_options = null, $options = null)
	{
		$type = (empty($options['year_type'])) ? Zend_Date::YEAR : $option['year_type'] ;
		
		$select_options = array();
		$select_options = $this->initYear($value, $type, $options['year_start'], $options['year_end']);

		return $this->formSelect($name, $value, $html_options, $select_options);
	}
	
	protected function initYear($current, $type, $start, $end)
	{
		if(empty($start))
			$start = 2000;
		if(empty($end))
			$end = 2010;
		
		$select_options = array();
		$select_options[''] = 'Year';
			
		switch ($type) {
			case Zend_Date::YEAR:
				for ($k = $start; $k < $end; $k++) {
					$select_options[$k] = $k;
			    }
			    break;
			case Zend_Date::YEAR_SHORT:
				$date = new Zend_Date();
				for ($k = $start; $k < $end; $k++) {
			      $date->setYear($k);
			      $select_options[$date->get(Zend_Date::YEAR_SHORT)] = $date->get(Zend_Date::YEAR_SHORT);
			    }
				break;
			default:
				throw new Zend_View_Exception("Could not initMonth as input \$option was invalid.");
		}
		
		return $select_options;
	}
}