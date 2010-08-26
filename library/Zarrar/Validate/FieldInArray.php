<?php

/**
* Checks if value(s) exist in array
*/
class Zend_Validate_FieldInArray extends Zend_Validate_Abstract
{

	const NOT_IN_ARRAY = 'notInArray';

	/**
	 * @var array
	 */
	protected $_messageTemplates = array(
	    self::NOT_IN_ARRAY => "'%field%' field not recognized, contact administrator"
	);
	
	/**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'field' => '_field'
    );

	/**
	 * Haystack of possible values
	 *
	 * @var array
	 */
	protected $_haystack;

	/**
	 * Whether a strict in_array() invocation is used
	 *
	 * @var boolean
	 */
	protected $_strict;
	
	/**
	 * Field to look for in the haystack
	 *
	 * @var boolean
	 */
	protected $_field;

	/**
	 * Sets validator options
	 *
	 * @param  array   $haystack
	 * @param  boolean $strict
	 * @return void
	 */
	public function __construct(array $haystack, $field, $strict = false)
	{
	    $this->setHaystack($haystack)
	         ->setStrict($strict);
		$this->setField($field);
	}

	/**
	 * Returns the haystack option
	 *
	 * @return mixed
	 */
	public function getHaystack()
	{
	    return $this->_haystack;
	}

	/**
	 * Sets the haystack option
	 *
	 * @param  mixed $haystack
	 * @return Zend_Validate_FieldInArray Provides a fluent interface
	 */
	public function setHaystack(array $haystack)
	{
	    $this->_haystack = $haystack;
	    return $this;
	}
	
	/**
	 * Returns the field to look for in the array
	 *
	 * @return string
	 */
	public function getField()
	{
		return $this->_field;
	}
	
	/**
	 * Sets the field option
	 *
	 * @param  string $field
	 * @return Zend_Validate_FieldInArray Provides a fluent interface
	 */
	public function setField($field)
	{
		$this->_field = $field;
		return $this;
	}

	/**
	 * Returns the strict option
	 *
	 * @return boolean
	 */
	public function getStrict()
	{
	    return $this->_strict;
	}

	/**
	 * Sets the strict option
	 *
	 * @param  boolean $strict
	 * @return Zend_Validate_InArray Provides a fluent interface
	 */
	public function setStrict($strict)
	{
	    $this->_strict = $strict;
	    return $this;
	}
	
    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $this->_field is contained in the haystack option. If the strict
     * option is true, then the type of $this->_field is also checked.
	 * $value is superfulous here except for including in any error messages
     *
     * @param  mixed $value
     * @return boolean
     */
	public function isValid($value)
	{
		$this->_setValue($value);
        if (!in_array($this->_field, $this->_haystack, $this->_strict)) {
            $this->_error();
            return false;
        }
        return true;
	}
}
