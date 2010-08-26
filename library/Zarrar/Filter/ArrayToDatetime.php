<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

class Zarrar_Filter_ArrayToDatetime implements Zend_Filter_Interface
{
	/**
     * Defined by Zend_Filter_Interface
     *
     * Returns the string $value, capitalizing words as necessary
     *
     * @param  array $value
     * @return string
     */
    public function filter($value)
    {
		if (!(is_array($value)))
			throw new Zend_Filter_Exception("Value must be an array with keys of 'year', 'month', 'day', 'hour', and 'minute'");
			
		// Get date parts
		// @todo: add str_pad(string input, int pad_length, string pad_string, [int pad_type])
		$year = $value['year'];
		$month = $value['month'];
		$day = $value['day'];
		$hour = $value['hour'];
		$minute = $value['minute'];
				
		// Set datetime as string
		if (empty($year) and empty($month) and empty($day) and empty($hour) and empty($minute))
			$newValue = '';
		else
			$newValue = "{$year}-{$month}-{$day} {$hour}:{$minute}:00";
		
        return $newValue;
    }
}