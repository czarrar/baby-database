<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

/**
 * Get plural word from singular
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Filter_Plural extends Zend_Filter_Interface
{

	/**
	 * Defined by Zend_Filter_Interface
	 * 
	 * Returns singular word from value
	 *
	 * @param string $value
	 * @return string
	 * @author Zarrar Shehzad
	 **/
	public function filter($value)
	{
		$name = $value;
	
        $len = strlen($name);
        if ($len > 0) {
            $last = strtolower($name[$len-1]);
            if ($last == 'y') {
                // entity => entities
                $name = substr($name, 0, -1) . 'ies';
            } elseif ($last != 's') {
                // thing => things
                $name .= 's';
            }
        }

        return $name;
	}

} // END class Zarrar_Filter_Plural extends Zend_Filter_Interface