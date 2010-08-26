<?php 
 
class FamilyOwner extends Zarrar_Db_Table 
{ 
	protected $_name = 'family_owners';
    protected $_primary = array('id');
	protected $_unique = array('owner');
	
	protected $_dependentTables = array('FamilyPhone', 'FamilyEmail');
}
