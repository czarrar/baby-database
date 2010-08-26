<?php 
 
class Ethnicity extends Zarrar_Db_Table 
{ 
	protected $_name = 'ethnicities';
    protected $_primary = array('id');
	protected $_unique = array('ethnicity');
	
	protected $_dependentTables = array('Family');
}
