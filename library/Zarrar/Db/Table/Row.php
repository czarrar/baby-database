<?php

/* Zend_Db_Table_Row_Abstract */
require_once 'Zend/Db/Table/Row/Abstract.php';

/**
 * Adds functionality to retrieve a custom method as a variable.
 * For instance, if you want to get a person's name from the
 * db columns of 'first_name' and 'last_name', you could create a
 * function called name() which will display this combination. You
 * would then get the name as you would any other column:
 * <code>$row->name</code>
 * 
 * Also allows for calling of fields from parent table as specified
 * by the user in _refDisplayColumn.
 *
 * @package default
 * @author Zarrar Shehzad
 **/
class Zarrar_Db_Table_Row extends Zend_Db_Table_Row_Abstract
{
	/**
	 * Array with keys:'column names' and values: array('tableClassName'
	 *
	 * @var array
	 **/
	protected $_refDisplayColumns = array();
	
	/**
	 * Contructor:
	 * 	Adds functionality to automatically call another column
	 * 	in a Parent Row.
	 *
	 * @param array $config
	 * @return void
	 **/
	public function __construct(array $config = array())
	{
		$tableClass = $this->_getTable();
		if (isset($tableClass))
			$this->_refDisplayColumns = $tableClass->getRefDisplays();
		
		parent::__construct($config);
	}

	/**
	 * Extension gives ability to
	 *	a) override normal field retrieval
	 *	b) calling a custom property/field
	 * 
	 * @param string $columnName
	 * @return mixed
	 * @author Zarrar Shehzad
	 **/
	public function __get($columnName)
	{
		if (method_exists($this, $columnName))
			return $this->$columnName();
		elseif (array_key_exists($columnName, $this->_refDisplayColumns))
			return $this->getRefDisplayColumn($columnName);
		
		return parent::__get($columnName);
	}
	
	/**
	 * Retrieves column value from Parent Table
	 * 
	 * @param string $columnName
	 * @return mixed
	 * @author Zarrar Shehzad
	 **/
	protected function _getRefColumn($columnName)
	{
		// Get rule key name from user specified array
		$parentTableClass = $this->_refDisplayColumn[$columnName];
		
		// Get parent row
		$parentRow = $this->findParentRow($parentTableClass);
		
		// Return specified column from parent row
		return $parentRow->$columnName;
	}
	
	/**
	 * For a given reference table/rule-key, will fetch a column for display
	 * 	This column is defined in the array $_referenceMap as "refDisplayColumn".
	 * 	This array is found in the table class
	 * 
	 * Usage:
	 * 	<code>
	 *		$_referenceMap = array(
	 * 			'Type' 		=> array(
	 *      		'columns'           => 'contact_type_id',
	 *      		'refTableClass'     => 'ContactType',
	 *      		'refColumns'        => 'id',
	 *  			'refDisplayColumn'	=> 'type'	// Will return this column instead of 'id'
	 *  		),
	 * 		);
	 *	</code>
	 * 
	 * @param string $tableClassName
	 * @param string $ruleKey
	 * @return mixed Reference column value
	 **/
	public function getRefDisplayColumn($columnName)
	{
		$callback = array($this->_getTable(), 'findParentRow');
		$args = $this->_refDisplayColumns[$columnName];
		$parentRow = call_user_func_array($callback, $args);
		
		return $parentRow->{$columnName};
	}
} // END class Zarrar_Db_Table_Row extends Zend_Db_Table_Row_Abstract