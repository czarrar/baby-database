<?php 
 
class ContactHistory extends Zarrar_Db_Table
{ 
	protected $_name = 'contact_histories';
    protected $_primary = array('baby_id', 'study_id', 'attempt');
	protected $_sequence = false;
    
	protected $_referenceMap    = array(
		'Baby' 		=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Researcher'	=> array(
		    'columns'           => 'researcher_id',
		    'refTableClass'     => 'Researcher',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'researcher'
			),
		'Study' 	=> array(
		    'columns'           => 'study_id',
		    'refTableClass'     => 'Study',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'study'
			),
		'Callers' 	=> array(
		    'columns'           => 'caller_id',
		    'refTableClass'     => 'Callers',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'name'
			),
		'Type' 		=> array(
		    'columns'           => 'contact_type_id',
		    'refTableClass'     => 'ContactType',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'type'
			),
		'Outcome' 	=> array(
		    'columns'           => 'contact_outcome_id',
		    'refTableClass'     => 'ContactOutcome',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'outcome'
			)
	);
	
	protected $_filterRules = array(
	);
	
	protected $_errorValidationRules = array(
		'baby_id'		=> array(
			'NotEmpty',
			'messages' 	=> "Baby id was not given, please contact administrator."
		),
		'attempt'		=> array(
			'NotEmpty',
			'Digits',
			array('GreaterThan', 0),
			'breakChainOnFailure' => true,
			'messages' 	=> array(
				0 => "You must enter the contact attempt number!",
				1 => "Contact attempt number must be a number",
				1 => "Contact attempt number must be greater than 0"
			)
		),
		'datetime'		=> array(
			'NotEmpty',
			array("Date", "YYYY-MM-DD HH:mm")
		),
		'callback_date'	=> array(
			"Date",
			'allowEmpty' => true,
			"messages"	=> "Incorrect callback date entered '%value%'"
		),
		'study_id'		=> array(
			'NotEmpty',
			'messages' 	=> "You must enter a study.  You can also select 'None' under study."
		),
		'primary'		=> array(
			array('Uniqueness', "ContactHistory"),
			'fields'	=> array('baby_id', 'study_id', 'attempt'),
			'messages'	=> "A contact attempt has already been logged for %value%!"
		),
		'contact_outcome_id' => array(
			'NotEmpty',
			'messages'	=> "You must give a contact outcome."
		)
	);
	
	// Addition valid fields (on top of column names in table)
	protected $_additionalFields = array(
		"checkout", "activate", "inactivate", "to_callback"
	);
	
	protected function _modifyData(array $data)
	{
		/* Datetime of Entry */
				
		// Get date
		$dateFilter = new Zarrar_Filter_ArrayToDate();
		$date = $dateFilter->filter($data["date"]);
		
		// Get time
		$timeFilter = new Zarrar_Filter_ArrayToTime();
		$time = $timeFilter->filter($data["time"]);
			
		// Merge date + time
		$data['datetime'] = "{$date} {$time}";
		
		// Callback?
		if ($data["to_callback"]) {
		    // Process callback date
    		$data["callback_date"] = $dateFilter->filter($data["callback_date"]);
    		// Process time
    		$data["callback_time_begin"] = $timeFilter->filter($data["callback_time_begin"]);
    		$data["callback_time_end"] = $timeFilter->filter($data["callback_time_end"]);
		} else {
		    // Remove callback if given
		    if (!empty($data["callback_date"]))
		        unset($data["callback_date"]);
		    if (!empty($data["callback_time_begin"]))
		        unset($data["callback_time_begin"]);
		    if (!empty($data["callback_time_end"]))
		        unset($data["callback_time_end"]);
		}
		
		// Delete unused date variables
		unset($data['date']);
		unset($data['time']);
		
		/** Process study and researcher ids **/
		
		$studyId = $data['study_id'];
		
		if (!(empty($studyId))) {
			$select = $this->getAdapter()->select()
						->distinct()
						->from("studies",
							array("researcher_id"))
						->where("id = ?", $studyId);
						
			$stmt = $select->query();
			$stmt->execute();
			$studyResearcherId = $stmt->fetchColumn();
			$data['researcher_id'] = $studyResearcherId;
		}
		
		return parent::_modifyData($data);
	}
	
	function insert(array $data=array())
	{
		// Don't want checkout, activate, inactivate
		unset($this->_data['checkout'], $this->_data['activate'], $this->_data['inactivate'], $this->_data['to_callback]);
				
		return parent::insert($data);
	}
}

