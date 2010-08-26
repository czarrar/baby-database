<?php

/** Zend_Form */
require_once 'Zend/Form/SubForm.php';

/**
 * Collection of two form fields that are combined:
 * 	- hour, minute as select fields
 * 	- combined into HOUR:MINUTE
 * Validation done on subfields and combined fields
 *
 * @package Zend_Form
 * @author Zarrar Shehzad
 **/
class Zarrar_Form_SubForm_Time extends Zend_Form_SubForm
{
	// This is the internal fieldNAME (hidden field) for the time element
	const TIME_FIELD = "my_time";
	
	/**
	 * Setup defaults for subform + allow for customization
	 * - default elements: hour, minute, time (h:m)
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
	
		// Get default options + add-on any user options
		// user options will overide any defaults
		$defaultOptions = $this->_getDefaultOptions($userSettings);
		$options = array_merge_recursive($defaultOptions, $userOptions);
		
		return parent::__construct($options);
	}
	
	public function __call($name, $arguments)
	{
		# Overload functions of element datetime
		$time = $this->getElement(self::TIME_FIELD);
		if (method_exists($time, $name))
			return call_user_func_array(array($time, $name), $arguments);
		
		# Whoops!
		$baseClassName = get_class($this);
		$otherClassName = get_class($time);
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
	 * Will set the time field as required
	 *
	 * @param boolean $flag
	 * @param string $message
	 * @return Zarrar_Form_SubForm_Date for fluent interface
	 **/
	public function setRequired($flag, $message="Time field is required")
	{
		// Get date element (this is the hidden field)
		$timeElement = $this->getElement(self::TIME_FIELD);

		// Get validators
		$validators = $timeElement->getValidators();

		// Clear validators
		$timeElement->clearValidators();

		// Set as required
		$timeElement->setRequired(true)
					->setAutoInsertNotEmptyValidator(false)
					->addValidator("NotEmpty", true, array("messages" => $message));
		
		// Add back validators
		$timeElement->addValidators($validators);

		return $this;
	}
	
	/**
	 * Disable the form fields
	 *
	 * @return Zarrar_Form_SubForm_Date for fluent interface
	 **/
	public function setDisabled()
	{
		$this->hour->setAttrib("disabled", "disabled");
		$this->minute->setAttrib("disabled", "disabled");
		
		return $this;
	}
	
	/**
	 * Returns array of default config options
	 *
	 * @param array $settings
	 * @return array
	 **/
	protected function _getDefaultOptions($settings)
	{		
		// Select hour options
		$hourOptions = array("" => "Hour");
		$limitTime = ($settings["limitTime"]) ? $settings["limitTime"] : array(0,24) ;
		for ($i=$limitTime[0]; $i < $limitTime[1]; $i++) {
			$hour = str_pad($i, 2, '0', STR_PAD_LEFT);
			$hourOptions[$hour] = $hour;
		}
		
		// Select minute options
		$minuteOptions = array("" => "Minute");
		$addBy = ($settings["addBy"]) ? $settings["addBy"] : 1 ;
		for ($i=0; $i < 60; $i+=$addBy) {
			$minute = str_pad($i, 2, '0', STR_PAD_LEFT);
			$minuteOptions[$minute] = $minute;
		}
		
		// Datetime specific settings
		$options = array(
			"elements" => array(
				# TIME
				self::TIME_FIELD => array(
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
									"format" => "HH:mm"
								)
							)
						)
					)
				),
				# HOUR
				"hour" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $hourOptions,
						# Decorator
						"decorators" => array(array(
							"element" => "ViewHelper",
							"options" => array(
								"separator" => " - ",
								"placement" => "PREPEND"
							)
						)),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							)
						)
					)
				),
				# MINUTE
				"minute" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $minuteOptions,
						# Decorator
						"decorators" => array(array(
							"element" => "ViewHelper",
							"options" => array(
								"separator" => " - ",
								"placement" => "PREPEND"
							)
						)),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
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
	 * (also merges fields into hidden time field)
	 *
	 * @param  array $data 
	 * @return boolean
	 **/
	public function isValid($data)
	{
		// Get year, month, day, hour, and minute
		$hour = $data["hour"];
		$minute = $data["minute"];
		
		// Combine into Y-m-d
		if (!empty($hour) or !empty($minute)) {
			// Combine parts
			$time = "{$hour}:{$minute}";
			$data[self::TIME_FIELD] = $time;
		} else {
			$data[self::TIME_FIELD] = NULL;
		}
		
		// Call parent isValid function
		return parent::isValid($data);		
	}
	
	/**
	 * Will only return the TIME field and nothing else
	 *
	 * @return string
	 **/
	public function getValues()
	{
		// Check for date field and return
		if ($element = $this->getElement(self::TIME_FIELD)) {
			$value = $element->getValue();
			return $value;
		}
		else
			return null;
	}
	
	/**
	 * Will only return the TIME field
	 *
	 * @return string
	 **/
	public function getUnfilteredValues()
	{
		if ($element = $this->getElement(self::TIME_FIELD))
			return $element->getUnfilteredValue();
		else
			return null;
	}
	
	/**
	 * Set value for time field
     * 
     * @param  mixed $value Time in format HH:mm
     * @return Zarrar_Form_SubForm_Time
	 **/
	public function setValue($value)
	{		
		$dateParts = date_parse($value);
		
		$this->getElement("hour")->setValue($dateParts["hour"]);
		$this->getElement("minute")->setValue($dateParts["minute"]);
		
		$time = $dateParts["hour"] . ":" . $dateParts["minute"];
		$this->getElement(self::TIME_FIELD)->setValue($time);
		
		return $this;
	}
	
	/**
	 * Set default value for time
	 *
	 * @param string $value Time in format HH:mm
	 * @return Zarrar_Form_SubForm_Time
	 **/
	public function setDefaults($value)
	{
		// Value is assumed to be a date (do some check to ensure that date?)
		if ($value[self::TIME_FIELD])
			$this->setValue($value[self::TIME_FIELD]);
		return $this;
	}
	
	public function disabled()
	{
		$this->getElement("hour")->setAttrib("disabled", "disabled");
		$this->getElement("minute")->setAttrib("disabled", "disabled");
		
		return $this;
	}
	
} // END class Zarrar_Form_SubForm_Ages extends Zend_Form_SubForm

