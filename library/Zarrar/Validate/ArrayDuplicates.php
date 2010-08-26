<?php

/**
* Gets the frequency of the values in an array
* and gives an error if there are duplicates for a specified value
*/
class Zend_Validate_ArrayDuplicates extends Zend_Validate_Abstract
{

	const DUPLICATE_VALUE = 'duplicateValue';

	/**
	 * @var array
	 */
	protected $_messageTemplates = array(
	    self::DUPLICATE_VALUE => "'%value%' found %count% time(s) in '%field%'"
	);
	
	/**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'count' => '_valueCount',
		'field' => '_field'
    );

	/**
	 * Haystack of possible values
	 *
	 * @var array
	 */
	protected $_haystack;
	
	/**
	 * Count for value of interest
	 *
	 * @var integer
	 */
	protected $_valueCount;
	
	/**
	 * Field(s) or key(s) of interest in array
	 *
	 * @var mixed
	 **/
	protected $_field;

	/**
	 * Sets validator options
	 *
	 * @param  mixed $field
	 * @return void
	 */
	public function __construct($field)
	{
		$this->setField($field);
	}

	/**
	 * Returns the haystack option
	 *
	 * @return array
	 */
	public function getHaystack()
	{
	    return $this->_haystack;
	}

	/**
	 * Sets the haystack option
	 *
	 * @param  array $haystack
	 * @return Zend_Validate_ArrayDuplicates Provides a fluent interface
	 */
	public function setHaystack(array $haystack)
	{
	    $this->_haystack = $haystack;
	    return $this;
	}
	
	/**
	 * Returns the field(s) to look for in the array
	 *
	 * @return mixed
	 */
	public function getField()
	{
		return $this->_field;
	}
	
	/**
	 * Sets the field option
	 *
	 * @param  mixed $field
	 * @return Zend_Validate_ArrayDuplicates Provides a fluent interface
	 */
	public function setField($field)
	{
		if(!(is_array($field)))
			$field = array($field);
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
