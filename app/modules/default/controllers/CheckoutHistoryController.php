<?php


class CheckoutHistoryController extends Zend_Controller_Action 
{
	function init()
	{
		// Leave header out
		$this->view->headerFile = '_empty.phtml';
	}
	
	function indexAction()
	{
		$this->_forward('list', 'checkout-history');
	}
	
	public function listAction()
	{
		// Attach additional css file for table
		$this->view->headLink()
			->appendStylesheet("/athena/public/styles/sortable_tables.css", "screen, projection");
		
		// Display the list with crud.phtml script
		// but only if is_crud is not set (which prevents infinite loop)
		if ($this->_getParam("is_crud") != 1) {
			// Set the script action file
			$this->_helper->viewRenderer->setScriptAction('crud');
		}
		
		// Get the baby id, if none then throw exception
		if ($this->_getParam('baby_id')) {
			$babyId = $this->_getParam('baby_id');
			$this->view->babyId = $babyId;
		}
		else
			throw new Exception("No baby id given! Nothing to display");
		
		/* Get db adapter */
		
		$db = Zend_Registry::get('db');
		
		/* Setup base query */
		
		$select = $db->select()
			->distinct()
		
		// Want to display these columns:
		// lab, researcher, date, action, baby age
		
	 	// Get date_of_entry, baby_age, checked_out
		// from base table (checkout_histories)
		->from(array('ch' => 'checkout_histories'),
	        array("date" => "date_of_entry", "babyage" => "baby_age", "action" => "checked_out"))
		// Get researcher name
		->joinLeft(array('r' => 'researchers'),
			"ch.researcher_id = r.id", "researcher")
		// Get lab name
		->joinLeft(array('l' => "labs"),
			"r.lab_id = l.id", "lab")
		
		// Want only rows with given baby_id
		->where("ch.baby_id = ?", $babyId)
		// Order the rows by the date_of_entry of row entry
		->order("ch.date_of_entry DESC");
		
		/* Execute Query */
		$db->setFetchMode(Zend_Db::FETCH_OBJ);
		$stmt = $db->query($select);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Save into view
		$this->view->assign("results", $result);
	}
}