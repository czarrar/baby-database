<?php

Zend_Loader::loadClass('Zend_View_Helper_FormElement');
Zend_Loader::loadClass('Zend_View_Exception');


class Zarrar_View_Helper_DbForm extends Zend_View_Helper_FormElement
{
	protected static $formtypes = array(
		'formText'			=> array('int', 'bigint', 'float', 'double', 'char', 'varchar'),
		'formTextarea'		=> array('blob', 'text'),
		'formCheckBox'		=> array('tinyint', 'boolean'),
		'formSelectDate'	=> array('date'),
		'formSelectYear'	=> array('year')
	);
	
	protected static $formOptions = array(
		'formButton', 'formCheckbox', 'formFile', 'formHidden', 'formImage', 'formLabel', 'formNote', 'formPassword', 'formRadio', 'formReset', 'formSelect', 'formSelectDate', 'formSelectDay', 'formSelectMonth', 'formSelectYear', 'formSubmit', 'formText', 'formTextarea'
	);
	
	protected $table;
	
	protected $row;
	
	protected $objectname;
	
	protected $forminfo;
	
	protected $metadata = array();
	
	protected $cols = array();
	
	protected $references = array();
	
	public function dbForm($tableORrow, $forminfo=NULL)
	{
		$classname = get_parent_class($tableORrow);
		switch ($classname) {
			case 'Zend_Db_Table':
			case 'Zarrar_Db_Table':
				$this->table = $tableORrow;
				$this->row = null;
				break;
			case 'Zend_Db_Table_Rowset_Abstract':
				$this->row = $tableORrow->current();
				$this->table = $this->row->getTable();
				break;
			case 'Zend_Db_Table_Row_Abstract':
				$this->row = $tableORrow;
				$this->table = $this->row->getTable();
				break;
			default:
				throw new Zend_View_Exception("Class: {$classname} is not supported by dbForm");
				break;
		}
		$this->objectname = get_class($this->table);
		$this->objectname = strtolower($this->objectname);
		
		$info = $this->table->info();
		$this->metadata = $info['metadata'];
		$this->cols = $info['cols'];
		foreach ($info['referenceMap'] as $column => $refTable)
			$this->references[$refTable['columns']] = $refTable['refTableClass'];
		
		# Save form conversion info
		$this->forminfo = $forminfo;
		
		return $this;
	}
	
	public function __call($method, $args)
	{
		if (in_array($method, $this->cols)) {
			$name = $method;
			$options = $args[1];
			$html_options = $args[2];
		}
		elseif (in_array($method, self::$formOptions)) {			
			// Assign variables name, value, html_options, options
			$name = $args[0];
			$options = $args[1];
			$options['formElement'] = $method;
			$html_options = $args[2];
		}
		else {
			throw new Zend_View_Exception("Method not defined");
		}
				
		return $this->getField($name, $html_options, $options);
	}
	
	public function __get($var)
	{
		if (in_array($var, $this->cols))
			return $this->getField($var);
		else
			throw new Zend_View_Exception("Member not defined");
	}
	
	public function getField($colname, $html_options = null, $options = null)
	{
		// Check to see if column exists
		if(!(in_array($colname, $this->cols)))
			throw new Zend_View_Exception("Column does not exist");
	
		// Initialize variables
		$name = "{$colname}";
		$value = $this->view->{$this->objectname}->$colname;
		$html_options['id'] = "{$this->objectname}_{$colname}";
				
		// Assign the formtype
		if (isset($options['formElement'])) {
			$formtype = $options['formElement'];
		}
		elseif (array_key_exists($colname, $this->references)) {
			$formtype = 'formSelect';
			$id = (isset($this->row)) ? $this->row->id : null ;
			$form_options = $this->getLinked($this->references[$colname], $id);
		}
		else {
			$datatype = $this->metadata[$colname]['DATA_TYPE'];
			$formtype = $this->getElementType($datatype);
			$form_options = null;
		}

		$xhtml = $this->view->$formtype($name, $value, $html_options, $form_options);
		$xhtml .= PHP_EOL;

		
		return $xhtml;
	}
	
	public function label($colname, $value=NULL, $html_options = array())
	{
		$name = "{$this->objectname}[{$colname}]";
		$value = (isset($value)) ? $value : $this->beautify($colname) ;
		$value = "<strong>{$value}</strong>";
		
		$xhtml = $this->view->formLabel($name, $value, $html_options);
		$xhtml .= PHP_EOL;
		
		
		return $xhtml;
	}
		
	public function begin($method = 'post', $action = null, $html_options = null)
	{
		# 1. Set form action
		if (empty($action))
			$action = $this->view->url(array('controller' => $this->view->controller, 'action' => $this->view->action), $this->view->action->module);
		$xhtml = PHP_EOL .
				'<form action="' . $action . '"'
				. ' method="' . $method . '"';
	    // add attributes, close, and return
        $xhtml .= $this->_htmlAttribs($html_options) . ' />' . PHP_EOL;
		
		# 2. Set hidden field
		// get hidden value
		$hiddenvalue = "";
		if(isset($this->row))
			$hiddenvalue = $this->row->id;
		else
			$hiddenvalue = -1;
		// get html
		$xhtml .= $this->view->formHidden("{$this->objectname}[id]", $hiddenvalue, array('id' => "{$this->objectname}_id"));
		$xhtml .= PHP_EOL;
				
		return $xhtml;
	}
	
	public function end()
	{
		return '</form>' . PHP_EOL;
	}
	
	protected function beautify($text)
	{
		$text = str_replace("_", " ", $text);
		$text = strtolower($text);
		$text = ucwords($text);
		$text .= ": ";
		
		return $text;
	}
	
	protected function getElementType($datatype)
	{
		foreach (self::$formtypes as $type => $options) {
			if(in_array($datatype, $options))
				return $type;
		}
		
		throw new Exception("Could not find an appropriate form element");
	}
	
	protected function getLinked($linkedTableName, $id = null)
	{
		$reference = $this->table->getReference($linkedTableName);
		$linkedTable = $this->loadObject($reference['refTableClass']);
		$rows = $linkedTable->fetchAll()->toArray();
		$return = array();
		foreach($rows as $row) {
			$return[$row['id']] = $row['name'];
		}
		
		return $return;
		
		//return $linkedTable->getLink($id);
	}
	
	protected function loadObject($classname)
	{
		Zend_Loader::loadClass($classname);
		
		$temp = new $classname();
		return $temp;
	}
}