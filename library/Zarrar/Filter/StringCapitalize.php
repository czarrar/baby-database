<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

class Zarrar_Filter_StringCapitalize implements Zend_Filter_Interface
{
	/**
     * Defined by Zend_Filter_Interface
     *
     * Returns the string $value, capitalizing words as necessary
     *
     * @param  string $value
     * @return string
     */
    public function filter($value)
    {
        return ucwords((string) $value);
    }
}