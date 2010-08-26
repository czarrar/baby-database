<?php 
 
class Logins extends Zarrar_Db_Table 
{ 
	protected $_name = 'logins';
    protected $_primary = array('id');
    
	protected $_referenceMap    = array(
		'Caller'			=> array(
		    'columns'           => 'caller_id',
		    'refTableClass'     => 'Callers',
		    'refColumns'        => 'id'
			)
	);
}