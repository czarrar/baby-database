<?php 
 
class Language extends Zarrar_Db_Table 
{ 
	protected $_name = 'languages';
    protected $_primary = array('id');
	protected $_unique = array('language');
	
	protected $_dependentTables = array('BabyLanguage');
}