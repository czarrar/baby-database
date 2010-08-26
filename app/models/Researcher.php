<?php 
 
class Researcher extends Zarrar_Db_Table
{ 
	protected $_name = 'researchers';
    protected $_primary = "id";
	protected $_unique = "researcher";
    
	protected $_referenceMap    = array(
		'Lab' 		=> array(
		    'columns'           => 'lab_id',
		    'refTableClass'     => 'Lab',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'lab'
			)
	);
	
	protected $_dependentTables = array('Study', "CheckoutHistory", "ResearcherBaby", "ContactHistory");
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'researcher'),
		'where'	 	=> "to_use = 1"			
	);
	
	/**
	 * Gives a list of baby record owners depending on users
	 * 	security clearance and/or lab affiliation. Possible to
	 * 	get list of ' researcher-name ' or ' lab-name : researcher-name '
	 *
	 * @param string $displayType Display 'short' version
	 * 	(researcher-name) or 'long' version (lab-name : researcher-name).
	 * 	Default is 'long' but can be set to 'short'.
	 * @param boolean $keepNone Do you want to keep the 'None' entry
	 * 	as a researcher name. Default is false.
	 * @param array $prependSelectOptions What to put at the beginning
	 * 	of the returned select options. Default is array("ALL"=>"ALL", ""=>"").
	 * 
	 * @return array To be used in form select where keys are
	 * 	researcher ids and values are record owner names
	 **/
	public function getRecordOwners($displayType="long", $keepNone=False, array $prependSelectOptions=array())
	{
	
		/* Get db adapter */
		$db = $this->getAdapter();
	
	
		/* Setup base query */
		
		// Instantiate class
		$select = $db->select()
		
		// Only want distinct rows
			->distinct()
		
		// Order by 'record_owner'
			->order("record_owner");
		
		// Get researcher_id + record_owner
		switch ($displayType) {
			case 'long':
				// Get researcher_id from base table 'researchers'
				$select->from(array('r' => 'researchers'),
			        array("researcher_id" => "id"))
				// Get lab table + record owner ("lab : researcher")
					->joinLeft(array('l' => 'labs'),
				        'r.lab_id = l.id', array("record_owner" => new Zend_Db_Expr('CONCAT(l.lab, " : ", r.researcher)')));
				break;
			case 'short':
				// Get researcher_id and record owner (researcher)
				// from base table 'researchers'
				$select->from(array('r' => 'researchers'),
			        array("researcher_id" => "id", "record_owner" => "researcher"))
				// Get lab table
					->joinLeft(array("l" => "labs"),
						'r.lab_id = l.id', array());
				break;
			default:
				break;
		}

		// Get callers table
		$select->joinLeft(array("c" => "callers"),
				"l.id = c.lab_id", array());

		// Restrict record owner list to those in the same lab as caller (unless...)
		if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator"))
			$select->where("c.id = ?", $_SESSION['caller_id']);

		// Want only rows with active (to_use=1) researcher
		$select->where("r.to_use = ?", 1);
		
		// Want to keep out 'None' researcher row
		if ($keepNone === false)
			$select->where("r.researcher != ?", "None");

		
		/* Execute Query + Form Select Options */
		
		// Execute
		$stmt = $select->query();
		$result = $stmt->fetchAll();
		
		// Create beginning of record owner select options
		if (empty($prependSelectOptions))
			$ownerOptions = array("All" => "All");
		else
			$ownerOptions = $prependSelectOptions;
			
		// Create form select, owner options
		foreach ($result as $key => $row)
			$ownerOptions[$row['researcher_id']] = $row['record_owner'];
			
		return $ownerOptions;
	}
}