<?php

require_once 'Zend/View/Helper/FormSelect.php';

class Zarrar_View_Helper_FormSelectTime extends Zend_View_Helper_FormSelect
{
	public function formSelectTime($name=null, $value = null, $html_options = null, $options = null)
	{
		// Deal with name
		if (empty($name)) {
			$hourName = "time[hour]";
			$minuteName = "time[minute]";
		} elseif (is_string($name)) {
			$hourName = "{$name}[hour]";
			$minuteName = "{$name}[minute]";
		} elseif (is_array($name)) {
			$hourName = $name[0];
			$minuteName = $name[1];
		} else {
			# throw exception
		}
		
		// Deal with value
		if (is_string($value)) {
			$value = explode(":", $value);
			$hourValue = $value[0];
			$minuteValue = $value[1];
		} elseif (is_array($value)) {
			$hourValue = $value['hour'];
			$minuteValue = $value['minute'];
		} else {
			# throw exception
		}
			
		// Do hour first
		$hourSelectOptions = array("" => "Hour");
		for ($i=0; $i < 24; $i++)
			$hourSelectOptions[str_pad($i, 2, '0', STR_PAD_LEFT)] = str_pad($i, 2, '0', STR_PAD_LEFT);
		$hourSelect = $this->formSelect($hourName, $hourValue, $html_options, $hourSelectOptions);
		
		// Do minutes next
		$minuteSelectOptions = array("" => "Minute");
		if ($options["addBy"]) {
			$add = $options["addBy"];
			unset($options["addBy"]);
		} else {
			$add = 1;
		}
		for ($i=0; $i < 60; ) {
			$minuteSelectOptions[str_pad($i, 2, '0', STR_PAD_LEFT)] = str_pad($i, 2, '0', STR_PAD_LEFT);
			$i = $i + $add;
		}
			
		$minuteSelect = $this->formSelect($minuteName, $minuteValue, $html_options, $minuteSelectOptions);
				
		// Get hour : minute
		$xhtml = "{$hourSelect} : {$minuteSelect}";
		
		return $xhtml;
	}	
}
