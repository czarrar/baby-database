<?php

/**
 * Extends Form helper
 * specifically to hande CREATE part of CRUD
 *
 * Usage:
 *	- Define filter rules, validation rules, and filter options in model
 * 	<code>
 * 		$formCreateRow = $this->_helper->FormCreate;
 *		$tableClass = 'Caller';
 * 		$result = $formCreateRow->processForm($tableClass);
 *		// Successful form, redirect to some other page
 * 		if($result) 
 *			$this->_redirect('index');
 * 	</code>
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Controller_Action_Helper_FormCreate extends Zarrar_Controller_Action_Helper_Form_Abstract
{
	/**
	* @todo: add relevant variables here
	**/

	/**
	 * Process the form data
	 * adds setting of db functions and table classes
	 * 
	 * @param string|array $sectionsTOtables An array
	 * 	that maps form data to models (or tables) to which
	 * 	data must be submitted. Array keys are 'sections' of
	 * 	the form that map onto table classes, which are
	 * 	array values. If string, then must be just table
	 * 	class where all form data will be submitted.
	 * 
	 * @param array $dbFunction Callback function during which
	 * 	formData is submitted.
	 * 
	 * @return boolean
	 **/
	public function processForm($sectionsTOtables=null, $submitCheck=null, $dbFunction=null, $updates=array())
	{
		$this->setDbFunction($dbFunction)
			 ->setSectionsToTables($sectionsTOtables);
		
		$this->_whatUpdates = $updates;

		return parent::processForm($submitCheck);
	}

	/**
	 * DESCRIBE
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 **/
	public function setSectionsToTables($sectionsToTables)
	{
		if (empty($this->_sectionsToTables) and empty($sectionsToTables))
			throw new Zend_Controller_Action_Exception("Sections to tables is not set.");
		elseif ($sectionsToTables)
			$this->_sectionsToTables = (is_string($sectionsToTables)) ? array("default" => $sectionsToTables) : $sectionsToTables ;
		
		return $this;
	}
	
	/**
	 * Sets a method that handles inserting form data
	 * into a db table. Default is $this->_db.
	 * 
	 * This option open for multi-forms.
	 * 
     * @return Zend_Controller_Action_Helper_FormCU Provides a fluent interface
	 **/
	public function setDbFunction($dbFunction)
	{
		if (empty($this->_dbFunction) and empty($dbFunction))
			$this->_dbFunction = array($this, "_db");
		elseif ($dbFunction)
			$this->_dbFunction = $dbFunction;			
		
		return $this;
	}
	
	
	/**
	 * Actually process form data!
	 * Filtering + validation occurs in model
	 * using setupData() method in table class
	 *
	 * @return boolean Success or No
	 **/
	protected function _process()
	{
		$isUpdate = (get_class($this) == "Zarrar_Controller_Action_Helper_FormCreate") ? False : True ;
		
		foreach ($this->_sectionsToTables as $sectionName => $tableClass) {
			
			// Get filtered data, errors, warnings
			foreach ($this->_formData[$sectionName] as $sectionData) {
				// Create table
				$table = new $tableClass();
								
				if (in_array($sectionName, $this->_whatUpdates))
					$isUpdate = True;
				
				// Filter data through model
				$filter = $table->setupData($sectionData, $ignoreWarnings = $isUpdate, $isUpdate);
				
				// Get data
				$data = $filter->getData();
			
				// Add form data
				$this->addData($sectionName, $data);

				// Add errors
				if ($filter->hasErrors())
					$this->addErrors($sectionName, $filter->getErrors());

				// Add warnings
				if ($filter->hasWarnings() && !($this->_warningsGiven))
					$this->addWarnings($sectionName, $filter->getWarnings());
				
				// If there is data, save table for later row insertion
				if (empty($data))
					continue;
				else
					$this->_tables[$tableClass][] = $table;				
			}
		}
		if ($this->hasErrors() or $this->hasWarnings()) {
			$this->_setForm();
			return self::ERROR;
		} else {
			$db = Zend_Registry::get('db');
			$db->beginTransaction();
			try {
				if (empty($this->_tables))
					throw new Zend_Controller_Action_Exception("No data to submit");
				$ids = call_user_func($this->_dbFunction, $this->_tables);
				$db->commit();
				return self::SUCCESS;
			} catch (Exception $e) {
				$db->rollback();
				$errors = array(
					'info' => array(
						'ERROR entering information into database',
						"<strong>" . $e->getMessage() . "</strong>"
					)
				);
				$this->addErrors('db_error', $errors);
				$this->_setForm();
				return self::ERROR;
			}
		}		
	}
	
	/**
	 * Default insert method.
	 *
	 * Table will use data already given to it
	 * from setupData() method and insert it.
	 * Can insert multiple rows at once.
	 * 
	 * @return array
	 **/
	protected function _db($tables)
	{
		foreach ($tables as $tableClass => $tableSet) {
			foreach ($tableSet as $table)
				$ids[$tableClass][] = $table->insert();
		}
		
		return $ids;
	}
	
	/**
	 * Preprocess...
	 * checks if # of table class names is same as
	 * # of section names
	 * 
	 * @return void
	 **/
	protected function _preProcess() {
		if (count($this->_tableClasses) != count($this->_sectionNames))
			throw new Zend_Controller_Exception('Number of table classes does not equal number of section name given.');
	}
	
	protected function _setNewForm()
	{

	}
	
	/**
	 * Do everything
	 *
	 * @param string|array $tableClasses
	 * @param string|array $sectionNames
	 * @param array $dbFunction
	 * @return boolean
	 **/
	public function direct($tableClasses, $submitCheck=null, $dbFunction=null)
	{
		return $this->processForm($tableClasses, $sectionNames, $dbFunction);
	}
	
} // END class Zend_Controller_Action_Helper_FormCU extends Zend_Controller_Action_Helper_Form_Abstract