<?php 
 
class FamilyPhone extends Zarrar_Db_Table 
{ 
	protected $_name = 'family_phones';
	protected $_primary = 'phone_number';
	protected $_sequence = false;

	protected $_referenceMap    = array(
		'Family' 		=> array(
		    'columns'           => 'family_id',
		    'refTableClass'     => 'Family',
		    'refColumns'        => 'id'
			),
		'Type' 		=> array(
		    'columns'           => 'family_setting_id',
		    'refTableClass'     => 'FamilySetting',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'setting'
			),
		'Owner' 		=> array(
		    'columns'           => 'family_owner_id',
		    'refTableClass'     => 'FamilyOwner',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'owner'
			)
	);	
	
	// Declare filters
	protected $_filterRules = array(
		'*'				=> 'StripTags',
		'phone_number'	=> 'Digits',
		'extension'     => 'Digits'
	);
	
	// Declare validators
	protected $_errorValidationRules = array(
		'phone_number'	=> array(
			array('StringLength', 10, 10),
			array('Uniqueness', "FamilyPhone", "phone_number"),
			'allowEmpty' => true,
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => 'The phone number (%value%) must be 10 digits long (xxx-xxx-xxxx)',
				1 => 'This phone number (%value%) already exists in the database'
			)
		)
	);
	
	protected $_useUpdate = true;
	
	protected $_updateErrorValidationRules = array(
		'phone_number'	=> array(
			array('StringLength', 10, 10),
			'allowEmpty' => true,
			'messages' => 'The phone number (%value%) must be 10 digits long (xxx-xxx-xxxx)'
		)
	);
	
	protected function _modifyData(array $data)
	{
		if (empty($data["phone_number"]))
			$data = array();
	
		return parent::_modifyData($data);
	}
}