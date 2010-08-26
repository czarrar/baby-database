<?php

/**
* NOT USED
*/
abstract class Zarrar_Db_Temp extends Zend_Db_Table
{
/* add support for a unique column */

	protected static $validateOptions = array(
		'presence_of', 'uniqueness_of', 'emailness', 'length_of', 'format_of'
	);
	
	public function getLink($id = null)
	{
		$link = array();
		
		if(isset($id)) {
			$where = $this->getDefaultAdapter()->quoteInto('id = ?', $id);
			$row = $this->fetchRow($where);
			$link = array($row->id => $row->{$this->_link});
		}
		else {			
			$rows = $this->fetchAll();
			foreach($rows as $row)
				$link[$row->id] = $row->{$this->_link};
		}
		return $link;
	}
	/*
	public function insert(array $data)
	{	
		// Validate data first
		$isValid = $this->validator($data);
		
		if($isValid)
			return parent::insert($data);
		else
			return false ;
	}
	
	public function update(array $data, $where)
	{
		// Validate data first
		$isValid = $this->validator($data);
		
		if($isValid)
			return parent::update($data, $where);
		else
			return false ;
	}
	*/
	public function validator(array $data)
	{
		if(empty($this->_validates))
			return true;
		
		foreach ($this->_validates as $field => $validations) {
			// check to make sure field is in table
			if(!(in_array($field, $this->_cols)))
				throw new Zend_Table_Exception("Given $field not in table.");
			
			// preprare field in $data
			$data[$field] = $this->prepare($data[$field]);
			
			// validate field
			foreach($validations as $key => $value) {
				if (is_string($key)) {
					$validation = $key;
					$options = (is_string($value)) ? array($value) : $value ;				
				} else {
					$validation = $value;
					$options = null;
				}
				
				$validatorChain = new Zend_Validate();
				$validation = "validate_{$validation}";
				$this->$validation($validatorChain, $options);
				
				if ($validatorChain->isValid($data[$field])) {
					// Field is valid
				} else {
					# code...
				}
				
			}
		}
	}
	
	protected function prepare($field)
	{
		$field = trim($field);
		
		return $field;		
	}
	
	protected function validate_required($validator)
	{
		Zend_Loader::loadClass('Zend_Validate_NotEmpty');
		$validator->addValidator(new Zend_Validate_NotEmpty(), true);
	}
	
	protected function validate_uniqueness($validator)
	{
		/* Need to create below validator */
		Zend_Loader::loadClass('Zend_Validate_UniquenessOf');
		$validator->addValidator(new Zend_Validate_UniquenessOf($this), true);
	}
	
	protected function validate_emailness($validator, $options)
	{
		// Set options
		$allow = (isset($options['allow'])) ? $options['allow'] : Zend_Validate_Hostname::ALLOW_DNS ;
		$validateMx = (is_bool($options['validate_mx'])) ? $options['validate_mx'] : false ;
		
		// Load email validator
		Zend_Loader::loadClass('Zend_Validate_EmailAddress');
		$validator->addValidator(new Zend_Validate_EmailAddress($allow, $validateMx));
	}
	
	protected function validate_length_of($validator, $options)
	{
		// Set options
		$min = (is_int($options[0])) ? $options[0] : 0 ;
		$max = (is_int($options[1])) ? $options[1] : null ;
	
		/* Need to convert bottom class to getting lengths of numbers too! */
		Zend_Loader::loadClass('Zend_Validate_StringLength');
		$validator->addValidator(new Zend_Validate_StringLength($min, $max));
	}
	
	protected function validate_format_of($validator, $options)
	{
		Zend_Loader::loadClass('Zend_Validate_Regex');
		$validator->addValidator(new Zend_Validate_Regex($options[0]));
	}
	
	protected function validate_alnum($validator, $options)
	{
		// Set options
		$allowWhiteSpace = (is_bool($options[0])) ? $options[0] : false ;
		
		// Load alphanum validator
		Zend_Loader::loadClass('Zend_Validate_Alnum');
		$validator->addValidator(new Zend_Validate_Alnum($allowWhiteSpace));
	}
	
	protected function validate_alpha($validator, $options)
	{
		// Set options
		$allowWhiteSpace = (is_bool($options[0])) ? $options[0] : false ;
		
		// Load alphanum validator
		Zend_Loader::loadClass('Zend_Validate_Alpha');
		$validator->addValidator(new Zend_Validate_Alpha($allowWhiteSpace));
	}
	
	protected function validate_digits($validator)
	{
		Zend_Loader::loadClass('Zend_Validate_Digits');
		$validator->addValidator(new Zend_Validate_Digits());
	}
	
	protected function validate_between($validator, $options)
	{
		// Set options
		$inclusive = (is_boo1($options['inclusive'])) ? $options['inclusive'] : true ;
		
		// Load between nums validator
		Zend_Loader::loadClass('Zend_Validate_Between');
		$validator->addValidator(new Zend_Validate_Between($options['min'], $options['max'], $inclusive));
	}
	
	protected function validate_greaterthan($validator, $options)
	{
		Zend_Loader::loadClass('Zend_Validate_GreaterThan');
		$validator->addValidator(new Zend_Validate_GreaterThan($options[0]));
	}
	
	protected function validate_lessthan($validator, $options)
	{
		Zend_Loader::loadClass('Zend_Validate_LessThan');
		$validator->addValidator(new Zend_Validate_LessThan($options[0]));
	}
}
