<?php 
 
class Auth extends Zarrar_Db_Table
{ 
	protected $_name = 'auth';
    protected $_primary = array('id');
	protected $_unique = 'username';
	
	protected $_referenceMap    = array(
		'Lab'					=> array(
		    'columns'           => 'lab_id',
		    'refTableClass'     => 'Lab',
		    'refColumns'        => 'id',
			'refDisplayColumn'	=> "lab"
			),
	);	
	
	public function insert(array $data)
    {
		// Encrypt password
        $data["password"] = md5($data["password"]);
		
        return parent::insert($data);
    }

	// Cannot actually update the password, just erase old one and set new one
	public function update(array $data, $where=array())
    {
        // Encrypt password (but only if different)
        $row = $this->fetchRow($where);
        if($row->password != $data["password"])
			$data["password"] = md5($data["password"]);

        return parent::update($data, $where);
    }
}
