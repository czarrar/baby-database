<?php 
 
class ContactOutcome extends Zarrar_Db_Table 
{ 
	protected $_name = 'contact_outcomes';
    protected $_primary = "id";
	protected $_unique = "outcome";
    
	protected $_dependentTables = "ContactHistory";
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'outcome')	
	);
	
}