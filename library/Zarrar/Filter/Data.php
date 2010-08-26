<?php

/**
 * Processes an array of data, including filtering and validation
 * using Zend_Filter_Input.
 *
 * Processed data can be accessed directory:
 *	<code>$filter->someField</code>
 * or can be obtained as one array:
 *	<code>$this->getData()</code>
 * 
 * Processed data will be filtered according to desired rules
 * and any empty values will be eliminated.
 * 
 * According to desired validation rules, error and/or warning
 * messages will be generated. They can be accessed via:
 *	<code>$this->getErrors()</code>
 *	<code>$this->getWarnings()</code>
 * Errors and warnings do not require any special validation
 * rules to distinguish them. When setting validation rules
 * ensure the array given has keys of 'errors' and/or 'warnings'
 * which point to array of validation rules. This class does
 * not make distinctions between errors and warnings, which is
 * left to the user.
 * 
 * @todo
 *	1) put sample usage here
 * @author	Zarrar Shehzad
 **/
class Zarrar_Filter_Data
{

	const VALIDATOR_ERROR_RULES 	= 'errors';
	const VALIDATOR_WARNING_RULES	= 'warnings';
	
	/**
	 * Filtered $_rawdata
	 *
	 * @var array
	 **/
	protected $_data = array();
	
	/**
	 * Data from Zend_Filter_Input
	 *
	 * @var array
	 **/
	protected $_alldata = array();
	
	/**
	 * Error messages
	 *
	 * @var array
	 **/
	protected $_errors = array();
	
	/**
	 * Warning messages
	 *
	 * @var array
	 **/
	protected $_warnings = array();
	
	/**
	 * Filters for Zend_Filter_Input
	 * 	Default filter is to strip tags
	 *
	 * @var array
	 **/
	protected $_filters = array(
		'*'	=> 'StripTags'
	);
	
	/**
	 * Validators for Zend_Filter_Input
	 *
	 * @var array
	 **/
	protected $_validators = array();
	
	/**
	 * Options for Zend_Filter_Input
	 * 	Defaults: 1) looks for custom validate and filter classes
	 * 	and 2) when getEscaped() will always trim string returned value
	 *
	 * @var array
	 **/
	protected $_options = array(
		Zend_Filter_Input::INPUT_NAMESPACE			=> array('Zarrar_Validate', 'Zarrar_Filter'),
		Zend_Filter_Input::ESCAPE_FILTER		=> 'StringTrim',
		Zend_Filter_Input::NOT_EMPTY_MESSAGE 	=> "Field '%field%' is required"
	);
	
	/**
	 * Constructor
	 *
	 * @param	array $filters Filter rules
	 * @param	array $validators Validation rules
	 * @param	array $rawdata Unprocessed data
	 * @param	array $options Options
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function __construct($filters=array(), $validators=array(), $rawdata=null, $options=array())
	{
		// Add validator rules
		foreach ($validators as $type => $validator)
			$this->addValidators($type, $validator);
		
		// Adds filter rules and options
		$this->addFilters($filters)
			 ->addOptions($options);
		
		// If rawdata given, then process (filter+validate)
		if ($rawdata)
			$this->setData($rawdata);
	}
	
	/**
	 * Retrieve values in $this->_data
	 * 
	 * @param string $field Array key for $this->_data
	 * @return mixed
	 * @author Zarrar Shehzad
	 * @throws Zend_Filter_Exception
	 **/
	public function __get($field)
	{
		if (!array_key_exists($field, $this->_data)) {
			require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception("Specified field '$field' could not be found.");
        }
        return $this->_data[$field];
	}
	
	/**
	 * Filters raw data, storing any errors and/or warnings
	 * data is filtered in two steps:
	 *	1) Zend_Filter_Input
	 *	2) then internally via _filter(), which takes out fields w/ empty values
	 *
	 * @param	array $raw Unprocessed data
     * @return Zend_Filter_Data Provides a fluent interface
	 **/
	public function setData(array $raw)
	{
		$this->filter($raw)
			 ->validate($raw);
			
		return $this;	
	}
	
