<?php

/**
 * @see Zend_Validate_Abstract
 */
require_once 'Zend/Validate/Abstract.php';


/**
 * @category   Zend
 * @package    Zend_Validate
 */
class Zarrar_Validate_Age extends Zend_Validate_Abstract
{
    /**
     * Validation failure message key for when the value does not appear to be a valid age (YYYY-MM-DD)
     */
    const INVALID        = 'ageInvalid';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID        => "'%value%' does not appear to be a valid age, must be in the format (YYYY-MM-DD)"
    );

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if $value is a valid date of the format YYYY-MM-DD
     *
     * @param  string $value
     * @return boolean
     */
    public function isValid($value)
    {
        $valueString = (string) $value;

        $this->_setValue($valueString);

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valueString)) {
            $this->_error(self::NOT_YYYY_MM_DD);
            return false;
        }

        return true;
    }

}
