<?php 
 
class Callers extends Zarrar_Db_Table 
{ 
	protected $_name = 'callers';
    protected $_primary = array('id');
	protected $_unique = array(array("name", "lab_id"));

	protected $_dependentTables = array('Logins', "ContactHistory", "BabyStudy", "StudyHistory");
    
	protected $_referenceMap    = array(
		'Lab'					=> array(
		    'columns'           => 'lab_id',
		    'refTableClass'     => 'Lab',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> "lab"
			),
	);
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'name'),
		'where'		=> "to_use = 1"
	);
	
	function getLabName($callerId)
	{
		// Query to get lab name
		$query = "SELECT DISTINCT l.lab AS lab FROM callers AS c LEFT JOIN labs AS l ON c.lab_id = l.id WHERE c.id = ?";
	
		// Execute
		$stmt = $this->getAdapter()->query($query, $callerId);
		$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		
		// Get lab name
		$labName = $result[0];
		
		return $labName;
	}
	
	public function getListIds($callerId=NULL) {
	    if (empty($callerId))
	        $callerId = $_SESSION['caller_id'];
	    
		$query = "SELECT lists.id FROM lists LEFT JOIN lab_lists ON lists.id = lab_lists.list_id LEFT JOIN labs ON lab_lists.lab_id = labs.id LEFT JOIN callers ON labs.id = callers.lab_id WHERE callers.id = ?";
		
		// Execute
		$stmt = $this->getAdapter()->query($query, $callerId);
		$results = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		
		// Check
		if (count($results) == 0)
			throw new Exception("Could not find a list associated with current study");
		
		return $results;
	}
}