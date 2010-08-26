<?php

/**
 * Zend_Db_Table_Abstract
 */
require_once 'Zend/Db/Table/Abstract.php';

/**
 * Zend_Loader
 */
require_once 'Zend/Loader.php';

/**
 * Adds additional functionality to Zend_Db_Table
 * - getName(): returns the db table name
 * - refOptions(): returns an array with values that can populate a form select field for a reference column
 * - extrafields functionality taken/modified from http://naneau.nl/2007/05/05/extra-fields-for-zend_db_table/
 * 
 * @todo
 *	1) add ability to lock table from inserting or updating if setupData returns an error
 * 
 * @author Zarrar Shehzad
 **/
class Zarrar_Db_Table extends Zend_Db_Table_Abstract
{
	const REF_DISPLAY_COLUMN	= "refDisplayColumn";
	const SELECT_COLUMNS		= 'columns';
	const SELECT_WHERE			= 'where';
	
	/**
	 * An array that defines properties for displaying
	 * select options in a form, using getSelectOptions()
	 * 
	 * Keys: SELECT_COLUMNS or SELECT_WHERE
	 * 
	 * Usage:
	 * 	<code>
	 * 		protected $_forSelect = array(
	 * 			// Must be two columns
	 *			'columns'	=> array('id', 'language'),
	 * 			// Filter select with optional where clause
	 *			'where'		=>"to_use = 1"			
	 *		);
	 * 	</code>
	 * 
	 * @var string
	 **/
	protected $_forSelect;
	
	/**
	 * Data processed by setupData()
	 *
	 * @var array
	 **/
	protected $_data;
	
	/**
	 * Validates and Filters Input Data
	 * serves as a wrapper for Zend_Filter_Input
	 *
	 * @var Zarrar_Filter_Data
	 **/
	protected $_filter;
	
	/**
	 * Array of rules for filtering
	 *
	 * @var array
	 **/
	protected $_filterRules = array();
	
	/**
	 * Array of rules for validation that result in errors
	 *
	 * @var array
	 **/
	protected $_errorValidationRules = array();
	
	/**
	 * Array of rules for validation that result in warnings
	 *
	 * @var array
	 **/
	protected $_warningValidationRules = array();
	
	/**
	 * Options for use in Zend_Filter_Input + Zarrar_Filter_Data
	 * extends those in _defaultFilterOptions.
	 *
	 * This array will take precedence if it has the same key as
	 * _defaultFilterOptions.
	 *
	 * @var array
	 **/
	protected $_filterOptions = array();
		
	/**
	 * Default options to use in Zarrar_Filter_Data + Zend_Filter_Input
	 *
	 * @var array
	 **/
	protected $_defaultFilterOptions = array(
		Zend_Filter_Input::INPUT_NAMESPACE		=> array('Zarrar_Validate', 'Zarrar_Filter'),
		Zend_Filter_Input::ESCAPE_FILTER	=> 'StringTrim'
	);
		
	
	/**
	 * Extend constructor,
	 * set default row class to be Zarrar_Db_Table_Row
	 * call setup for subclasses that have extra fields
	 *
	 * @param array $config
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function __construct(array $config = array())
	{
		// Set custom row class
		$config['rowClass'] = (!isset($config['rowClass'])) ? 'Zarrar_Db_Table_Row' : null ;
		
		parent::__construct($config);
	}
	
	/**
	 * Returns the name of the sql table
	 *
	 * @return string
	 * @author Zarrar Shehzad
	 **/
	public function getName()
	{
		return $this->_name;
	}
		
