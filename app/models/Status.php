<?php 
 
class Status extends Zarrar_Db_Table 
{ 
	protected $_name = 'statuses';
    protected $_primary = "id";
	protected $_unique = "status";
	
	protected $_dependentTables = array("Baby");
	
	public function getStatus($statusId) {
		$query = "SELECT status FROM statuses WHERE id = ?";
		
		// Execute
		$stmt = $this->getAdapter()->query($query, $statusId);
		$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		
		// Check
		if (count($result) != 1)
			echo "error"; # throw an exception
		// Get Status
		else
			$status = $result[0];
		
		return $status;
	}
}