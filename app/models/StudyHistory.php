<?php 
 
class StudyHistory extends Zarrar_Db_Table 
{ 
	protected $_name = 'study_histories';
    protected $_primary = array("id");
    
	protected $_referenceMap    = array(
		'Baby' 			=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Study' 		=> array(
		    'columns'           => 'study_id',
		    'refTableClass'     => 'study',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'study'
			),
		'Outcome' 		=> array(
		    'columns'           => 'study_outcome_id',
		    'refTableClass'     => 'StudyOutcome',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'outcome'
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
		"baby_id"	=> array(
			"presence" => "required"
		),
		'study_id'	=> array(
			"presence" => "required"
		),
		"study_outcome_id"	=> array(
			"NotEmpty",
			"messages" => "Field 'outcome' is required."
		),
		"appointment"	=> array(
			"allowEmpty" => TRUE,
			"messages"	=> "An appointment date and time must be given."
		)
	);
	
	// Addition valid fields (on top of column names in table)
	protected $_additionalFields = array(
		"new", "edit", "confirm", "save"
	);
	
	// Modify incoming form fields
	protected function _modifyData($formData)
	{
		// Set allow further as 1 or 0
		if (!(empty($formData["allow_further"])))
			$formData['allow_further'] = ($formData['allow_further'] == "Allow Further Study") ? 1 : 0 ;
	
		// Set confirm (study_outcome_id)
		if ($formData["confirm"]) {
			$formData["study_outcome_id"] = ($formData["confirm"] == "Confirm Appointment") ? 1 : 3 ;
			unset($formData["confirm"]);
		}
		
		// Set appointment date
		if (is_array($formData["appointment"]))
			$formData["appointment"] = $this->_filter->ArrayToDate($formData["appointment"], $type = "datetime");
		
		#Sup
		return $formData;
	}
}