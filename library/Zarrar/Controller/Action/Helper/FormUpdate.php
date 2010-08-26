<?php

require_once 'Zarrar/Controller/Action/Helper/FormCreate.php';

/**
 * Extends Form helper
 * specifically to hande create part of CRUD
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Controller_Action_Helper_FormUpdate extends Zarrar_Controller_Action_Helper_FormCreate
{

	public function setNewForm($data)
	{
		foreach ($data as $sectionName => $data) {
			if (empty($data))
				continue;
			$this->addData($sectionName, $data);
		}
		
		return $this;
	}
	
	protected function _setNewForm()
	{
		// Only set if formData not alredy set
		if ($this->_manualNewForm)
			return;
	
		foreach ($this->_sectionsToTables as $sectionName => $tableClass) {			
			$table = new $tableClass();
			$where = $this->_getWhere($table);
			
			$rows = $table->fetchAll($where);
			$row = $rows->current();
			
			if (count($rows) < 1) {
				continue;
			} elseif (count($rows) > 1) {
				// warn about other rows
				// and only displaying one row
			}
			
			$this->addFormData($sectionName, $row->toArray());
		}
		
		$this->_setForm();
		
		return;
	}
	
	protected function _getWhere($table)
	{
		$where = array();
		$info = $table->info();
		$tableClass = strtolower(get_class($table));
		$primary_keys = $info['primary'];
		foreach ($primary_keys as $fieldName) {
			$paramToGet = "{$tableClass}_{$fieldName}";
			$fieldValue = $this->getRequest()->getParam($paramToGet);
			if (empty($fieldValue) === false)
				$where["{$fieldName} = ?"] = $fieldValue;
		}
		
		return $where;
	}
	
	protected function _db($tables)
	{		
		foreach ($tables as $key => $table)
			$table->update(null, $this->_getWhere($table));
		
		return;
	}
	
	public function setManualNewForm($toManual)
	{
		if (empty($this->_manualNewForm) and empty($toManual))
			$this->_manualNewForm = False;
		elseif ($toManual)
			$this->_manualNewForm = $toManual;
		
		return $this;
	}
	
	public function processForm($sectionsTOtables=null, $submitCheck=null, $dbFunction=null, $manualNewForm=null)
	{
		$this->setManualNewForm($manualNewForm);
		
		return parent::processForm($sectionsTOtables, $submitCheck, $dbFunction);
	}
}