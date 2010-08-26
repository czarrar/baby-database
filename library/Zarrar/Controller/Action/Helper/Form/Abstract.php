<?php

/** Zend_Controller_Action_Helper_Abstract */
require_once 'Zend/Controller/Action/Helper/Abstract.php';

/**
 * Process form data
 * which means filters, validates, inserts/updates/searches db
 * and in case of errors helps redisplay information + errors
 *
 * This specific abstract class does not do any filtering + 
 * validations and instead depends on child classes to implement
 * this functionality
 *
 * @author Zarrar Shehzad
 **/
abstract class Zarrar_Controller_Action_Helper_Form_Abstract extends Zend_Controller_Action_Helper_Abstract
{
	/** Possible return values after processing form **/
	const SUCCESS	= 0;
	const ERROR 	= 1;
	const NEW_FORM	= -1;
	
	/**
	 * Input (unprocessed) form data
	 * 
	 * Kept as an associated array:
	 * e.g. array($sectionName => $formSectionData...)
	 *
	 * @var array
	 **/
	protected $_formData = array();
	
	/**
	 * Processed form data, ready for whatevs
	 *
	 * @var array
	 **/
	protected $_data = array();
	
	/**
	 * Errors from validation
	 *
	 * @var array
	 **/
	protected $_errors = array();
	
	/**
	 * Warnings from validation
	 *
	 * @var array
	 **/
	protected $_warnings = array();
	
	/**
	 * If a form has been submitted,
	 * did it have any warnings in it?
	 *
	 * Warnings are known if there is a 
	 * hidden field called 'warnings_given'
	 * submitted with the form. 
	 * 
	 * @var boolean
	 **/
	protected $_warningGiven;
	
	/**
	 * If a form has been submitted,
	 * did it have any errors in it?
	 *
	 * Errors are known if there is a 
	 * hidden field called 'errors_given'
	 * submitted with the form. 
	 * 
	 * @var boolean
	 **/
	protected $_errorGiven;
	
	/**
	 * Process Form
	 * - sets up variables
	 * - does check on variables
	 * - if is post -> process
	 * - else setup new form
	 * 
	 * @param string $submitCheck Name of submit form field.
	 * 	Allows checking for submission of a specific form.
	 * 	Set to NULL by default so there is no checking of this.
	 * 
	 * @return integer
	 *	self::SUCCESS (0): everything is good
	 * 	self::ERROR (1): data was bad
	 *	self::NEW_FORM (-1): nothing was submitted, new form
	 **/
	public function processForm($submitCheck=null)
	{
	
		// Preprocessing of data (defined by child)
		$this->_preProcess();
		
		// Process form if there is posted data and check submitCheck if set
		if ($this->getRequest()->isPost() and (empty($submitCheck) or $this->getRequest()->getPost($submitCheck))) {
			$this->_setFormData($submitCheck);
			return $this->_process();
		}
		
		// Otherwise setup the new form (defined by child class)
		$this->_setNewForm();
		
		return self::NEW_FORM;
	}
	
	public function addSectionName($sectionName)
	{
		if (!(in_array($sectionName, (array) $this->_sectionNames))) 
			$this->_sectionNames[] = $sectionName;
			
		return $this;
	}
	
