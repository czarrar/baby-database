<?php

/** Zend_Form */
require_once 'Zend/Form/SubForm.php';

/**
 * Collection of three form fields that are combined:
 * 	- year, month, day as select fields
 * 	- combined into Year-Month-Day
 * Validation done on subfields and combined fields
 *
 * @package Zend_Form
 * @author Zarrar Shehzad
 **/
class Zarrar_Form_SubForm_Date extends Zend_Form_SubForm
{
	// This is the internal fieldname (hidden field) for the date element
	protected $_dateField = "my_date";
	
	/**
	 * Setup defaults for subform + allow for customization
	 * - default elements: year, month, day, date (combine y-m-d)
	 *
	 * @param array $userSettings
	 * @param array $userOptions
	 * @return void
	 **/
	public function __construct($userSettings = null, $userOptions = array())
	{
		###
		# MIGHT WANT TO ALLOW DIRECT CUSTIMIZATION OF THIS AS IF IT WERE THE DATE ELEMENT (so setLabel etc)
		###
		
		// Get year span to display in select field
		$userSettings["years"] = $this->_setYears($userSettings["years"]);
	
		// Get default options + add-on any user options
		// user options will overide any defaults
		$defaultOptions = $this->_getDefaultOptions($userSettings);
		$options = ($userOptions) ? array_merge_recursive($defaultOptions, $userOptions) : $defaultOptions ;
		
		return parent::__construct($options);
	}
	
	public function __call($name, $arguments)
	{
		# Overload functions of element datetime
		$date = $this->getElement($this->_dateField);
		if (method_exists($date, $name))
			return call_user_func_array(array($date, $name), $arguments);
		
		# Whoops!
		$baseClassName = get_class($this);
		$otherClassName = get_class($date);
        throw new Zend_Form_Exception("Could not find method '{$name}' in class '{$baseClassName}' and '{$otherClassName}'!");
	}
	
	/**
	 * Loads only form elements as default decorator
	 *
	 * @return void
	 **/
	public function loadDefaultDecorators()
	{
		$this->setDecorators(array(
			"FormElements"
		));
		
		return;
	}
	
	/**
	 * Will set the date field as required
	 *
	 * @param boolean $flag
	 * @param string $message
	 * @return Zarrar_Form_SubForm_Date for fluent interface
	 **/
	public function setRequired($flag, $message="Date field is required")
	{
		// Get date element (this is the hidden field)
		$dateElement = $this->getElement($this->_dateField);
		
		// Get validators
		$validators = $dateElement->getValidators();
		
		// Clear validators
		$dateElement->clearValidators();
		
		// Set as required
		$dateElement->setRequired(true)
					->setAutoInsertNotEmptyValidator(false)
					->addValidator("NotEmpty", true, array("messages" => $message));
					
		// Add back validators
		$dateElement->addValidators($validators);
		
		return $this;
	}
	
	/**
	 * Disable the form fields
	 *
	 * @return Zarrar_Form_SubForm_Date for fluent interface
	 **/
	public function setDisabled()
	{
		$this->year->setAttrib("disabled", "disabled");
		$this->month->setAttrib("disabled", "disabled");
		$this->day->setAttrib("disabled", "disabled");
		
		return $this;
	}
	
	/**
	 * Range of years to display in years select field
	 *
	 * @param integer|array $years
	 * @return array
	 **/
	protected function _setYears($years = 10)
	{
		if (is_numeric($years)) {
			$curYear = date('Y');
			$years = array(date('Y')-$years, date('Y')+$years);
		}
		
		return $years;
	}
	
