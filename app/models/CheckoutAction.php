<?php 
 
class CheckoutAction extends Zend_Db_Table 
{ 
	protected $_name = 'checkout_actions';
    protected $_primary = "id";
	protected $_unique = "action";
	
	protected $_dependentTables = array("CheckoutHistory", "Baby");
}
