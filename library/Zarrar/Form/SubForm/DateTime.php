<?php

/** Zend_Form */
require_once 'Zend/Form/SubForm.php';

/**
 * Combines date and time fields from respective subforms
 * 	Subforms: Zarrar_Form_SubForm_Date and Zarrar_Form_SubForm_Time
 * 	DATETIME in format: YYYY-MM-dd HH:mm
 * Validation done by sub-subforms and again by this subform
 *
 * @package Zend_Form
 * @author Zarrar Shehzad
 **/
class Zarrar_Form_SubForm_DateTime extends Zend_Form_SubForm
{
	
	/**
	 * Setup class, subforms, and set defaults
	 *
	 * @param array $userSettings Passed to sub-subforms + used by this class
	 * @param array $userOptions Passed to sub-subforms
	 * @return void
	 **/
	public function __construct($userSettings = null, $userOptions = array())
	{
		###
		# MIGHT WANT TO ALLOW DIRECT CUSTIMIZATION OF THIS AS IF IT WERE THE DATE ELEMENT (so setLabel etc)
		###
		
		# Get date + time subform
		$date = new Zarrar_Form_SubForm_Date($userSettings, $userOptions);
		$time = new Zarrar_Form_SubForm_Time($userSettings, $userOptions);
		
		# Set + get options
		$defaultOptions = $this->_getDefaultOptions($userSettings);
		$options = ($userOptions) ? array_merge_recursive($defaultOptions, $userOptions) : $defaultOptions ;
		// Run parent contructor
		parent::__construct($options);
		
		# Add date + time subforms
		$this->addSubForm($date, 'date');
		$this->addSubForm($time, 'time');
		
	}
	
	public function __call($name, $arguments)
	{
		# Overload functions of element datetime
		$datetime = $this->getElement("datetime");
		if (method_exists($datetime, $name))
			return call_user_func_array(array($datetime, $name), $arguments);
		
		# Whoops!
		$baseClassName = get_class($this);
		$otherClassName = get_class($datetime);
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
	 * Returns array of default config options
	 *
	 * @param array $settings
	 * @return array
	 **/
	protected function _getDefaultOptions(array $settings)
	{
		
		// Datetime specific settings
		$options = array(
			"elements" => array(
				# DATE
				"datetime" => array(
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
									"format" => "YYYY-MM-dd HH:mm"
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
	 * (also merges fields into hidden datetime field)
	 *
	 * @param  array $data 
	 * @return boolean
	 **/
	public function isValid($data)
	{
		// Get year, month, day, hour, and minute
		$year = $data["date"]["year"];
		$month = $data["date"]["month"];
		$day = $data["date"]["day"];
		$hour = $data["time"]["hour"];
		$minute = $data["time"]["minute"];
		
		// Combine into Y-m-d
		if (!empty($year) or !empty($month) or !empty($day) or !empty($hour) or !empty($minute)) {
			// Combine parts
			$date = "{$year}-{$month}-{$day} {$hour}:{$minute}";
			$data["datetime"] = $date;
		} else {
			$data["datetime"] = NULL;
		}
		
		// Validate
		$valid = $this->getSubForm("date")->isValid($data["date"]);
		$valid = $this->getSubForm("time")->isValid($data["time"]) && $valid;
		
		// Is Valid?
        $this->_errorsExist = !$valid;

        return $valid;
	}
	
	/**
	 * Will only return the DATETIME field and nothing else
	 *
	 * @return string
	 **/
	public function getValues()
	{
		// Check for date field and return
		if ($element = $this->getElement("datetime")) {
			$value = $element->getValue();
			return $value;
		}
		else
			return null;
	}
	
	/**
	 * Will only return the DATETIME field
	 *
	 * @return string
	 **/
	public function getUnfilteredValues()
	{
		if ($element = $this->getElement("datetime"))
			return $element->getUnfilteredValue();
		else
			return null;
	}
	
	/**
	 * Set value for datetime field
     * 
     * @param  string $value 
     * @return Zarrar_Form_SubForm_Datetime
	 **/
	public function setValue($value)
	{
		$this->getElement("datetime")->setValue($value);
		
		$datetime = new Zend_Date($value, "YYYY-MM-dd HH:mm");
		$date = $datetime->get("YYYY-MM-dd");
		$time = $datetime->get("HH:mm");
		
		$this->getSubForm("date")->setValue($date);
		$this->getSubForm("time")->setValue($time);
		
		return $this;
	}
	
	/**
	 * Set default value for time
	 *
	 * @param string $value datetime in format YYYY-MM-dd HH:mm
	 * @return Zarrar_Form_SubForm_Datetime
	 **/
	public function setDefaults($value)
	{
		if ($value["datetime"])
			$this->setValue($value["datetime"]);
		return $this;
	}
	
} // END class Zarrar_Form_SubForm_Ages extends Zend_Form_SubForm

