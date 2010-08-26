<?php 
 
class CheckoutHistory extends Zarrar_Db_Table 
{ 
	protected $_name = 'checkout_histories';
    protected $_primary = "id";
    
	protected $_referenceMap    = array(
		'Baby' 		=> array(
		    'columns'           => 'baby_id',
		    'refTableClass'     => 'Baby',
		    'refColumns'        => 'id'
			),
		'Researcher' 	=> array(
		    'columns'           => 'researcher_id',
		    'refTableClass'     => 'Researcher',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'researcher'
			),
		'Study' => array(
		    'columns'           => 'study_id',
		    'refTableClass'     => 'Study',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'study'
			)
	);
	
	public function insert(array $data)
    {
        // default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');

		// default value of baby_age is taken from the baby table
		if (empty($data['baby_age']))
			$data['baby_age'] = $this->_getBabyAge($data['baby_id']);
		
        return parent::insert($data);
    }

	public function filterInsert(array $data)
    {
        // default value for 'date_of_entry' is the current data
        if (empty($data['date_of_entry']))
            $data['date_of_entry'] = new Zend_Db_Expr('CURDATE()');

		// default value of baby_age is taken from the baby table
		if (empty($data['baby_age']))
			$data['baby_age'] = $this->_getBabyAge($data['baby_id']);
		
        return parent::filterInsert($data);
    }

	/**
	 * Given the id for a baby, this will return that babies age
	 * 	this is intended for inserting new
	 *
	 * @param integer $babyId
	 * @throws Zend_Db_Table_Exception
	 * 	If the baby record cannot be found
	 * @return integer
	 **/
	protected function _getBabyAge($babyId)
	{
		// Get db adapter
		$db = $this->getAdapter();
		
		// Create query to get baby date of birth
		$select = $db->select()
					->distinct()
					->from(array('b' => 'babies'),
						"dob")
					->where("id = ?", $babyId);
		// Get dob
		$stmt = $select->query();
		$dob = $stmt->fetch(Zend_Db::FETCH_COLUMN);
		
		// Get current age
		$curDate = new Zend_Date();
		$dobDate = new Zend_Date($dob, "YYYY-MM-dd");
		// Subtract date of birth from current date
		$curDate->sub($dobDate);
		// Get the year, which will be the age
		$age = (int) $curDate->get("YYYY-MM-dd");
		
		return $age;
	}
}