	/**
	 * Returns array of default config options
	 *
	 * @param array $settings
	 * @return array
	 **/
	protected function _getDefaultOptions($settings)
	{
		// years 1992-1999, represented as array("1992", "1999")
		$years = $settings["years"];
	
		// Select year options
		$yearOptions = array("" => "Year");
		for ($i=$years[0]; $i <= $years[1]; $i++) {
			$year = str_pad($i, 4, '0', STR_PAD_LEFT);
			$yearOptions[$year] = $year;
		}
		
		// Select month options
		$monthOptions = array("" => "Month");
		for ($i=1; $i < 13; $i++) {
			$month = str_pad($i, 2, '0', STR_PAD_LEFT);
			$monthOptions[$month] = $month;
		}
		
		// Select day options
		$dayOptions = array("" => "Day");
		for ($i=1; $i < 32; $i++) {
			$day = str_pad($i, 2, '0', STR_PAD_LEFT);
			$dayOptions[$day] = $day;
		}
		
		// The real settings!
		$options = array(
			"elements" => array(
				# DATE
				$this->_dateField => array(
					"type" => "hidden",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# Decorators
						"decorators" => array(
							array(
								"element" => "ViewHelper"
							)
						),
						# Validators
						"validators" => array(
							"date" => array(
								"validator" => "Date",
								"options" => array(
									"messages" => array(
										"dateNotYYYY-MM-DD" => "%value% is not a valid date (YYYY-MM-DD)"
									)
								)
							)
						)
					)
				),
				# YEAR
				"year" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $yearOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper",
									"options" => array(
										"separator" => " - ",
										"placement" => "PREPEND"
									)
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							)
						)
					)
				),
				# MONTH
				"month" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $monthOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper",
								"options" => array(
									"separator" => " - ",
									"placement" => "PREPEND"
								)
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							),
							"between" => array(
								"validator" => "Between",
								"options" => array(
									"min" => 1,
									"max" => 12
								)
							)
						)
					)
				),
				# DAY
				"day" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $dayOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper"
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							),
							"between" => array(
								"validator" => "Between",
								"options" => array(
									"min" => 1,
									"max" => 31
								)
							)
						)
					)
				)
			)
		);
		
		return $options;
	}
	
	/**
	 * Validate the form 
	 * (also merges fields into hidden date field)
	 *
	 * @param  array $data 
	 * @return boolean
	 **/
	public function isValid($data)
	{	
		// Get year, month, day
		$year = $data["year"];
		$month = $data["month"];
		$day = $data["day"];
		
		// Combine into Y-m-d
		if (!empty($year) or !empty($month) or !empty($day)) {
			// Combine parts
			$date = "{$year}-{$month}-{$day}";
			$data[$this->_dateField] = $date;
		} else {
			$data[$this->_dateField] = NULL;
		}
		
		// Call parent isValid function
		return parent::isValid($data);
		
		# MIGHT WANT TO DELETE YEAR MONTH DAY IF VALID
	}
	
	/**
	 * Will only return the DATE field and nothing else
	 *
	 * @return string
	 **/
	public function getValues()
	{
		// Check for date field and return
		if ($element = $this->getElement($this->_dateField)) {
			$value = $element->getValue();
			return $value;
		}
		else
			return null;
	}
	
	/**
	 * Will only return the DATE field
	 *
	 * @return string
	 **/
	public function getUnfilteredValues()
	{
		if ($element = $this->getElement($this->_dateField))
			return $element->getUnfilteredValue();
		else
			return null;
	}
	
	/**
	 * Set value for date field
     * 
     * @param  mixed $value 
     * @return Zarrar_Form_SubForm_Date
	 **/
	public function setValue($value)
	{		
		$dateParts = date_parse($value);
		
		$this->getElement("year")->setValue($dateParts["year"]);
		$this->getElement("month")->setValue($dateParts["month"]);
		$this->getElement("day")->setValue($dateParts["day"]);
		
		$date = $dateParts["year"] . "-" . $dateParts["month"] . "-" . $dateParts["day"];

		$this->getElement($this->_dateField)->setValue($date);
		
		return $this;
	}
	
	public function setDefaults($value)
	{		
		// Value is assumed to be a date (do some check to ensure that date?)
		if (is_array($value) and isset($value[$this->_dateField]))
			$this->setValue($value[$this->_dateField]);
		elseif (is_string($value))
			$this->setValue($value);
		return $this;
	}
	
	public function disabled()
	{
		$this->getElement("year")->setAttrib("disabled", "disabled");
		$this->getElement("month")->setAttrib("disabled", "disabled");
		$this->getElement("day")->setAttrib("disabled", "disabled");
		
		return $this;
	}
	
} // END class Zarrar_Form_SubForm_Ages extends Zend_Form_SubForm

