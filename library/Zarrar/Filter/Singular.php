<?php

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

/**
 * Get singular word from plural
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Filter_Singular extends Zend_Filter_Interface
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
            $last1 = strtolower($name[$len-1]);
            $last3 = strtolower(substr($name, -3));
            if (strtolower($last3) == 'ies') {
                // entities => entity
                $name = substr($name, 0, -3) . 'y';
            } elseif ($last1 == 's') {
                // things => thing
                $name = substr($name, 0, $len-1);
            }
        }

        return $name;
	}

} // END class Zarrar_Filter_Singular extends Zend_Filter_Interface