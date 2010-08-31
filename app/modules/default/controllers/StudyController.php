<?php

class StudyController extends Zend_Controller_Action 
{

	/**
	 * Table Class for Studies
	 *
	 * @var Zend_Db_Table
	 **/
	protected $_table;

	# CRUD
	# Create, Retrieve, Update, and Delete (be able to sort i.e. with mochkit)
	
	# Fields: id, date_of_entry, researcher_id, study, to_use, lower_age, upper_age, odd/even
	
	/**
	 * Initialize any action
	 **/
	function init()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
		
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		// Add stylesheet for table
		$this->view
			->headLink()
			->appendStylesheet("{$this->view->dir_styles}/oranges-in-the-sky.css");
			
		// Instantiate table for later usage
		$this->_table = new Study();
	}
	
	/**
	 * Setup the form (for new or edit action)
	 *
	 * @return obj Zend_Form
	 **/
	private function _setupForm()
	{
		$form = new Zend_Form();
		$form->setAction("")
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
		
		// Create date_of_entry field (text field but with ajax)
		$dateOfEntry = $form->createElement("text", "date");
		$dateOfEntry->setLabel("Date of Entry")
					->addValidator('date')
					->setAllowEmpty(true)
					->setValue(date("Y-m-d"));
		$form->addElement($dateOfEntry);
		
		// Get researcher options
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("long", true, array("" => "Choose"));
		// Create researcher field (select)
		$researcher = $form->createElement("select", "researcher_id");
		$researcher->setLabel("Researcher")
					->setRequired(true)
					->setMultiOptions($researcherOptions);
		$form->addElement($researcher);
		
		// Create study field (textbox)
		$study = $form->createElement("text", "study");
		$study->setLabel("Study")
				->setRequired(true)
				->addValidator('stringLength', false, array(1, 100));
		$form->addElement($study);
		
		// Create google calendar calendar id
		$gcalId = $form->createElement("text", "gcal_calendar_id");
		$gcalId->setLabel("Gcal Calendar ID");
		$form->addElement($gcalId);
		
		// Create to use (checkbox)
		$toUse = $form->createElement("checkbox", "to_use");
		$toUse->setLabel("Active?")
				->setChecked(true);
		$form->addElement($toUse);
		
		// Add all, odd, even (select)
		$oddEven = $form->createElement("select", "odd_even");
		$oddEven->setLabel("Odd/Even")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					0 => "All",
					1 => "Odd",
					2 => "Even"
				));
		$form->addElement($oddEven);
		
		// Set decorators
		$form->setElementDecorators(array(
			'ViewHelper',
			'Label'
		));
		// Set universal filters
		$form->setElementFilters(array('StringTrim', 'StripTags'));
		
		// Add lower and upper ages (age range of babies for study)
		$yearRange = array("years" => array(0,18));
		$lowerAge = new Zarrar_Form_SubForm_Age($yearRange);
		$form->addSubForm($lowerAge, 'lower_age');
		$upperAge = new Zarrar_Form_SubForm_Age($yearRange);
		$form->addSubForm($upperAge, 'upper_age');
				
		// Submit
		$submit = new Zend_Form_Element_Submit("submit");
		$form->addElement($submit);
		
		return($form);
	}
	
	public function indexAction()
	{
		$this->_forward("list");
	}
	
	/**
	 * Create a new study
	 **/
	function newAction()
	{
		$form = $this->_setupForm();
		
		// Posted something?
		if($this->getRequest()->isPost()) {
			// Get data
			$formData = $this->getRequest()->getPost();
			
			// Check if valid
			if (!$form->isValid($formData)) {
				// Save error messages
				$errors = $form->getMessages();
			} else {
				// Add into database
				$result = $this->_newStudy($form->getValues());
				// Check if good
				if ($result === TRUE)
					$this->_forward("success", "study");
				else
					$errors = $result;
			}
		}
		
		// Save to view
		$this->view->form = $form;
		$this->view->errors = $errors;
	}
	
	/**
	 * Inserts study information into the database
	 *
	 * @param array $data Data from form
	 * @return boolean|array
	 * 	Returns TRUE if everything good but an array of errors if not good
	 **/
	protected function _newStudy(array $data)
	{			
		try {
			// Begin transaction
			$db = Zend_Registry::get('db');
			$db->beginTransaction();
			
			// Insert into study table
			$studyTbl = new Study();
			$studyTbl->filterInsert($data);
			
			// Finish
			$db->commit();
			
			return TRUE;
		} catch(Exception $e) {
			// No good, rollback changes
			$db->rollback();
			// Create error message
			$errors = array(
				'info' => array(
					'ERROR entering information into database',
					"<strong>" . $e->getMessage() . "</strong>"
				)
			);
			return $errors;
		}
	}
	
	/**
	 * Update a current study
	 **/
	public function editAction()
	{
		// Need study id
		$studyId = (int) $this->_getParam("study");
		if (empty($studyId))
			throw new Zend_Controller_Action_Exception("Whoops a study id was not specified! There is nothing to update.");
	
		// Setup form
		$form = $this->_setupForm();
		
		// Posted something?
		if($this->getRequest()->isPost()) {
			// Get data
			$formData = $this->getRequest()->getPost();
			
			// Check if valid
			if (!$form->isValid($formData)) {
				// Save error messages
				$errors = $form->getMessages();
			} else {
				// Add into database
				$result = $this->_editStudy($form->getValues(), $studyId);
				// Check if good
				if ($result === TRUE)
					$this->_forward("success", "study");
				else
					$errors = $result;
			}
		} else {
			/* FIRST TIME ON PAGE */
			
			// Get study specific row from db
			$studyTbl = $this->_table;
			$select = $studyTbl->select()->where("id = ?", $studyId);
			$studyRow = $studyTbl->fetchRow($select);
			
			// Take out any null values
			$studyData = array_filter($studyRow->toArray());
			
			if($studyRow)
				$form->populate($studyData);
			else
				throw new Zend_Controller_Action_Exception("Could not find study with id '$studyId'.");
		}
		
		// Save to view
		$this->view->form = $form;
		$this->view->errors = $errors;
	}
	
	/**
	 * Update study entry in db
	 *
	 * @param array $data
	 * @return boolean|array Can be TRUE for success or array holding errors
	 **/
	protected function _editStudy(array $data, $studyId)
	{
		try {
			// Begin transaction
			$db = Zend_Registry::get('db');
			$db->beginTransaction();
			
			// Update study table
			$studyTbl = new Study();
			$where = $db->quoteInto("id = ?", $studyId);
			$studyTbl->filterUpdate($data, $where);
			
			// Finish
			$db->commit();
			
			return TRUE;
		} catch(Exception $e) {
			// No good, rollback changes
			$db->rollback();
			// Create error message
			$errors = array(
				'info' => array(
					'ERROR entering information into database',
					"<strong>" . $e->getMessage() . "</strong>"
				)
			);
			return $errors;
		}
	}
	
	/**
	 * Lists all studies that are in the db
	 **/
	public function listAction()
	{
		# 1. SETUP HEADER STUFF
		$dirs = Zend_Registry::get('dirs');
		// Attach spreadsheet for table
		$this->view->headLink()
			->prependStylesheet("{$dirs->styles}/sortable_tables.css", "screen, projection");
		// Attach scripts for dynamic sorting of table
		$this->view->headScript()
			->prependFile("{$dirs->scripts}/sortable_tables.js")
			->prependFile("{$dirs->scripts}/MochiKit/MochiKit.js");
		
		# 2. GET ROWS
		// Get select field
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->from(array('s' => "studies"),
				array('id', 'date_of_entry', 'study', 'to_use', 'lower_age', 'upper_age', 'odd_even', 'gcal_calendar_id'))
			->joinLeft(array('r' => "researchers"),
				"s.researcher_id = r.id", array("researcher"))
			->joinLeft(array('l' => "labs"),
				"r.lab_id = l.id", array("lab"));
		// Check if want to display archived
		$viewArchive = $this->_getParam("view_archive");
		switch ($viewArchive) {
			// View only archived (to_use = 0)
			case 1:
				$select->where("s.to_use = ?", 0);
				$this->view->viewArchived = TRUE;
				break;
			// View only active (to_use = 1)
			case 2:
				$select->where("s.to_use = ?", 1);
				$this->view->viewActive = TRUE;
				break;
			// View all
			default:
				$this->view->viewAll = TRUE;
				break;
		}
		// Fetch and Save rows
		$rows = $db->fetchAll($select);
		$this->view->results = $rows;
		
		# 3. ID PADDING FOR SORTING
		// Get the id of the last row (assuming that they are in order of id)
		$tmpRows = $rows;
		$lastRow = array_pop($tmpRows);
		$lastId = $lastRow["id"];
		// Get the length of id + padding length to have proper sorting
		$this->view->idPad = strlen($lastId);
	}
	
	/**
	 * Delete or Undelete Study (b/c of to_use field nothing is really deleted)
	 **/
	public function deleteAction()
	{
		// Need study id
		$studyId = (int) $this->_getParam("study");
		if (empty($studyId))
			throw new Zend_Controller_Action_Exception("Whoops a study id was not specified! There is nothing to update.");
			
		// Need undelete or delete
		$whatDo = (string) $this->_getParam("todo");
		if (empty($whatDo))
			throw new Zend_Controller_Action_Exception("Whoops need to know what to do.");
		
		// Start to get data to update
		if ($whatDo == "do")
			$data = array("to_use" => 0);
		elseif ($whatDo == "undo")
			$data = array("to_use" => 1);
		
		// Delete or Undelete now
		$result = $this->_editStudy($data, $studyId);
		
		// What happened?
		if ($result === TRUE)
			$this->_forward("success", "study", null, array());
		else
			echo "FAILURE";
	}
	
	/**
	 * Stuff is good, don't worry
	 **/
	public function successAction()
	{
		$this->view->message = "Whatever you just did was a success!";
	}
}
