<?php

/**
 * Performs additional processing/filtering on data, generally from Zend_Filter_Input
 *
 * @category	Zarrar
 * @author		Zarrar Shehzad
 **/
class Zarrar_Filter_Data
{

	const MULTIPLE	= 'multiple';
	const UNIQUE	= 'unique';
	
	/**
     * @var array Default values to use when processing data.
     */
    protected $_defaults = array(
        self::MULTIPLE	=> false,
        self::UNIQUE	=> false
    );
	
	/**
	 * Filtered $_rawdata
	 *
	 * @var array
	 **/
	public $data = array();
	
	/**
	 * Error messages
	 *
	 * @var array
	 **/
	public $errors = array();
	
	/**
	 * Warning messages
	 *
	 * @var array
	 **/
	public $warnings = array();
	
	/**
	 * Default filters for Zend_Filter_Input
	 *
	 * @var array
	 **/
	protected $_filters;
	
	/**
	 * Data from Zend_Filter_Input
	 *
	 * @var array
	 **/
	protected $_rawdata = array();
	
	/**
	 * The different sets of data called in processInstance
	 * they represent the keys in $this: _rawdata, data, errors, warnings
	 *
	 * @var array
	 **/
	protected $_categories = array();
	
	/**
	 * Options specific to this class and not for Zend_Filter_Input
	 * keys are categories and values are the settings for each category
	 *
	 * @var array
	 **/
	protected $_options = array();
	
	/**
	 * Constructor
	 *
	 * @param	array $options
	 * @param	array $filters
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function __construct($options=null, $filters=null)
	{
		$this->setOptions($options);
		$this->setFilters($filters);
	}
	
	/**
	 * Filters, validates, and gets errors or warnings
	 * 
	 * The filtered data will be added to $this->_rawdata.
	 * The errors or warnings will be added to $this->errors
	 * or $this->warnings.
	 * 
	 * @param	string $name The name for this set of raw/filtered data.
	 * Can then query for it using $this->getRaw($name) or $this->getFiltered($name)
	 * @param	array $filters
	 * @param	array $validators Must be an array with fields 'errors' and/or 'warnings'
	 * each of which are keys to an array with validators for Zend_Filter_Input
	 * @param	array $data
	 * @param	array $options
	 * Can include options for Zend_Filter_Input and Zarrar_Filter_Data.
	 * Options can include:
	 *	-	'as_array'. Default: false.  If set to true, then
	 *		data will be treated a multi-dimensional array
	 * @return	array Filtered version of $data
	 * @author	Zarrar Shehzad
	 **/
	public function processInstance($name, $filters, $validators, $data, $options=null)
	{	
		// Use default filters?
		if (empty($filters))
			$filters = $this->_filters;
		// Use default options?
		if (empty($options))
			$options = $this->_defaults;
		else
			$options = array_merge($this->_defaults, $options);		
		
		// Set options
		// setting multiple
		$multiple = $options[self::MULTIPLE];
		unset($options[self::MULTIPLE]);
		// setting unique
		$unique = (is_array($options[self::UNIQUE])) ? $options[self::UNIQUE] : array($options[self::UNIQUE]) ;
		unset($options[self::UNIQUE]);
		
		// Add to _options
		if ($multiple) {
			$this->_options[$name][][self::MULTIPLE] = $multiple;
			$this->_options[$name][][self::UNIQUE] = $unique;
		} else {
			$this->_options[$name][self::MULTIPLE] = $multiple;
			$this->_options[$name][self::UNIQUE] = $unique;
		}
		
		// Check that $validators has the keys 'errors' and/or 'warnings'
		if (empty($validators['errors']) && empty($validators['warnings']))
			throw new Exception("Parameter \$validators must have either/both 'errors' or 'warnings' as keys with their values being an array of validators for Zend_Filter_Input");
		
		// Do the processing (filtering and validation)
		if ($multiple) {
			if ($unique)
				$uvalues = array();
			foreach ($data as $key => $raw) {
				if (!(is_numeric($key)))
					throw new Exception("You choose '\$options[{self::MULTIPLE}] = true' when it was not necessary.");
				// Get rawdata, data, errors/warnings and put into this class vars
				$processed = $this->_process($filters, $validators, $raw, $options);
				foreach ($processed as $variable => $value) {
					if (isset($value) and $value != array())
						$this->{$variable}[$name][$key] = $value;
				}
				if ($unique) {
					$ucollector = array();
					foreach ($unique as $ufield)
						$ucollector[] = $this->data[$name][$key][$ufield];
					$uvalues[$key] = implode('', $ucollector);
				}
			}
			if ($unique) {
				$ucounts = array_count_values($uvalues);
				$ukeys = array();
				foreach ($ucounts as $uvalue => $ucount) {
					if ($ucount > 1) {
						$ukeys = array_keys($uvalues, $uvalue);
						print_r($ukeys);
						foreach ($ukeys as $ukey) {
							$this->errors[$name][$ukey][$unique[0]][] = "The '{$name}' field must be unique. The value of '{$uvalue}' has been set by another field";
						}
					}
				}
			}
		} else {
			// Get rawdata, data, errors/warnings and put into this class vars
			$processed = $this->_process($filters, $validators, $data, $options);
			foreach ($processed as $variable => $value) {
				if (isset($value) and $value != array())
					$this->{$variable}[$name] = $value;
			}			
		}
		
		// Return filtered data
		return $this->data[$name];
	}
	
