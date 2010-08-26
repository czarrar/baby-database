<?php 
 
class FamilyEmail extends Zarrar_Db_Table 
{ 
	protected $_name = 'family_emails';
	protected $_primary = 'email';
	protected $_sequence = false;

	protected $_referenceMap    = array(
		'Family' 		=> array(
		    'columns'           => 'family_id',
		    'refTableClass'     => 'Family',
		    'refColumns'        => 'id'
			),
		'Owner' 		=> array(
		    'columns'           => 'family_owner_id',
		    'refTableClass'     => 'FamilyOwner',
		    'refColumns'        => 'id'
			)
	);	
	
	// Declare filters
	protected $_filterRules = array(
		'*'				=> 'StripTags'
	);
	
	// Declare validators
	protected $_errorValidationRules = array(
		'email'	=> array(
			'EmailAddress',
			array('Uniqueness', "FamilyEmail", 'email'),
			'allowEmpty' => true,
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => 'The email address (%value%) is not valid',
				1 => 'This email address (%value%) already exists in the database'
			)
		)
	);
	
	protected $_useUpdate = true;
	
	// Declare validators
	protected $_updateErrorValidationRules = array(
		'email'	=> array(
			'EmailAddress',
			'allowEmpty' => true,
			'messages' => 'The email address (%value%) is not valid'
		)
	);
	
	protected function _modifyData(array $data)
	{
		if (empty($data["email"]))
			$data = array();
	
		return parent::_modifyData($data);
	}
}