	/**
	 * Sets form data to process by
	 * putting data into $this->_formData
	 * 
	 * 
 	 * Form data is transformed into an array where
	 * the keys are a $sectionName and values are an
	 * array where each element is form data. This
	 * means that you could have multiple of the
	 * same thing on a form (e.g. multiple phone #s)
	 * 
 	 * Also sees if warnings or errors were given
	 * 
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	protected function _setFormData($submitCheck)
	{
	
		// Get form data
		$request = $this->getRequest();
		$data = $request->getPost();
				
		# Errors are set then warnings must not be set (null in fact)
		# When errors are empty, then set the warnings thing once
		# When errors are empty and warnings set, then put warnings given
		
		// Find out if submitted form data had a previous
		// error or warning attached with it
		$errors = $request->getParam("errors");
		$warnings = $request->getParam("warnings");
		
		if (empty($errors)) {
			if ($warnings)
				$this->_warningsGiven = True;
		} else {
			$this->_errorsGiven = True;
		}		
		
		// Take out errors and warnings
		unset($data["warnings"]);
		unset($data["errors"]);
		
		// Take out submit value
		if ($submitCheck)
			unset($data[$submitCheck]);
					
		// Always have 'default' sectionName
		$this->addSectionName("default");
		
		// Set form data
		// Want to make everything into a multi-dimensional array
		// (e.g. $sectionName => array(0 => array('key' => 'value'), 1 => array('key' => 'value')))			
		foreach ($data as $sectionName => $section) {
			// If sectionValues string, then clump with 'default' section
			// (e.g. $sectionName => 'value')
			if (is_string($section)) {
				$this->_formData["default"][0][$sectionName] = $section;
			} elseif (is_array($section)) {
				$test = array_keys($section);
				if(is_numeric($test[0]))
					$section = array_values($section);
				$this->addSectionName($sectionName);
				$this->addFormData($sectionName, $section);
			}
		}
		
		return True;
	}
	
	/**
	 * Do something before actual form processing
	 * 
	 * @return void
	 **/
	abstract protected function _preProcess();
	
	/**
	 * Does the grunt work of processing
	 * 	1) filter form data
	 *	2) validate form data
	 *	3) save processed data in $this->_data
	 *	4) save any errors in $this->_errors
	 *	5) save any warnings in $this->_warnings
	 *	6) does something with form data (maybe)
	 *
	 * @return boolean
	 **/
	abstract protected function _process();
	
	/**
	 * Setup the new form (nothing has been submitted yet...)
	 **/
	abstract protected function _setNewForm();
	
	/**
	 * Check if there are any errors in form
	 *
	 * @return boolean
	 **/
	public function hasErrors()
	{	
		if(empty($this->_errors))
			return False;
		else
			return True;
	}
	
	/**
	 * Check if there are any warnings in form
	 *
	 * @return boolean
	 **/
	public function hasWarnings()
	{
		if (empty($this->_warnings))
			return False;
		else
		 	return True;
	}
	
