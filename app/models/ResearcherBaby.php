<?php 
 
class ResearcherBaby extends Zarrar_Db_Table
{ 
	protected $_name = 'researcher_babies';
    protected $_primary = array("baby_id", "researcher_id");
	protected $_sequence = false;
    
	protected $_referenceMap    = array(
		'Baby' 		=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Researcher' => array(
		    'columns'           => 'researcher_id',
		    'refTableClass'     => 'Researcher',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'researcher'
			),
		'Study' => array(
		    'columns'           => 'study_id',
		    'refTableClass'     => 'Study',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'study'
			)
	);
}
