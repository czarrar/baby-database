<?php

/**
* BS class, only meant as a way to have Zend_Filter_Input recognize what fields are known and unknown
* no error messages should display
*/
class Zarrar_Validate_ValidFields extends Zend_Validate_Abstract
{

	const BS = 'bs';

	/**
	 * @var array
	 */
	protected $_messageTemplates = array(
	    self::BS => "CRAP SOMETHING WENT SERIOUSLY WRONG"
	);
	
    /**
     * Defined by Zend_Validate_Interface
     *
     * Always returns true
     *
     * @param  mixed $value
     * @return boolean
     */
	public function isValid($value)
	{
		$this->_setValue($value);
        return true;
	}
}