	/**
	 * Adds one error into mix
	 *
	 * @param string $error
	 * @param string $fieldName
	 * @param string $sectionName
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	public function addError($error, $fieldName='custom', $sectionName='default')
	{
		$this->_errors[$sectionName][][$fieldName] = (array) $error;
		
		return $this;
	}
	
	/**
	 * Adds an error from a specific section
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	public function addErrors($sectionName, array $errors)
	{
		$this->_errors[$sectionName][] = $errors;
			
		return $this;
	}
	
	/**
	 * Adds a warning from a specific section
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	public function addWarnings($sectionName, array $warnings)
	{
		$this->_warnings[$sectionName][] = $warnings;
			
		return $this;
	}
	
	/**
	 * Adds processed form data from a specific section
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	public function addData($sectionName, array $data)
	{
	
		if ($data[0] != null)
			$this->_data[$sectionName] = $data;
		else
			$this->_data[$sectionName][] = $data;
		
		return $this;
	}
	
	public function pushData($sectionName, $dataKey, $dataVal, $sectionKey=0)
	{
		$this->_data[$sectionName][$sectionKey][$dataKey] = $dataVal;
		
		return $this;
	}
	
	public function addFormData($sectionName, array $data)
	{
		// Multi-dimensional array
		// (e.g. $sectionName => array(0 => array('key' => 'value'), 1 => array('key' => 'value')))
		if ($data[0] != null)
			$this->_formData[$sectionName] = $data;
		// Associative array
		// (e.g. $sectionName => array('key' => 'value', 'key' => 'value'))
		else
			$this->_formData[$sectionName][] = $data;
	}
	
	/**
	 * Sanitize form data for returning to user
	 * 
	 * @param string $getSectionName
	 * @param string $getSectionKey
	 * @return mixed
	 **/
	public function getData($getSectionName=null, $getSectionKey=null)
	{
		// Data to return
		$returnData = array();
		
		// Want specific field (need sectionName and sectionKey)
		if (isset($getSectionName) and isset($getSectionKey)) {
			// Section data
			$sectionData = $this->_data[$getSectionName];
			
			// Return value dependent on if section data array or not
			if (count($sectionData) > 1) {
				foreach ($sectionData as $key => $subSectionData)
					$returnData[$key][$getSectionKey] = $subSectionData[$getSectionKey];
			} else {
				$returnData = $sectionData[0][$getSectionKey];
			}			
		}
		elseif (isset($getSectionName)) {
			// Get section dataset
			$sectionDataset = $this->_data[$getSectionName];
			// Get section data
			$sectionData = (count($sectionDataset) > 1) ? $sectionDataset : $sectionDataset[0] ;
			
			$returnData = $sectionData;
		}
		// Return everything
		else {
			foreach ($this->_data as $sectionName => $sectionDataset) {				
				// Get section data
				$sectionData = (count($sectionDataset) > 1) ? $sectionDataset : $sectionDataset[0] ;
				
				// Check if just empty array
				if ($sectionData == array())
					continue;
			
				// Assign data depending on if interpret as array or not
				if ($sectionName == 'default' or $this->_formAsArray === false)
					$returnData = array_merge($returnData, $sectionData);
				else
					$returnData[$sectionName] = $sectionData;
			}
		}
				
		return $returnData;
	}
	
	/**
	 * Sets up form for redisplay
	 *
	 * @return void
	 **/
	public function setForm()
	{
		$this->_setForm();
		
		return $this;
	}
	
	/**
	 * Sets up form for redisplay (sets view vars)
	 *
	 * @return void
	 **/
	protected function _setForm()
	{	
		$controller = $this->getActionController();
		
		$this->_setViewFormData($controller)
			 ->_setViewErrors($controller)
			 ->_setViewWarnings($controller);
			
	}
	
	/**
	 * Sets form data for redisplay in form
	 * Can be 'sectionName[columnName]' or 'columnName'
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	protected function _setViewFormData($controller)
	{	
		foreach ($this->_data as $sectionName => $sectionDataset) {
			$sectionData = (count($sectionDataset) > 1) ? $sectionDataset : $sectionDataset[0] ;
			
			if ($sectionName != "default")
				$controller->view->assign($sectionName, $sectionData);
			else
				$controller->view->assign($sectionData);
		}
		
		return $this;
	}
	
	/**
	 * Sets errors (if any) for display
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	protected function _setViewErrors($controller)
	{
		if ($this->hasErrors()) {
			$this->getRequest()->setParam("errors", 1);
			$controller->view->haserrors = 1;
			$controller->view->errors = $this->_errors;	
		}
		
		return $this;	
	}
	
	/**
	 * Sets warnings (if any) for display
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	protected function _setViewWarnings($controller)
	{	
		if ($this->hasWarnings()) {			
			if (!($this->hasErrors())) {
				$this->getRequest()->setParam("warnings", 1);
				$controller->view->haswarnings = 1;
			}
			
			$controller->view->warnings = $this->_warnings;
		}
		
		return $this;		
	}
	
	/**
	 * Does everything
	 *
	 * @todo
	 *	1) Might want to return this class
	 * @param array $sectionNames If not given, then assume simple form (not multidata form)
	 * @return boolean
	 **/
	public function direct($sectionNames=null)
	{		
		return $this->processForm($sectionNames);		
	}
} // END class Zend_Controller_Action_Helper_Form extends Zend_Controller_Action_Helper_Abstract