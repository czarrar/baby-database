<?php

class Zarrar_Scaffold extends Zend_Controller_Action {

	/**
	 * Element types to display for a specific data type
	 *
	 * @var array
	 **/
	protected $_typeConv = array(
		"text" => array(
			"smallint", "mediumint", "int", "bigint", "float", "double", "decimal", "char", "varchar", "tinyblob", "tinytext", "date", "datetime", "timestamp", "year", "time"
		),
		"textarea" => array(
			"blob", "text", "mediumblob", "mediumtext", "longblob", "longtext"
		),
		"checkbox" => array(
			"tinyint", "boolean"
		)
	);

	/**
	 * Types of checks
	 *
	 * @var array
	 **/
	protected $_checkTypes = array(
		"Digits"	=> array("smallint", "mediumint", "int", "bigint", "tinyint"),
		"Float"		=> array("double", "float", "decimal"),
		"Date"		=> array("date", "datetime", "timestamp", "year", "time")
	);
	
	// Add column for date check
	
	/**
	 * Table Class Name
	 *
	 * @var string
	 **/
	protected $_name;
	
	/**
	 * Table Class
	 *
	 * @var Zend_Db_Table
	 **/
	protected $_table;
	
	/**
	 * Metadata of table
	 *
	 * @var array
	 **/
	protected $_tableInfo;
	
	/**
	 * Column names for table
	 *
	 * @var array
	 **/
	protected $_columns;
	
	/**
	 * Primary key(s) for table
	 *
	 * @var array
	 **/
	protected $_primary;
	
	/**
	 * Initialize function
	 **/
	function init()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
	
		// Get class name without Controller at end
		$this->_name = str_replace("Controller", "", get_class($this));
		
		// Declare table
		$this->_table = new $this->_name();
		
		// Save table info
		$this->_tableInfo = $this->_table->info();
		
		// Get table name
		$this->_tableName = $this->_tableInfo["name"];
		
		// Save columns
		$this->_columns = $this->_tableInfo["cols"];
		
