<?php 
 
class RList extends Zarrar_Db_Table 
{ 
	protected $_name = 'lists';
    protected $_primary = "id";
	protected $_unique = "list";
	
	protected $_referenceMap    = array(
		'Lab' 		=> array(
		    'columns'           => 'lab_id',
		    'refTableClass'     => 'Lab',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> 'lab'
			)
	);
	
	protected $_dependentTables = array("Baby");
	
	public function getList($listId) {
		$query = "SELECT list FROM lists WHERE id = ?";
		
		// Execute
		$stmt = $this->getAdapter()->query($query, $listId);
		$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		
		// Check
		if (count($result) != 1)
			echo "error"; # throw an exception
		// Get List
		else
			$list = $result[0];
		
		return $list;
	}
	
	public function insert(array $data)
    {
		if (empty($data["is_permanent"]))
		    $data["is_permanent"] = 0;
		
        return parent::insert($data);
    }

	// Cannot actually update the password, just erase old one and set new one
	public function update(array $data, $where=array())
    {
        if (empty($data["is_permanent"]))
            $data["is_permanent"] = 0;
		
        return parent::update($data, $where);
    }
}
