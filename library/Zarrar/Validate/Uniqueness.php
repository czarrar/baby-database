<?php

/**
* Checks if value(s) exist in database
* @todo
* 	1) Allow uniqueness to return the primary id(s) of similar/same tables
* 	2) Switch search to select with LIKE in query and not 
*/
class Zarrar_Validate_Uniqueness extends Zend_Validate_Abstract
{

	const NOT_UNIQUE = 'notUnique';

	/**
	 * @var array
	 */
	protected $_messageTemplates = array(
	    self::NOT_UNIQUE 	=> "Values (%value%) already exists in database. See '%link%'.",
	);
	
	/**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'link' => '_link'
    );

	protected $_link;
		
	protected $_table;
	
	protected $_key;

	public function __construct($tableClassName, $key=null)
	{
		$this->_table = new $tableClassName();
		
		if (isset($key) and is_string($key))
			$this->_key = $key;
	}
	
	/**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value is a valid integer
     *
     * @param  mixed $value
     * @return boolean
     */
	public function isValid($value)
	{
		// Set value for error message AND where command for search
		$valueString = '';
		$where = array();
		
		if (is_string($value) and isset($this->_key))
			$value = array($this->_key => $value);
		
		if (is_array($value)) {
			foreach ($value as $key => $subValue) {
				$valueString[] = "<em>{$key}={$subValue}</em>";
				$where["{$key} = ?"] = $subValue;
			}
		}
		else {
			throw new Zend_Validate_Exception("Value given must be an array and not '$value'");
		}
		
		$this->_setValue(implode(", ", $valueString));

		// Check table for field(s)
		$rows = $this->_table->fetchAll($where);
		$rowCount = count($rows);
		// Return no error if rowCount = 1
		if ($rowCount == 0)
			return true;
		
		// Get link
		$tableClass = get_class($this->_table);
		$info = $this->_table->info();
		$primaryCols = $info["primary"];
		$params = array();
		$rows = $rows->toArray();
		foreach ($rows as $key => $row) {
			foreach ($primaryCols as $column)
				$params[$key][$column] = $row[$column];
		}
		
		$inflector = new Zend_Filter_Inflector(':controller');
		$inflector->setRules(array(
		    ':controller'	=> array('Word_CamelCaseToDash', 'StringToLower'),
		));
		$controller = $inflector->filter(array('controller' => $tableClass));		
		$mainLink = array("controller" => $controller, "action" => "view");
		
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$urls = array();
		foreach ($params as $key => $param) {
			$linkParts = array_merge($mainLink, $param);
			$url = $viewRenderer->view->url($linkParts, null, true);
			$displayKey = $key + 1;
			$urls[] = "<a href='{$url}' target='_blank'>duplicate {$displayKey}</a>";
		}
		
		$this->_setValue(implode(", ", $valueString));
		$this->_link = implode(", ", $urls);
						
		// Return error if field(s) already exist
		if ($rowCount > 0)
			$this->_error(self::NOT_UNIQUE);
			return false;
		
		return true;		
	}
}
