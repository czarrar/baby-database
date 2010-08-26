<?php 
 
class FamilySetting extends Zarrar_Db_Table 
{ 
	protected $_name = 'family_settings';
    protected $_primary = array('id');
	protected $_unique = array('setting');
	
	protected $_dependentTables = array('FamilyPhone');
}
