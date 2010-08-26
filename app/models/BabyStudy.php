<?php 
 
class BabyStudy extends Zarrar_Db_Table 
{ 
	protected $_name = 'baby_studies';
    protected $_primary = array('baby_id', 'study_id');
	protected $_sequence = false;
    
	protected $_referenceMap    = array(
		'Baby' 			=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Study' 		=> array(
		    'columns'           => 'study_id',
		    'refTableClass'     => 'Study',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'study'
			),
		'StudyHistory' 	=> array(
		    'columns'           => 'study_history_id',
		    'refTableClass'     => 'StudyHistory',
		    'refColumns'        => 'id'
			),
		'Caller'		=> array(
			'columns'			=> 'caller_id',
			'refTableClass'		=> 'Callers',
			'refColumns'		=> 'id',
			'refDisplayColumn'	=> 'name'
			)
	);
	
	// Declare filters
	protected $_filterRules = array(
		'*' => 'StripTags'
	);
	
	// Declare validators
	protected $_errorValidationRules = array(
		"baby_id"		=> array(
			"NotEmpty",
			"messages"	=> "A serial no must be given"
		),
		'study_id'		=> array(
			"NotEmpty",
			'messages'	=> "A study must be selected"
		),
		"appointment"	=> array(
			"NotEmpty",
			"messages"	=> "An appointment date and time must be given."
		)
	);
	
	// Addition valid fields (on top of column names in table)
	protected $_additionalFields = array(
		"new", "edit", "room", "study_length", "sibling"
	);
	
	// Modify incoming form fields
	protected function _modifyData($formData)
	{
		// Convert appointment, which is an array, into a string of YYYY-MM-dd HH:mm:ss
		$formData["appointment"] = $this->_filter->ArrayToDate($formData["appointment"], $type = "datetime");
		return $formData;
	}
	
	// Modify insert function
	public function insert(array $data=array())
    {
		// Take out sibling field and add to comments
		if (!(empty($data["sibling"]))) {
			$oldComments = $this->getFilteredData();
			$data["comments"] = "Sibling coming.  " . $data["comments"];
		}
		// Take out sibling column no matter what
		unset($data["sibling"]);
				
        return parent::insert($data);
    }
	
	// Modify update function
    public function update(array $data=array(), $where=array())
    {
		// Take out sibling field and add to comments
		if (!(empty($data["sibling"])))
			unset($data["sibling"]);

        return parent::update($data, $where);
    }

	// Modify insert function
	public function filterInsert(array $data)
    {
		// Take out sibling field and add to comments
		if (!empty($data["sibling"]))
			$data["comments"] = "Sibling coming.  " . $data["comments"];
		unset($data["sibling"]);
				
        return parent::filterInsert($data);
    }

	/**
	 * Fetch information for a given baby in a study
	 *
	 * @param int $babyId
	 * @param int $studyId
	 * @return array Contains fields for comments, appointment, and study history id
	 **/
	public function getBasics($babyId, $studyId)
	{
		// Query to get comments, appointment time, and study history id
		$query = "SELECT bs.comments, bs.appointment, bs.appointment_end_time FROM baby_studies AS bs WHERE bs.baby_id = ? AND bs.study_id = ?";
		
		// Execute query given baby and study ids
		$stmt = $this->getAdapter()->query($query, array($babyId, $studyId));
		$stmt->execute();
		$result = $stmt->fetchAll();
				
		// What you got?
		$result = (count($result) < 1) ? False : $result[0];
			
		return $result;
	}
}
