<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

class Zarrar_Filter_ArrayToDate implements Zend_Filter_Interface
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
			throw new Zend_Filter_Exception("Value must be an array with keys of 'year', 'month', and 'day'");
						
		// Get date parts
		$year = $value['year'];
		$month = $value['month'];
		$day = $value['day'];
		
		// Set date as string
		if (empty($year) and empty($month) and empty($day))
			$newValue = '';
		else
			$newValue = "{$year}-{$month}-{$day}";
		
        return $newValue;
    }
}