	/**
	 * Sets default options to be used with Zend_Filter_Input
	 *
	 * @param	array $options
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function setOptions($options)
	{
		$this->_defaults = array_merge($this->_defaults, $options);
	}
	
	/**
	 * Sets default filters to be used with Zend_Filter_Input
	 *
	 * @param	array $filters
	 * @return	void
	 * @author	Zarrar Shehzad
	 **/
	public function setFilters($filters)
	{
		$this->_filters = $filters;
	}
	
	/**
	 * Returns final filtered data
	 *
	 * @param	string $name
	 * @param	integer $key
	 * @return	array
	 * @author	Zarrar Shehzad
	 **/
	public function getFiltered($name=null, $key=null)
	{
		if (isset($name)) {
			if (isset($key))
				return $this->data[$name][$key];
			else
				return $this->data[$name];
		} else {
			return $this->data;
		}
	}
	
	/**
	 * Returns post Zend_Filter_Input data
	 *
	 * @param	string $name
	 * @return 	array
	 * @author 	Zarrar Shehzad
	 **/
	public function getRaw($name=null)
	{
		return $this->_getVars('_rawdata', $name);
	}
	
	/**
	 * Get errors
	 *
	 * @param	string $name
	 * @return	boolean|array
	 * @author	Zarrar Shehzad
	 **/
	public function getErrors($name=null)
	{
		return $this->_getVars('errors', $name);
	}
	
	/**
	 * Get warnings
	 *
	 * @param	string $name
	 * @return	boolean|array
	 * @author	Zarrar Shehzad
	 **/
	public function getWarnings($name=null)
	{
		return $this->_getVars('warnings', $name);
		
	}
	
	/**
	 * Does the data have any errors
	 *
	 * @param	string $name
	 * @return	boolean
	 * @author	Zarrar Shehzad
	 **/
	public function hasErrors($name=null)
	{
		$errors = $this->_getVars('errors', $name);
		
		if (empty($errors))
			return false;
		else
			return true;
	}
	
	/**
	 * Does the data have any warnings
	 *
	 * @param	string $name
	 * @return	boolean
	 * @author	Zarrar Shehzad
	 **/
	public function hasWarnings($name=null)
	{
		$warnings = $this->_getVars('warnings', $name);
		
		if (empty($warnings))
			return false;
		else
			return true;
	}
	
	/**
	 * Returns class array
	 *
	 * @param	string $variable
	 * @param	string $name
	 * @return	array
	 * @author	Zarrar Shehzad
	 **/
	protected function _getVars($variable, $name)
	{
		if (isset($name))
			return $this->{$variable}[$name];
		else
			return $this->{$variable};
	}
	
	/**
	 * Takes in raw data with a set of filters, validators, and options
	 * and gives back filtered data and errors and/or warnings
	 *
	 * @param	array $filters
	 * @param	array $validators
	 * @param	array $raw
	 * @param	array $options
	 * @return	array Has keys of 'rawdata', 'data', 'errors', 'warnings' that point to arrays
	 * @author	Zarrar Shehzad
	 **/
	protected function _process($filters, $validators, $raw, $options)
	{	
		// Filter data
		$filtered_input = new Zend_Filter_Input($filters, null, $raw, $options);
		$_rawdata = $filtered_input->getEscaped();
		$data = $this->_filter($name, $_rawdata);

		// Clear vars for validation
		$errors = null;
		$warnings = null;
		
		// Validate data
		foreach ($validators as $type => $validator) {
			$validated_input = new Zend_Filter_Input(null, $validator, $_rawdata, $options);

			// Get errors or warnings
			switch ($type) {
				case 'errors':
					$errors = $this->_errorMessages($validated_input);
					break;
				case 'warnings':
					$warnings = $this->_errorMessages($validated_input);
					break;
				default:
					throw new Exception("Unidentified field ({$type}) in \$validators. Parameter \$validators must have either/both 'errors' or 'warnings' as keys with their values being an array of validators for Zend_Filter_Input");
					break;
			}
		}
		
		return compact('_rawdata', 'data', 'errors', 'warnings');
	}
	
	/**
	 * Removes empty fields from data
	 *
	 * @param	string $name
	 * @param	array $rawdata
	 * @return	array Filtered data
	 * @author	Zarrar Shehzad
	 **/
	protected function _filter($name, $rawdata)
	{
		// Set vars
		$data = array();		
				
		// Take out empty fields
		foreach ($rawdata as $field => $value) {
			if (isset($value) && $value != '')
					$data[$field] = $value;
		}
				
		return $data;
	}
	
	/**
	 * Get the error messages from filtered/validated $this->_input[$name]
	 *
	 * @param Zend_Filter_Input $input
	 * @return array Messages
	 * @author Zarrar Shehzad
	 **/
	protected function _errorMessages($input)
	{
		$errors = null;
		
		// Get error messages from Zend_Filter_Input
		if ($input->hasInvalid() or $input->hasMissing())
			$errors = $input->getMessages();
				
		// If there are any unknown fields, mark as error
		if ($input->hasUnknown()) {
			if(empty($errors))
				$errors = array();
			foreach ($input->getUnknown() as $key => $value) {
				if (is_array($value))
					throw new Exception("Data given to processInstance has array within an array, maybe choose \$options['as_array'] = true.");
				else
					$errors[$key][] = "Field '{$key}' with value '{$value}' not recognized, contact administrator";
			}
		}

		return $errors;
	}
	
} // END class Zarrar_Filter_Data