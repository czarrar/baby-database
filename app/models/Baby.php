<?php 
 
class Baby extends Zarrar_Db_Table 
{ 
	protected $_name = 'babies';
    protected $_primary = array('id');

	protected $_referenceMap    = array(
		'Family' 		=> array(
		    'columns'           => 'family_id',
		    'refTableClass'     => 'Family',
		    'refColumns'        => 'id'
			),
		'Status' 		=> array(
		    'columns'           => 'status_id',
		    'refTableClass'     => 'Statuses',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'status'
			),
		'List'          => array(
    	    'columns'           => 'list_id',
    	    'refTableClass'     => 'RList',
    	    'refColumns'        => 'id',
    		'refDisplayColumn'	=> 'list'
		)
	);
	
	protected $_dependentTables = array('BabyLanguage', "ResearcherBaby", "BabyStudy", 'ContactHistory', 'StudyHistory', "CheckoutHistory");
	
	protected $_columnsNull = array(
		"middle_name"		=> NULL,
		"birth_weight"		=> NULL,
		"term"				=> NULL,
		"daycare"			=> NULL,
		"med_problems"		=> NULL,
		"ear_infection"		=> NULL,
		"audlang_problems"	=> NULL,
		"comments"			=> NULL
	);
	
	protected $_filterRules = array(
		'*'				=> 'StripTags'
	);
	
