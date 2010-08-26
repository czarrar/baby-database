<?php 
 
class Lab extends Zarrar_Db_Table
{
	protected $_name = 'labs';
    protected $_primary = "id";
	protected $_unique = "lab";
	
	protected $_forSelect = array(
		'columns'	=> array('id', 'lab'),
		'where'	 	=> "to_use = 1"			
	);
	
	protected $_dependentTables = array('Researcher', "Study", "Callers", "Auth");
	
	public function decodeGCalPassword($pwd) {
		return base64_decode($pwd);
	}
	
	public function insert(array $data)
    {
		// Encrypt password
        $data["gcal_password"] = base64_encode($data["gcal_password"]);
		
        return parent::insert($data);
    }

	// Cannot actually update the password, just erase old one and set new one
	public function update(array $data, $where=array())
    {
        // Encrypt password (but only if different)
        $row = $this->fetchRow($where);
        if(trim($row->gcal_password) != $data["gcal_password"])
			$data["gcal_password"] = base64_encode($data["gcal_password"]);
		
        return parent::update($data, $where);
    }
}

