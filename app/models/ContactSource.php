<?php 
 
class ContactSource extends Zarrar_Db_Table 
{ 
	protected $_name = 'contact_sources';
    protected $_primary = "id";
	protected $_unique = "source";
    
	protected $_dependentTables = "Family";
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'source')		
	);
}