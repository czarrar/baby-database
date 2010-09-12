<?php


class StudyHistoryController extends Zend_Controller_Action 
{
	function init()
	{
		// Leave header out
		$this->view->headerFile = '_empty.phtml';
	}
	
	function indexAction()
	{
		$this->_forward('list', 'study-history');
	}
	
	public function listAction()
	{
		$dirs = Zend_Registry::get('dirs');
	
		// Attach additional css file for table
		$this->view->headLink()
			->appendStylesheet("{$dirs->styles}/sortable_tables.css", "screen, projection");
		
		// Display the list with crud.phtml script
		// but only if is_crud is not set (which prevents infinite loop)
		#if ($this->_getParam("is_crud") != 1) {
		#	// Set the script action file
		#	$this->_helper->viewRenderer->setScriptAction('crud');
		#}
		
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
		// study, lab, researcher, xappointment, outcome, comments,
		// xlevel enthusiasm, xfurther study
		
	 	// Get appointment, level enthusiasm, allow further, comments
		// from base table (study_histories)
		->from(array('sh' => 'study_histories'),
	        array("appointment", "enthusiasm" => "level_enthusiasm", "further" => "allow_further", "comments"))
		// Get study name
	    ->joinLeft(array('s' => 'studies'),
	        'sh.study_id = s.id', "study")
		// Get researcher name
		->joinLeft(array('r' => 'researchers'),
			"s.researcher_id = r.id", "researcher")
		// Get lab name
		->joinLeft(array('l' => "labs"),
			"r.lab_id = l.id", "lab")
		// Get study outcome
		->joinLeft(array("so" => "study_outcomes"),
			"sh.study_outcome_id = so.id", "outcome")
		
		// Want only rows with given baby_id
		->where("sh.baby_id = ?", $babyId)
		// Order the rows by the appointment of row entry
		->order("sh.appointment DESC");
		
		/* Execute Query */
		$db->setFetchMode(Zend_Db::FETCH_OBJ);
		$stmt = $db->query($select);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Find if doing any studies now!
		$select = $db->select()
			->distinct()
			->from(array('bs' => 'baby_studies'), array('appointment', 'comments'))
			->joinLeft(array('s' => 'studies'), 'bs.study_id = s.id', array("study"))
			->joinLeft(array('r' => 'researchers'), 's.researcher_id = r.id', array("researcher"))
			->joinLeft(array('l' => 'labs'), 'r.lab_id = l.id', array('lab'))
			->where("bs.baby_id = ?", $babyId);
		$stmt = $db->query($select);
		$stmt->execute();
		$bsRows = $stmt->fetchAll();
		
		if(count($bsRows)>0)
			$this->view->currentBabyStudies = $bsRows;
		else
			$this->view->currentBabyStudies = FALSE;
		
        // Find out days since last visit (use age calculator)
        if (count($result) > 0) {
            $lastRow = count($result) - 1;
            $calculator = new Zarrar_AgeCalculator();
    		$calculator->setDob(substr($result[$lastRow]->appointment, 0, 10))
    				   ->setDate(date('Y-m-d'));
    	    $this->view->daysLastVisit = $calculator->getAge("full");
        } else {
            $this->view->daysLastVisit = FALSE;
        }
		
		// Save into view
		$this->view->assign("results", $result);
	}
}
