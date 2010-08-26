<?php 
 
class StudyOutcome extends Zarrar_Db_Table 
{ 
	protected $_name = 'study_outcomes';
    protected $_primary = "id";
	protected $_unique = "outcome";
	
	protected $_dependentTables = array("StudyHistory");
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'outcome')
	);
}