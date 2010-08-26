<?php 
 
class ContactType extends Zarrar_Db_Table 
{ 
	protected $_name = 'contact_types';
    protected $_primary = "id";
	protected $_unique = "type";
    
	protected $_dependentTables = "ContactHistory";
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'type')		
	);
}