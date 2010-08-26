<?php

require_once 'Zend/View/Helper/FormSelect.php';


class Zarrar_View_Helper_FormSelectDate extends Zend_View_Helper_FormSelect 
{
	// $names = array(year, month, day)
	// $value = "YYYY-MM-DD"
	public function formSelectDate(array $names, $values = null, $html_options = null, $options = null)
	{
		// 0=>YYYY, 1=>MM, 2=>DD
		if (is_string($values)) {
			$values = explode("-", $values);
		} elseif (is_array($values)) {
			$values[0] = ($values['year']) ? $values['year'] : $values[0] ;
			$values[1] = ($values['month']) ? $values['month'] : $values[1] ;
			$values[2] = ($values['day']) ? $values['day'] : $values[2] ;
		}

		// If year has been set have 5 before and 5 after
		if (empty($options['year_start']) and empty($options['year_end'])) {
			$options['year_start'] = $values[0] - 5;
			$options['year_end'] = $values[0] + 5;
		}
		
		$date = new Zend_Date();
				
		$year = $this->view->formSelectYear($names[0], $values[0], $html_options, $options);
		$month = $this->view->formSelectMonth($names[1], $values[1], $html_options, $options);
		$day = $this->view->formSelectDay($names[2], $values[2], $html_options, $options);
		
		$xhtml = "{$month} - {$day} - {$year}";		
		
		return $xhtml;
	}
}


