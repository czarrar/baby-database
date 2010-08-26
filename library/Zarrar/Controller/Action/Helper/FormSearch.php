<?php

/** Zend_Controller_Action_Helper_Abstract */
require_once 'Zarrar/Controller/Action/Helper/Form/Abstract.php';

class Zend_Controller_Action_Helper_FormSearch extends Zarrar_Controller_Action_Helper_Form_Abstract {
	
	/**
	 * Filter rules
	 *
	 * @var array
	 **/
	protected $_filterRules = array();
	
	/**
	 * Validation Rules
	 *
	 * @var array
	 **/
	protected $_validationRules = array();
	
	/**
	 * Filter Options
	 *
	 * @var array
	 **/
	protected $_filterOptions = array();
	
	/**
	 * Callback to a method (used in 'call_user_func')
	 *
	 * @var string|method
	 **/
	protected $_modifyMethod;
	
	/**
	 * Process form (filter + validate)
	 * 
	 * @param array $filterRules
	 * @param array $validationRules
	 * @param array $filterOptions
	 * @param sting|array $sectionNames
	 * @return integer See parent method for more details
	 **/
	public function processForm($filterRules=array(), $validationRules=array(), $filterOptions=array(), $modifyMethod=null, $sectionNames=null)
	{
		$this->setFilterRules($filterRules)
			 ->setValidationRules($validationRules)
			 ->setFilterOptions($filterOptions)
			 ->setModifyMethod($modifyMethod);
				
		return parent::processForm($sectionNames);
	}
	
	/**
	 * Does the actual processing of input data
	 * 	Called by processForm().
	 *
	 * @return integer
	 **/
	protected function _process()
	{
		foreach ($this->_sectionNames as $sectionName) {
			if (empty($this->_formData[$sectionName]))
				continue;
			foreach ($this->_formData[$sectionName] as $sectionData) {
				// Create filter class
				$filter = new Zarrar_Filter_Data($this->_filterRules, $this->_validationRules, null, $this->_filterOptions);
				$this->_filter = $filter;
				
				// Modify necessary fields (redirects to user specified function)
				$sectionData = $this->_modifyFields($sectionData);
				
				// Filter
				$filter->filter($sectionData);
				
				// Validate
				$filter->validate($sectionData);
								
				// Add form data
				$this->addData($sectionName, $filter->getData());
				
				// Add errors
				if ($filter->hasErrors())
					$this->addErrors($sectionName, $filter->getErrors());

				// Add warnings
				if ($filter->hasWarnings() && !($this->_warningGiven))
					$this->addWarnings($sectionName, $filter->getWarnings());
			}
		}
		
		if ($this->hasErrors() or $this->hasWarnings()) {
			$this->_setForm();
			return self::ERROR;
		}
		else {
			return self::SUCCESS;
		}
	}
	
	/**
	 * Modifies input data through a user callback function
	 * 	Called by processForm()
	 *
	 * @param array $sectionData
     * @return mixed Depends on return from user callback function
	 **/
	protected function _modifyFields(array $sectionData)
	{
		// Calls user function
		$newSectionData = call_user_func($this->_modifyMethod, $sectionData);
		
		// Returns result of user function
		return $newSectionData;
	}
	
	/**
	 * Default method to modify fields (doesn't do anything except return true)
	 * 	Can be called by _modifyFields()
	 * By default can process incoming date and datetime fields
	 * 
	 * @param array $sectionData
	 * @return boolean Always returns true
	 **/
	public function modifyFields(array $sectionData)
	{
		// Default for handling array of date values
		if (isset($sectionData["date"]) and $sectionData['date']) {
			$sectionData["date"] = $this->_filter->ArrayToDate($sectionData['date'], $type = "date");
		}
		
		return $sectionData;
	}
	
	/**
	 * Sets a callback method that passes input data for modification.
	 * 	This modification occurs after filtering but before validation.
	 * 	This might be necessary if for instance you want to get a babies
	 * 	date of birth from a given age.
	 * 
	 * @param string|array $method
     * @return Zend_Controller_Action_Helper_FormSearch Provides a fluent interface
	 **/
	public function setModifyMethod($method)
	{
		// Default callback method is $this->modifyFields()
		if (empty($method))
			$method = array($this, "modifyFields");
		
		// Set callback
		$this->_modifyMethod = $method;
		
		return $this;
	}
	
	/**
	 * Sets rules for filtering data
	 * 
	 * @param array $filterRules
     * @return Zend_Controller_Action_Helper_FormSearch Provides a fluent interface
	 **/
	public function setFilterRules($filterRules)
	{
		if (empty($filterRules) === false)
			$this->_filterRules = (array) $filterRules;
		
		return $this;
	}
	
	/**
	 * Sets rules for validating data
	 * 
	 * @param array $validationRules
     * @return Zend_Controller_Action_Helper_FormSearch Provides a fluent interface
	 **/
	public function setValidationRules($validationRules)
	{
		if (empty($validationRules) === false) {	
			// No error or warning specific rules,
			// then assume this is an array of only errors
			if (empty($validationRules[Zarrar_Filter_Data::VALIDATOR_ERROR_RULES]) and empty($validationRules[Zarrar_Filter_Data::VALIDATOR_WARNING_RULES]))
				$validationRules = array(Zarrar_Filter_Data::VALIDATOR_ERROR_RULES => $validationRules);
	
			$this->_validationRules = (array) $validationRules;
		}
		
		return $this;
	}
	
	/**
	 * Sets options for using Zend_Filter_Input
	 *
	 * @param array $filterOptions
	 * @return Zend_Controller_Action_Helper_FormSearch Provides a fluent interface
	 **/
	public function setFilterOptions($filterOptions)
	{
		if (empty($filterOptions) === false)
			$this->_filterOptions = (array) $filterOptions;
		
		return $this;
	}
	
	/**
	 * Direct call method (see Zend Framework documentation)
	 *
	 * @param array $filterRules
	 * @param array $validationRules
	 * @param array $filterOptions
	 * @param sting|array $sectionNames
	 * @return Zend_Controller_Action_Helper_FormSearch Provides a fluent interface
	 **/
	public function direct($filterRules=array(), $validationRules=array(), $filterOptions=array(), $modifyMethod=null, $sectionNames=null)
	{		
		$this->processForm($filterRules, $validationRules, $filterOptions, $modifyMethod, $sectionNames);
		
		return $this;
	}
	
	
	/* Below, methods used in this inherited class */
	
	protected function _preProcess()
	{
	}
	
	protected function _setNewForm()
	{
	}
}