	/**
	 * Processes data to be ready to insert/update into table.
	 * Filters and Validates data using Zend_Filter_Input.
	 * 
	 * @todo
	 * 1) if keys in $data are not those in the db,
	 *	then allow 2nd argument that is an array where
	 *	array keys = keys in $data and array vals = db column names
	 * @param array $data Assoc Array where array keys are 
	 *	column names in db and array values are field values
	 * @return boolean|object Zarrar_Filter_Data
	 * @author Zarrar Shehzad
	 **/
	public function setupData(array $data, $ignoreWarnings=False, $isUpdate=False)
	{		
		// Merge errors and warning validators
		$warnings = ($ignoreWarnings) ? null : $this->_warningValidationRules ;
		$validationRules = array("errors" => ($isUpdate and $this->_useUpdate) ? $this->_updateErrorValidationRules : $this->_errorValidationRules, "warnings" => $warnings);
		
		// Fields of interest (db table columns)
		// only allow these columns to pass through form
		$validFields = array_merge((array) $this->_cols, (array) $this->_additionalFields);
		$validationRules["errors"]['myfields'] = array(
			'ValidFields',
			'fields' => $validFields
		);
		
		// Declare filter
		$this->_filter = new Zarrar_Filter_Data($this->_filterRules, $validationRules, null, array_merge($this->_defaultFilterOptions, $this->_filterOptions));
	
		// Modify any of the incoming data
		// before validation and entering into table
		$data = $this->_modifyData($data);
		
		// Process data (filter and validate)
		$this->_filter->setData($data);
		
		// Store data in table
		$this->_data = $this->_filter->getData();
		
		return $this->_filter;
	}
	
	/**
	 * Returns $data after modifying its elements
	 * 
	 * @param array $data
	 * @return array
	 **/
	protected function _modifyData(array $data)
	{
		return $data;
	}
	
	/**
	 * Gets data processed by setupData()
	 * 
     * @throws Zend_Db_Table_Exception
	 * @return array
	 **/
	public function getFilteredData($key=null)
	{
		if (empty($this->_data)) {
			require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception("Cannot getData() as it is empty!");
		}
	
		if ($key)
			return $this->_data[$key];
		else
			return $this->_data;
	}
	
	public function filterInsert(array $data)
	{
		// Filter out values that are not in the db
		$columns = array_flip((array) $this->_cols);
		$data = array_intersect_key($data, $columns);
		
		// Insert
		$this->insert($data);
	}
	
	public function filterUpdate(array $data, $where)
	{
		// Filter out values that are not in the db
		$columns = array_flip((array) $this->_cols);
		$data = array_intersect_key($data, $columns);
		
		// Update
		return $this->update($data, $where);
	}
	
	/**
	 * Extends insert method
	 * allows data to be inserted from that processed with setupData()
	 * 
	 * @param array $data
	 * @param string|integer $key
	 * @return boolean|integer
	 **/
	public function insert(array $data=array())
    {
		if (isset($this->_data))
			$dataToInsert = array_merge($this->getFilteredData(), $data);
		elseif (empty($data))
			return False;
		else
			$dataToInsert = $data;
				
		return parent::insert($dataToInsert);
	}
	
	public function update(array $data=array(), $where=array(), $columnsNull=array())
    {
		// Setup data to update
		if (isset($this->_data)) {
			$dataToUpdate = array_merge($this->getFilteredData(), $data);
		}
		elseif (empty($data))
			return False;
		else
			$dataToUpdate = $data;
			
		$dataToUpdate = array_merge($columnsNull, $dataToUpdate);
			
		// Setup where clause
		if (empty($where)) {
			// Get where based on primary ids in data
			$primaries = (array) $this->_primary;
			foreach ($primaries as $primary) {
				if (isset($dataToUpdate[$primary])) {
					$where[] = $this->getAdapter()->quoteInto("{$primary} = ?", $dataToUpdate[$primary]);
				}
			}
		}
		elseif (is_array($where) and count($where) == 1) {
			$keys = array_keys($where);
			$values = array_values($where);
			if (is_numeric($keys[0])) {
				$where = $where[0];
			} else {
				$where = $this->getAdapter()->quoteInto($keys[0], $values[0]);
			}
		}
				
		return parent::update($dataToUpdate, $where);
	}
	