		// Save primary key(s)
		$this->_primary = $this->_tableInfo['primary'];
			
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		// Get title of page
		// Replace '_' with ' ' and capitalize words
		$title = str_replace("_", " ", $this->_name);
		$this->view->title = ucwords($title);
	}
	
	/**
	 * Saves column information
	 * 	and converts column names into something presentable
	 *
	 * @return array Column names that can be displayed
	 **/
	protected function sanitizeColumns($columns)
	{
		$displayCols = array();
	
		// Save into $this->_cols where keys are column names
		// and values are converted column names
		foreach ($columns as $name) {
			// Replace '_' with ' ' and capitalize words
			$displayName = str_replace("_", " ", $name);
			$displayName = ucwords($displayName);
			
			$displayCols[$name] = $this->view->escape($displayName);
		}
		
		return $displayCols;
	}
	
	public function indexAction()
	{
		$this->_forward("list");
	}
	
	/**
	 * Shows a list of all rows in the table
	 **/
	public function listAction($defaultRender=True)
	{
		$dirs = Zend_Registry::get('dirs');
	
		/* View Variables */
	
		// Add css for tables
		$this->view->headLink()->appendStylesheet(
		"{$dirs->styles}/oranges-in-the-sky.css"
		);	
		$this->view->headLink()
			->prependStylesheet("{$dirs->styles}/sortable_tables.css", "screen, projection");
		
		// Attach scripts for dynamic sorting of table
		$this->view->headScript()
			->prependFile("{$dirs->scripts}/sortable_tables.js")
			->prependFile("{$dirs->scripts}/MochiKit/MochiKit.js");
	
		// Add table to title
		$this->view->title .= " Table";
		
		// Check for to_use
		if (in_array("to_use", $this->_columns))
			$this->view->toUse = True;
		else
			$this->view->toUse = False;
		
		
		/* Create table query */
		
		// Select query
		$select = $this->_table->select();
		
		// Columns in parent table
		$columns = array_combine($this->_columns, $this->_columns);
		
		// Add reference columns where needed		
		$reference = $this->_tableInfo["referenceMap"];
		$joins = array();
		foreach ($reference as $rule => $ruleInfo) {
			if ($ruleInfo["refDisplayColumn"]) {
				// Get this table's name
				$name = $this->_table->getName();
				// Get reference table + name
				$refTable = new $ruleInfo["refTableClass"]();
				$refName = $refTable->getName();
				
				// Get reference column to diplay
				$refDisplay = $ruleInfo["refDisplayColumn"];
				// Get columns that might be arrays
				$refCol = $ruleInfo["refColumns"];
				$refCol = (is_array($refCol)) ? $refCol[0] : $refCol ;
				$col = $ruleInfo["columns"];
				$col = (is_array($col)) ? $col[0] : $col ;
				
				// Save parameters for join, for later
				$joins[] = array(
					"table" 	=> $refName,
					"where" 	=> "{$refName}.{$refCol} = {$name}.{$col}",
					"column"	=> $refDisplay
				);
								
				unset($columns[$col]);
			}
		}
		
		// Get table with only desired columns from from table
		$select->from($this->_table, array_keys($columns));
		
		// Join tables + columns
		foreach ($joins as $join)
			$select->joinLeft($join["table"], $join["where"], $join["column"]);
			
		// Order by primary keys
		foreach ($this->_primary as $primary)
			$select->order($primary);
		
		// Don't want integrity check because want join to work
		$select->setIntegrityCheck(false);
		
		// Check if want to display archived
		$viewArchive = $this->_getParam("view_archive");
		// Text for links at top
		// Set for display of to_use
		switch ($viewArchive) {
			// View only archived (to_use = 0)
			case 1:
				$select->where("{$this->_table->getName()}.to_use = ?", 0);
				$this->view->viewArchived = TRUE;
				break;
			// View only active (to_use = 1)
			case 2:
				$select->where("{$this->_table->getName()}.to_use = ?", 1);
				$this->view->viewActive = TRUE;
				break;
			// View all
			default:
				$this->view->viewAll = TRUE;
				break;
		}

		// Fetch Rows
		$rows = $this->_table->fetchAll($select)->toArray();
		
		// Get what length you should pad the id to have proper sorting		
		if (in_array("id", $this->_primary)) {
			// Get the id of the last row (assuming that they are in order of id)
			$tmpRows = $rows;
			$lastRow = array_pop($tmpRows);
			$lastId = $lastRow["id"];
			// Get the length of id + padding length to have proper sorting
			$this->view->idPad = strlen($lastId);		
		}
		
		/* View Variables */
		
		// Set table items
		if (empty($rows) === false) {
			$this->view->items = $rows;
		
			// Set columns to display
			$this->view->columns = $this->sanitizeColumns(array_keys($this->view->items[0]));
		
			// Set primary key(s)
			$this->view->primary = $this->_primary;
		}
		
		/* Rendering */
		
		if ($defaultRender) {
			$viewRenderer = $this->_helper->viewRenderer;
			$viewRenderer->renderScript("_scaffold/list.phtml");
		}
	}
	
	/**
	 * Gets the primary id params
	 *
	 * @return array
	 **/
	protected function _getPrimaryParams()
	{
		$this->_primarySet = array();
		$this->_where = array();
	
		foreach ($this->_primary as $key) {
			$value = $this->_getParam($key);
			
			if ($value) {
				$this->_primarySet[$key] = $value;
				$this->_where["{$key} = ?"] = $value;
			} else {
				throw new Zend_Controller_Action_Exception("Missing value for primary key '{$key}'");
			}
		}
		
		if (count($this->_where) == 1) {
			$keys = array_keys($this->_where);
			$values = array_values($this->_where);
			$this->_where = $this->_table->getAdapter()->quoteInto($keys[0], $values[0]);
		}
		
		return $this->_primarySet;
	}
	
	/**
	 * Fetches row based on params in url
	 *
	 * @return array
	 **/
	protected function _fetchRow()
	{
		// Make sure all primary fields given and put in array
		$this->_getPrimaryParams();
		// Get row
		$row = $this->_table->fetchRow($this->_where);
		
		// Check to see if found anything
		if ($row)
		 	return $row->toArray();
		else
			throw new Zend_Controller_Action_Exception("Could not find given row");
	}
	
	/**
	 * Shows a single row in the table
	 **/
	public function showAction($defaultRender=True)
	{
		$this->view->controller = $this->getRequest()->getControllerName();
	
		// Add css for tables
		$this->view->headLink()->appendStylesheet("{$this->view->dir_styles}/oranges-in-the-sky.css");
	
		// Get row
		$this->view->item = $this->_fetchRow();
		
		// Get columns
		$this->view->columns = $this->sanitizeColumns($this->_columns);
		
		// Render default row
		if ($defaultRender) {
			$viewRenderer = $this->_helper->viewRenderer;
			$viewRenderer->renderScript("_scaffold/show.phtml");
		}
	}
	
	/**
	 * Shows a form for adding a new row in the table
	 **/
	public function newAction($defaultRender=True)
	{
		// Get form
		$result = $this->_processForm();
				
		if ($result)
			$this->_redirect($this->view->controller);
		
		// Render default list
		if ($defaultRender) {
			$viewRenderer = $this->_helper->viewRenderer;
			$viewRenderer->renderScript("_scaffold/new.phtml");			
		}
	}
	
	/**
	 * Creates a row in table
	 *
	 * @return mixed
	 **/
	protected function _createRow(array $data)
	{
		unset($data["submit"]);
		$primarySet = $this->_table->insert($data);
		
		if ($primarySet) {
			$this->_primarySet = $primarySet;
			return True;
		}
	}
	
	/**
	 * Processes and sets up form
	 *
	 * @return Zend_Form
	 **/
	protected function _processForm($action="new", $defaultData=null, $callback="_createRow")
	{
		// Add css for tables
		$this->view->headLink()->appendStylesheet("{$this->view->dir_styles}/cssform.css");
		
		$this->_cols = $this->sanitizeColumns($this->_columns);
		
		$metadata = $this->_tableInfo["metadata"];
		$reference = $this->_tableInfo["referenceMap"];
		$refCols = array();
		foreach ($reference as $rule => $info) {
			if (is_string($info["columns"])) {
				$col = $info["columns"];
				$refCols[$col] = $rule;
			}
		}
		$form = new Zend_Form;
		$actionUrl = $this->view->url(array("action" => $action));
		$form->setAction($actionUrl)
		     ->setMethod('post')
		     ->setElementFilters(array('StringTrim', 'StripTags'));
		
		foreach ($metadata as $k => $info) {
			// Get column name
			$name = $info["COLUMN_NAME"];
			// Get data type => form field type
			$type = $info["DATA_TYPE"];
			
			// Check if element in any relationship
			if (array_key_exists($name, $refCols)) {
				$elementType = "Select";
				$rule = $refCols[$name];
				$refTable = $reference[$rule]["refTableClass"];
				$selectOptions = $this->_table->getRefSelectOptions($refTable, $rule);
			}
			else if ($name == "to_use") {
				$elementType = "Hidden";
			}
			else if ($name == "id") {
				$elementType = "Text";
			}
			else {			
				foreach ($this->_typeConv as $e => $t) {
					if (in_array($type, $t))
						$elementType = ucfirst($e);
				}
			}
			
			// Check IDENTITY
			if ($info["IDENTITY"]) {
				if($action == "new") {
					unset($this->_cols[$name]);
					continue;
				} else {
					$toDisable = True;
				}
			} else {
				$toDisable = False;
			}
			
			// Set Element
			$elementClass = "Zend_Form_Element_{$elementType}";
			$element = new $elementClass($name);
			
			
			// Set label (not if hidden)
			if ($elementType != "Hidden")
				$element->setLabel($this->_cols[$name]);
						
			// Checkbox specific stuff
			if ($elementType == "Checkbox")
				$element->setAttrib("defaults", 1);
			
			// Disable
			if ($toDisable)
				$element->setAttrib("disabled", "disabled");
			
			// Set select options
			if ($selectOptions)
				$element->setMultiOptions($selectOptions);
			
			// Check Default values
			if ($info["DEFAULT"] || $info["DEFAULT"] === '0') {
				$element->setValue($info["DEFAULT"]);
			}
			// Check NULLABLE
    		elseif (!($info["NULLABLE"])) {
    			$element->setRequired(true);
			}
			
			// Check unsigned
			if ($info["UNSIGNED"])
				$element->addValidator("GreaterThan", true, 0);
				
			// Check length
			if ($info["LENGTH"])
				$element->addValidator("StringLength", true, array(0, $info["LENGTH"]));
				
			// Check datatype
			foreach ($this->_checkTypes as $v => $t) {
				if (in_array($type, $t))
					$element->addValidator($v, true);
			}
			
			// Add default filters (StringTrim and StripTags)
			$element->addFilters(array('StringTrim', 'StripTags'));
			
			// Add decorators
			$element->addDecorators(array(
			    array('Errors'),
			    array('HtmlTag', array("tag" => "nothing")),
			    array('Label'),
			));
			
			// Add element
			$form->addElement($element);
			
			unset($selectOptions);
		}
		
		// Add submit button
		$element = new Zend_Form_Element_Submit("submit");
		$element->addDecorators(array(
		    array('HtmlTag', array("tag" => "nothing")),
		));
		$form->addElement($element);
		
		// Validate submitted data

		if ($this->getRequest()->isPost()) {
			// Get data (@todo: have primary variables hidden)
			$originalData = $this->getRequest()->getPost();
			$data = (empty($this->_primarySet)) ? $originalData : array_merge($originalData, $this->_primarySet) ; 
			// Check data
			if ($form->isValid($data)) {
				// Pass to function
				$result = $this->$callback($originalData);
			} else {
				$result = False;
				// Populate bad data and redisplay
				$form->populate($data);
			}
		} elseif ($defaultData) {
			$form->populate($defaultData);
		}
		
		// Pass to view
		$this->view->form = $form;
		$this->view->columns = $this->_cols;
		
		return $result;
	}
	
	/**
	 * Edits a specified row
	 **/
	public function editAction($defaultRender=True)
	{
		// Get row
		$data = $this->_fetchRow();
		
		// Get form
		$result = $this->_processForm("edit", $data, "_updateRow");
				
		if ($result)
			$this->_redirect($this->view->controller);
				
		// Render default list
		if ($defaultRender) {
			$viewRenderer = $this->_helper->viewRenderer;
			$viewRenderer->renderScript("_scaffold/edit.phtml");			
		}
	}
	
	/**
	 * Creates a row in table
	 *
	 * @return mixed
	 **/
	protected function _updateRow(array $data)
	{
		// Take out submit
		unset($data["submit"]);
		// Update row
		$this->_table->update($data, $this->_where);
		
		return True;
	}
	
	
	/**
	 * Deletes a specified row
	 **/
	public function deleteAction($defaultRender=True)
	{
		// Make sure all primary fields given and put in array
		$primarySet = $this->_getPrimaryParams();
		
		// Delete
		if ($this->_hasParam("confirm")) {
			if (in_array("to_use", $this->_columns)) {
				$data = array("to_use" => 0);
				$this->_table->update($data, $this->_where);
			} else {
				$this->_table->delete($this->_where);
			}
			
			$this->_forward("show", $this->view->controller, null, $this->_primarySet);
			
			return;
		}
		
		$this->view->primarySet = $primarySet;
		
		// Add css for tables
		$this->view->headLink()->appendStylesheet("{$this->view->dir_styles}/oranges-in-the-sky.css");
		
		// Render default list
		if ($defaultRender) {
			$viewRenderer = $this->_helper->viewRenderer;
			$viewRenderer->renderScript("_scaffold/delete.phtml");			
		}
	}
	
	/**
	 * Will undelete a row if there is a column to_use which equals 0
	 *
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function undeleteAction($defaultRender=True)
	{
		// Get row
		$data = $this->_fetchRow();
		
		if (in_array("to_use", $this->_columns) and $data["to_use"] == 0) {
			$this->_table->update(array("to_use" => 1), $this->_where);
			$this->_forward("show", $this->view->controller, null, $this->_primarySet);
		} else {
			$this->_forward("list", $this->view->controller, null, null);
		}
	}
}