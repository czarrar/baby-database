 <?php 
 
class IpControl extends Zarrar_Db_Table 
{ 
	protected $_name = 'ip_control';
    protected $_primary = array('id');
	protected $_unique = "allowedIp";
}