<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

class Zarrar_Filter_ArrayToTime implements Zend_Filter_Interface
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
			throw new Zend_Filter_Exception("Value '{$value}' must be an array with keys of 'hour', and 'minute'");
			
		// Get date parts
		// @todo: add str_pad(string input, int pad_length, string pad_string, [int pad_type])
		$hour = $value['hour'];
		$minute = $value['minute'];
				
		// Set datetime as string
		if (empty($hour) and empty($minute))
			$newValue = '';
		else
			$newValue = "{$hour}:{$minute}:00";
		
        return $newValue;
    }
}

