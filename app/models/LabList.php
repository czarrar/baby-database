<?php 
 
class LabList extends Zarrar_Db_Table 
{ 
	protected $_name = 'lab_lists';
    protected $_primary = array("id");
	
	protected $_referenceMap    = array(
		'Lab' 		=> array(
		    'columns'           => 'lab_id',
		    'refTableClass'     => 'Lab',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'lab'
			),
		'List'          => array(
    	    'columns'           => 'list_id',
    	    'refTableClass'     => 'RList',
    	    'refColumns'        => 'id',
    		'refDisplayColumn'	=> 'list'
		)
	);
}