	/**
	 * Returns an array to be used in formSelect
	 * 
	 * Array keys are a primary id
	 *	(and the value of a select option)
	 * Array values are another column
	 *	(and what is displayed in the select element to the user)
	 * 
	 * @return array Select options for helper formSelect
	 **/
	public function getSelectOptions($forSelect = null, $order = null, $firstOption = array("" => "Choose"))
	{
		// Set forSelect (array with optional settings)
		$forSelect = (isset($forSelect)) ? $forSelect : $this->_forSelect ;
	
		/* Get DATABASE adapter */
		$db = $this->getDefaultAdapter();
		
		/* Create SELECT statement */
		$select = $db->select()
					 ->distinct();
					
		/* Add FROM clause */
		if (isset($forSelect[self::SELECT_COLUMNS])) {
			$select->from($this->_name, $forSelect[self::SELECT_COLUMNS]);
		}
		// If from clause not specified in array _forSelect, automate process
		else {
			// For tables like 'languages' with 3 columns (id, language, to_use)
			// will want to have id and language in the select options with to_use
			// deciding which rows to include
			if (count($this->_cols) == 3 and in_array('id', $this->_cols) and in_array('to_use', $this->_cols)) {
				$displayColumns = $this->_cols;
				unset($displayColumns['to_use']);
			}
			// For other tables, just table the first two columns
			// (maybe want to change this behavior and throw exception?)
			else {
				$displayColumns = array_slice($this->_cols, 0, 2);
			}
			
			$select->from($this->_name, $displayColumns);
		}
		
		/* Add WHERE clause if necessary */
		// Tables like 'languages' have a column 'to_use'
		// which when set to 0, means row will not be used
		if (isset($forSelect[self::SELECT_WHERE])) {
			$forWheres = (array) $forSelect[self::SELECT_WHERE];
			foreach ($forWheres as $forWhere)
				$select->where($forWhere);
		}
		elseif (in_array("to_use", $this->_cols))
			$select->where("to_use = ?", 1);
		
		if (isset($order)) {
			$select->order($order);
		} else {
			// Order by primary id
			$primary = (array) $this->_primary;
			foreach ($primary as $key)
				$select->order($key);
		}
		
		/* Setup db STATEMENT and fetch everything */
		$stmt = $db->query($select);
		$stmt->setFetchMode(Zend_Db::FETCH_NUM);
		$result = $stmt->fetchAll();
		
		/* Create SELECT OPTIONS */
		// want to transform database query
		// so that 'id' column becomes the key
		// and some other column becomes the value
		$selectOptions = $firstOption;
		foreach ($result as $rowNum => $row)
			$selectOptions[$row[0]] = $row[1];
		
		return $selectOptions;
	}
	
	/**
	 * Fetches values in parent table for form select options.
	 * 
	 * @param string $tableClassName
	 * @param string $ruleKey
	 * @return array
	 **/
	public function getRefSelectOptions($tableClassName, $ruleKey=null, $refDisplayColumn=null)
	{
		// Get reference
		$reference = $this->getReference($tableClassName, $ruleKey);
		
		// Get $refTableClass, $columns, $refColumns
		extract($reference);
		
		// Create forSelect
		if ($refDisplayColumn) {
			$refCol = $refColumns[0];
			$refDisplay = $refDisplayColumn;
			
			$forSelect = array(
				self::SELECT_COLUMNS => array(
					$refCol, $refDisplay
				)
			);
		} else {
			$forSelect = null;
		}
		
		// Get select options from parent table
		$refTable = new $refTableClass();
		$selectOptions = $refTable->getSelectOptions();
		
		return $selectOptions;
	}
	
	public function getRefDisplays()
	{
		$refDisplayColumns = array();
	
		foreach ($this->_referenceMap as $ruleKey => $rules)
			$refDisplayColumns[$rules[self::REF_DISPLAY_COLUMN]] = array($rules[self::REF_TABLE_CLASS], $ruleKey);
		
		return $refDisplayColumns;
	}
	
} // END abstract class Zarrar_Db_Table