	protected $_errorValidationRules = array(
		'first_name'		=> array(
			'NotEmpty',
			array('StringLength', 0, 100),
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => "You must enter the baby's first name",
				1 => "The baby's first name cannot be longer than 100 characters"
			)
		),
		'last_name'			=> array(
			'NotEmpty',
			array('StringLength', 0, 150),
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => "You must enter the baby's last name",
				1 => "The baby's last name cannot be longer than 150 characters"
			)
		),
		'dob'		=> array(
			'NotEmpty',
			'Date',
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => "You must enter the baby's date of birth",
				1 => "The baby's date of birth must be in this format: YYYY-MM-DD"
			)
		),
		'birth_weight'		=> array(
			'Float',
			'allowEmpty' => true,
			'messages' => 'Birth weight (%value%) does not appear to be in the proper format'
		)
	);	
	
	// Declare warning validators
	protected $_warningValidationRules = array(
		'unique'			=> array(
			array('Uniqueness', 'Baby'),
			'fields' => array('first_name', 'last_name', 'dob'),
			'messages' => "This baby's first name, last name, and date of birth already exist in the database."	# Can the uniqueness thing give me a link to the baby's entry?
		)
	);
	
	
	# Function to calculate the appropriate list id given a date of birth
	public function assignListId($dob, $familyId) {
	    # check to see if have siblings
	    # take list id from oldest sibling if exists
	    if (!empty($familyId)) {
	        $query = "SELECT babies.dob, babies.list_id FROM families LEFT JOIN babies ON families.id = babies.family_id WHERE families.id = ?";
        	$stmt = $this->getAdapter()->query($query, $familyId);
        	$results = $stmt->fetchAll();
        	
        	$listId = NULL;
        	foreach ($results as $arr) {
        	    foreach ($arr as $key => $value) {
        	        if($key == "dob" && $value == $dob)
        	            continue;
        	        elseif($key == "list_id" && !empty($value))
        	            $listId = $value;
        	   }
        	}
        	
        	if (!empty($listId))
        	    return($listId);
	    }
	    
	
	    # automatically assign the list ID of A, B, or C depending on date of
        # birth (first day of a month = A, second = B, third = C, fourth = A,
        # etc.)
        
        # Get day from dob
        $day = explode('-', $dob);
        $day = $day[2];
        
        # Get list ids and total # of ids
	    $query = "SELECT id FROM lists WHERE (is_permanent = 0) AND (to_use = 1) ORDER BY list";
	    $stmt = $this->getAdapter()->query($query);
	    $results = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
	    $numIds = count($results);
	    
	    # Divide day by # of list ids and take the reminder
	    $which = ($day + $numIds - 1) % $numIds;
	    $listId = $results[$which];
	    
	    return($listId);
	}
	
	protected function _modifyData(array $data)
	{	
		/* Check for archive setting */
		if (isset($data["archive"]) and $data["archive"]) {
			// Set status to archive = 2
			$data["status_id"] = 2;
			// Set to checked in
			$data["checked_out"] = 0;			
			// Pass message
			$_SESSION["baby_message"] = "Baby has been archived!";
			
			unset($data["archive"]);
		}
		
		/* Check for unarchive setting */
		if (isset($data['unarchive']) and $data['unarchive']) {
			$data["status_id"] = 1;
			$_SESSION["baby_message"] = "Baby has been unarchived";
			unset($data["unarchive"]);
		}
	
		/* Date of birth */
		
		// Since form data will have dob split into year, month, and day
		// we want to put these three things together for db insertion
		$baby_dob = $data['dob'];
		
		if (!(empty($baby_dob['year'])) or !(empty($baby_dob['month'])) or !(empty($baby_dob['day'])))
			$data['dob'] = "{$baby_dob['year']}-{$baby_dob['month']}-{$baby_dob['day']}";
		else
			$data['dob'] = '';
			
		
		/* Birth weight */
		
		// Get weight + weight type
		$pounds = (empty($data["birth_weight_pounds"])) ? 0 : $data["birth_weight_pounds"];
		$ounces = (empty($data["birth_weight_ounces"])) ? 0 : $data["birth_weight_ounces"];
		$grams = $data["birth_weight_grams"];
		$weight = 0;
		
		// Go through pounds + ounces first
		if (!(empty($pounds)) or !(empty($ounces))) {
			// Get/Set weight
			$ounceUnit = new Zend_Measure_Weight($ounces, Zend_Measure_Weight::OUNCE);
			$ounceUnit->setType(Zend_Measure_Weight::POUND);
			$data["birth_weight"] = (float) ($pounds + $ounceUnit->getValue());
		}
		// Go through grams, second
		elseif (!(empty($grams))) {
			// Get/Set Weight
			$gramUnit = new Zend_Measure_Weight($grams, Zend_Measure_Weight::GRAM);
			$gramUnit->setType(Zend_Measure_Weight::POUND);
			$data["birth_weight"] = (float) $gramUnit->getValue();
		}
		
		// Unset unneeded vars
		unset($data["birth_weight_grams"]);
		unset($data["birth_weight_pounds"]);
		unset($data["birth_weight_ounces"]);
		
		
		/* Term length */
		
		$term = '';
		$term_period = $data['term_period'];
		$term_weeks = $data['term_weeks'];
		
		// Set term (can't have both term_period and term_weekds defined)
		if (isset($term_period) and $term_period != '' and !(empty($term_weeks))) {
			$this->_filter->addErrorMessage('term', "Cannot put values in for both term period '{$term_period}' or term weeks '{$term_weeks}'");
			$term = $term_weeks;
		} elseif (isset($term_period) and $term_period != '') {
			$term = 40 + $term_period;
		} elseif (!(empty($term_weeks))) {
			$term = $term_weeks;
		}
		
		#// Warning if child is a preemie
		#if ($term != '' && $term < 37)
		#	$this->_filter->addWarningMessage('term', "Possible preemie as gestation period is $term");
		
		// Set 'term' and take out term_period and term_weeks
		$data['term'] = $term;
		unset($data['term_period']);
		unset($data['term_weeks']);
		
		return parent::_modifyData($data);
	}
	
	# note that $data coming in here is incomplete
	# other data should be in $this->_data
	public function insert(array $data=array())
    {
        // default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');

		// default value for 'last_update' is the date+time now
        if (empty($data['last_update']))
            $data['last_update'] = new Zend_Db_Expr('NOW()');
		
		// default value for 'list'
		if (empty($data['list_id']) && !empty($this->_data['dob'])) {
		    if (!empty($this->_data['family_id']))
		        $familyId = $this->_data['family_id'];
		    else
		        $familyId = $data['family_id'];
		    $data['list_id'] = $this->assignListId($this->_data['dob'], $familyId);
		}
		
        return parent::insert($data);
    }

    public function update(array $data=array(), $where=array(), $setNulls=FALSE)
    {
        // default value for 'last_update' is the date+time now
        if (empty($data['last_update']))
            $data['last_update'] = new Zend_Db_Expr('NOW()');

		$nulls = ($setNulls) ? $this->_columnsNull : array();

		return parent::update($data, $where, $nulls);
    }

	public function filterInsert(array $data)
    {
        // default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');

		// default value for 'last_update' is the date+time now
        if (empty($data['last_update']))
            $data['last_update'] = new Zend_Db_Expr('NOW()');
            
        // default value for 'list'
		if (empty($data['list_id']) && !empty($this->_data['dob'])) {
		    if (!empty($this->_data['family_id']))
		        $familyId = $this->_data['family_id'];
		    else
		        $familyId = $data['family_id'];
		    $data['list_id'] = $this->assignListId($this->_data['dob'], $familyId);
		}
		
        return parent::filterInsert($data);
    }

    public function filterUpdate(array $data, $where)
    {
        // default value for 'last_update' is the date+time now
        if (empty($data['last_update']))
            $data['last_update'] = new Zend_Db_Expr('NOW()');

        return parent::filterUpdate($data, $where);
    }
}