	/**
	 * Filters raw input data according to filters specified
	 * 	and removes empty fields from data array
	 * 
	 * @param array $raw 
     * @return Zend_Filter_Data Provides a fluent interface
	 **/
	public function filter(array $raw)
	{
		/* Filter data */
		$filtered_input = new Zend_Filter_Input($this->_filters, null, $raw, $this->_options);
		$this->_alldata = $filtered_input->getEscaped();
		
		// Since Zend_Filter_Input keeps empty values, so take these out
		foreach ($this->_alldata as $field => $value) {
			if (isset($value) and $value != '') {
				$this->_data[$field] = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * Validates input data according to validation rules
	 * 	specified. Usually done after filtering.
	 * 
	 * @param array $raw 
     * @return Zend_Filter_Data Provides a fluent interface
	 **/
	public function validate(array $raw=null)
	{
		/* Filter data if need exists */
		if (empty($this->_alldata))
			$this->filter($raw);
	
		/* Validate data */
		foreach ($this->_validators as $type => $validator) {
			// Use $this->_alldata and not $this->_data
			// Zend_Filter_Input validation needs to know all fields including empty ones
			$validated_input = new Zend_Filter_Input(null, $validator, $this->_alldata, $this->_options);
			// Get errors or warnings
			switch ($type) {
				case self::VALIDATOR_ERROR_RULES:
					$this->_errors = array_merge_recursive((array) $this->_errors, $this->_getErrorMessages($validated_input));
					break;
				case self::VALIDATOR_WARNING_RULES:
					$this->_warnings = array_merge_recursive((array) $this->_warnings, $this->_getWarningMessages($validated_input));
					break;
				default:
					throw new Exception("Unidentified field ({$type}) in \$validators. Parameter \$validators must have either/both 'errors' or 'warnings' as keys with their values being an array of validators for Zend_Filter_Input");
					break;
			}
		}
		
		return $this;
	}
	
	/**
	 * Gets error messages from Zend_Filter_Input post validation.
	 * Also inserts an error if there are any unknown fields.
	 *
	 * @param Zend_Filter_Input $input
	 * @return array Messages
	 * @author Zarrar Shehzad
	 **/
	protected function _getErrorMessages($input)
	{
		$errors = array();
	
		// Get error messages from Zend_Filter_Input
		if ($input->hasInvalid() or $input->hasMissing())
			$errors = $input->getMessages();
				
		// If there are any unknown fields, mark as error
		if ($input->hasUnknown()) {
			foreach ($input->getUnknown() as $key => $value)
				$errors[$key][] = "Field '{$key}' with value '{$value}' not recognized, contact administrator";
		}
		
		return $errors;
	}
	
	protected function _getWarningMessages($input)
	{
		// Get error messages from Zend_Filter_Input
		if ($input->hasInvalid() or $input->hasMissing())
			return $input->getMessages();
		else
			return array();
	}
	
	/**
	 * Adds an error message to $this->_errors
	 * 
	 * This is only for custom error messages and
	 * 	not for messages coming from Zend_Filter_Input
	 * 
	 * @param string $fieldName
	 * @param string $message
	 * @return fluent
	 * @author Zarrar Shehzad
	 **/
	public function addErrorMessage($fieldName, $message)
	{
		$this->_errors[$fieldName][] = $message;
		
		return $this;
	}
	
	public function addWarningMessage($fieldName, $message)
	{
		$this->_warnings[$fieldName][] = $message;
		
		return $this;
	}
	
	/**
	 * Sets filter rules to be used with Zend_Filter_Input
	 *
	 * @param	array $filters
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function addFilters(array $filters=array())
	{
		$this->_filters = array_merge($this->_filters, $filters);
		
		return $this;
	}
	
	/**
	 * Sets validator rules to be used with Zend_Filter_Input
	 *
	 * @param	array $validators
	 * 	array keys: 'errors' and/or 'warnings'
	 * 	array values: array of validation rules
	 * @return	fluent...
	 * @author	Zarrar Shehzad
	 **/
	public function addValidators($type, $rules)
	{
		switch ($type) {
			case self::VALIDATOR_ERROR_RULES:
				if (!(empty($rules)))
					$this->_validators[self::VALIDATOR_ERROR_RULES] = $rules;
				break;
			case self::VALIDATOR_WARNING_RULES:
				if (!(empty($rules)))
					$this->_validators[self::VALIDATOR_WARNING_RULES] = $rules;
				break;
			default:
				throw new Exception("Validator type cannot be '{$type}'. Valid options are '" . self::VALIDATOR_ERROR_RULES . "' or '" . self::VALIDATOR_WARNING_RULES . "'");
				break;
		}
		
		return $this;
	}
	
	/**
	 * Sets options to be used with Zend_Filter_Input
	 *
	 * @param	array $options
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function addOptions(array $options=array())
	{
		$this->_options = array_merge($this->_options, $options);
		
		return $this;
	}
	
	/**
	 * Returns final filtered data
	 *
	 * @return	array
	 * @author	Zarrar Shehzad
	 **/
	public function getData($field=null)
	{
		$data = ($field) ? $this->_data[$field] : $this->_data;
		return $data;
	}
	
	/**
	 * Returns _alldata
	 * (post Zend_Filter_Input processing but pre _filter())
	 *
	 * @return 	array
	 * @author 	Zarrar Shehzad
	 **/
	public function getAllData($field=null)
	{
		$alldata = ($field) ? $this->_alldata[$field] : $this->_alldata;
		return $alldata;
	}
	
	/**
	 * Get errors
	 *
	 * @param	string $name
	 * @return	boolean|array
	 * @author	Zarrar Shehzad
	 **/
	public function getErrors()
	{
		return $this->_errors;
	}
	
	/**
	 * Get warnings
	 *
	 * @param	string $name
	 * @return	boolean|array
	 * @author	Zarrar Shehzad
	 **/
	public function getWarnings()
	{
		return $this->_warnings;
		
	}
	
	/**
	 * Does the data have any errors
	 *
	 * @return	boolean
	 * @author	Zarrar Shehzad
	 **/
	public function hasErrors()
	{
		if (empty($this->_errors))
			return false;
		else
			return true;
	}
	
	/**
	 * Does the data have any warnings
	 *
	 * @return	boolean
	 * @author	Zarrar Shehzad
	 **/
	public function hasWarnings()
	{
		if (empty($this->_warnings))
			return false;
		else
			return true;
	}
	
	public function ArrayToDate($fieldName, $type="date", $overwrite=False)
	{
		// Get field value
		$fieldValue = (is_array($fieldName)) ? $fieldName : $this->getAllData($fieldName) ;
		
		// Get filter
		switch ($type) {
			case 'datetime':
				$filter = new Zarrar_Filter_ArrayToDatetime();
				$format = "YYYY-MM-dd HH:mm:ss";
				break;
			case 'date':
				$filter = new Zarrar_Filter_ArrayToDate();
				$format = "YYYY-MM-dd";
				break;
		}
		// Perform filter (getting string from array)
		$date = $filter->filter($fieldValue);
		
		// If it isn't empty then validate
		if (empty($date) === false) {
			$validDate = new Zend_Validate_Date($format);
			if (!($validDate->isValid($date)))
				$this->addErrorMessage($type, "Invalid date/time entry '{$date}', please try again.");
		}
		
		// Save new field
		if ($overwrite) {
			$this->_alldata[$fieldName] = $date;
			$this->_data[$fieldName] = $date;
		}
		
		// Return new value in case user needs it
		return $date;
	}
	
} // END class Zarrar_Filter_Data