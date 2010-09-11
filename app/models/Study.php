<?php 
 
class Study extends Zarrar_Db_Table
{ 
	protected $_name = 'studies';
    protected $_primary = "id";
	protected $_unique = "study";
    
	protected $_referenceMap    = array(
		'Researcher'	=> array(
		    'columns'           => 'researcher_id',
		    'refTableClass'     => 'Researcher',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'researcher'
			)
	);
	
	protected $_dependentTables = array("BabyStudy", "ResearcherBaby", "StudyHistory", "ContactHistory", "CheckoutHistory");
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'study'),
		'where'	 	=> "to_use = 1"			
	);
	
	public function insert(array $data=array())
    {
        // default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');
		
        return parent::insert($data);
    }
    
    public function getListIds($studyId) {
		$query = "SELECT lists.id FROM lists LEFT JOIN lab_lists ON lists.id = lab_lists.list_id LEFT JOIN labs ON lab_lists.lab_id = labs.id LEFT JOIN researchers ON labs.id = researchers.lab_id LEFT JOIN studies ON studies.researcher_id = researchers.id WHERE studies.id = ?";
		
		// Execute
		$stmt = $this->getAdapter()->query($query, $studyId);
		$results = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		
		// Check
		if (count($results) == 0)
			throw new Exception("Could not find a list associated with current study");
		
		return $results;
	}

	/**
	 * Gives a list of studies depending on users
	 * 	security clearance and/or lab affiliation.
	 *
	 * @param array $prependSelectOptions What to put at the beginning
	 * 	of the returned select options. Default is array("ALL"=>"ALL", ""=>"").
	 * @param boolean $keepNone Do you want to keep the 'None' entry
	 * 	as a researcher name. Default is false.
	 * 
	 * @return array To be used in form select where keys are
	 * 	researcher ids and values are record owner names
	 **/
	public function getStudies(array $prependSelectOptions=array(), $keepNone=False, $showResearcher=False)
	{
	
		/* Get db adapter */
		
		$db = $this->getAdapter();
	
	
		/* Setup base query */
		
		// Instantiate class
		$select = $db->select()
		
		// Only want distinct rows
			->distinct()
		
		// Order by 'record_owner'
			->order("study");
			
		// Get study table + researchers
		if ($showResearcher) {
			$select->from(array("s" => "studies"),
				array("study_id" => "id"))
			->joinLeft(array("r" => "researchers"),
				"s.researcher_id = r.id", array("study" => new Zend_Db_Expr('CONCAT(s.study, " : ", r.researcher)')));
		} else {
			$select->from(array("s" => "studies"),
				array("study_id" => "id", "study"))
			->joinLeft(array("r" => "researchers"),
				"s.researcher_id = r.id", array());
		}
				
		// Get associated caller list
		$select->joinLeft(array("l" => "labs"),
				"r.lab_id = l.id", array())
			->joinLeft(array("c" => "callers"),
				"l.id = c.lab_id", array())
				
		// Want only rows with active (to_use=1) researcher
			->where("r.to_use = ?", 1);

		// Restrict record owner list to those in the same lab as caller (unless...)
		if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator")) {
			$where1 = $db->quoteInto("c.id = ?", $_SESSION['caller_id']);
			$where2 = $db->quoteInto("c.name = ?", "None");
			$where3 = $db->quoteInto("s.id = ?", 1);
			if ($keepNone == false)
				$select->where("{$where1} OR {$where2}");
			else
				$select->where("{$where1} OR {$where2} OR {$where3}");
		}
		
		// Want to keep out 'None' studies row
		if ($keepNone === false)
			$select->where("s.study != ?", "None");

		
		/* Execute Query + Form Select Options */
		
		// Execute
		$stmt = $select->query();
		$result = $stmt->fetchAll();
		
		// Create beginning of record owner select options
		$studyOptions = (empty($prependSelectOptions)) ? array("ALL" => "ALL", "" => "") : $prependSelectOptions ;
		
		// Create form select, study options
		foreach ($result as $key => $row)
			$studyOptions[$row['study_id']] = $row['study'];
			
		return $studyOptions;
	}
	
	/**
	 * Gives a list of baby record owners depending on users
	 * 	security clearance and/or lab affiliation. Possible to
	 * 	get list of ' study-name ' or ' researcher-name : study-name '
	 *
	 * @param string $displayType Display 'short' version
	 * 	(study-name) or 'long' version (researcher-name : study-name).
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
				// Get study_id from base table 'studies'
				$select->from(array('s' => 'studies'),
					array("study_id" => "id"))
				// Get researcher_id from table 'researchers'
					->joinLeft(array('r' => 'researchers'),
			        "s.researcher_id = r.id", array("researcher_id" => "id", "record_owner" => new Zend_Db_Expr('CONCAT(r.researcher, " : ", s.study)')))
				// Get lab table + record owner ("lab : researcher")
					->joinLeft(array('l' => 'labs'),
				        'r.lab_id = l.id', array());
				break;
			case 'short':
				// Get study_id and record owner (studies)
				// from base table 'researchers'
				$select->from(array('s' => 'studies'),
			        array("study_id" => "id", "record_owner" => "study"))
				// Get researcher table
					->joinLeft(array('r' => 'researchers'),
			        	"s.researcher_id = r.id", array())
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

		// Want only rows with active (to_use=1) study
		$select->where("s.to_use = ?", 1);
		
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
			$ownerOptions[$row['study_id']] = $row['record_owner'];
			
		return $ownerOptions;
	}
}
