<?php 
 
class BabyLanguage extends Zarrar_Db_Table 
{ 
	protected $_name = 'baby_languages';
    protected $_primary = array('baby_id', 'language_id');
	protected $_sequence = false;
    
	protected $_referenceMap    = array(
		'Baby' 		=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Language' 	=> array(
		    'columns'           => 'language_id',
		    'refTableClass'     => 'Language',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'language'
			)
	);
	
	// Declare filters
	protected $_filterRules = array(
		'*'				=> 'StripTags'
	);
	
	// Declare validators
	protected $_errorValidationRules = array(
		'percent_per_week'	=> array(
			'Digits',
			'allowEmpty' => true,
			'messages' => "Language field 'percent per week' can only have whole numbers"
		)
	);
	
	protected function _modifyData(array $data)
	{	
		// Insert/update data with language name and not language id
		if (isset($data["language"]) and $data["language"]) {
			// Sanitize language name
			$name = $data["language"];
			$name = trim($name);
			
			// Check if in db already
			$languages = new Language();
			$where = $languages->getAdapter()->quoteInto("language LIKE ?", $name);
			$language = $languages->fetchRow($where);
			
			// Use old value
			if ($language) {
				$data["language_id"] = $language->id;
			}
			// Insert new value and use
			else {
				$id = $languages->insert(array("language" => $name));
				if ($id)
					$data["language_id"] = $id;
			}
			
			unset($data["language"]);
		}
	
		if (empty($data["language_id"]))
			$data = array();
	
		return parent::_modifyData($data);
	}
}