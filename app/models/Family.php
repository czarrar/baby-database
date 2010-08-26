<?php 
 
class Family extends Zarrar_Db_Table 
{ 
	protected $_name = 'families';
    protected $_primary = array('id');

	protected $_referenceMap    = array(
		'MotherEthnicity' 	=> array(
		    'columns'           => 'mother_ethnicity_id',
		    'refTableClass'     => 'Ethnicity',
		    'refColumns'        => 'id',
			"refDisplayColumn"	=> "ethnicity"
			),
		'FatherEthnicity' 	=> array(
		    'columns'           => 'father_ethnicity_id',
		    'refTableClass'     => 'Ethnicity',
		    'refColumns'        => 'id',
			"refDisplayColumn"	=> "ethnicity"
			)
	);
	
	protected $_dependentTables = array('Baby', 'FamilyPhone', 'FamilyEmail');
	
	protected $_columnsNull = array(
		'mother_first_name'		=> NULL,
		'mother_last_name'		=> NULL,
		'mother_ethnicity_id'	=> NULL,
		'father_first_name'		=> NULL,
		'father_last_name'		=> NULL,
		'father_ethnicity_id'	=> NULL,
		'contact_source_id'		=> NULL,
		'how_heard'				=> NULL,
		'income'				=> NULL,
		'address_1'				=> NULL,
		'address_2'				=> NULL,
		'city'					=> NULL,
		'state'					=> NULL,
		'zip'					=> NULL,
		'zip_plus'				=> NULL,
		'comments'				=> NULL
	);
	
	// Declare filters
	protected $_filterRules = array(
		'*'					=> 'StripTags',
		'mother_first_name'	=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'mother_last_name'	=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'father_first_name'	=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'father_last_name'	=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'address_1'			=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'address_2'			=> array(
			'StringToLower',
			'StringCapitalize'
		),
		'city'				=> array(
			'StringToLower',
			'StringCapitalize'
		)
	);
	
	// Declare error validators
	protected $_errorValidationRules = array(
		'zip'		=> array(
			'Digits',
			array('StringLength', 5, 5),
			'allowEmpty' => true,
			'messages' => array(
				0 => "Zip code has non-numeric characters.",
				1 => "Zip code must be 5 characters long."
			)
		),
		'zip_plus'	=> array(
			'Digits',
			array('StringLength', 4, 4),
			'allowEmpty' => true,
			'messages' => array(
				0 => "Zip+ code has non-numeric characters.",
				1 => "Zip+ code must be 4 characters long."
			)
		),
		'income'	=> array(
			'Digits',
			'allowEmpty' => true,
			'messages' => 'Income field has non-numeric characters'
		)
	);
	
	// Declare warning validators
	#protected $_warningValidationRules = array(
	#	'mother'		=> array(
	#		array('Uniqueness', "Family"),
	#		'fields'	=> array('mother_first_name', 'mother_last_name'),
	#		'messages'	=> "A mother with the same first and last name as the one entered already exists in the database."
	#	),
	#	'father'		=> array(
	#		array('Uniqueness', "Family"),
	#		'fields'	=> array('father_first_name', 'father_last_name'),
	#		'messages'	=> "A father with the same first and last name as the one entered already exists in the database."
	#	)
	#);
	
	public function insert(array $data=array())
    {
		// default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');
		
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

	/**
	 * Returns an array with stats
	 *
	 * @return array
	 **/
	public function getStates()
	{
		$fullStates = array("Alabama", "Alaska", "Arizona", "Arkansas", "California", "Colorado", "Connecticut", "Delaware", "District of Columbia", "Florida", "Georgia", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", "Kansas", "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts", "Michigan", "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska", "Nevada", "New Hampshire", "New Jersey", "New Mexico", "New York", "North Carolina", "North Dakota", "Ohio", "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island", "South Carolina", "South Dakota", "Tennessee", "Texas", "Utah", "Vermont", "Virginia", "Washington", "West Virginia", "Wisconsin", "Wyoming");
		
		$abbreviations = array("AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "DC", "FL", "GA", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD",
		"MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH", "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC", "SD",
		"TN", "TX", "UT", "VT", "VA", "WA", "WV", "WI", "WY");
		
		return(array_combine(array("") + $abbreviations, array("Choose") + $fullStates));
	}
}