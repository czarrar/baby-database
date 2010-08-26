<?php


class BabyStudyController extends Zend_Controller_Action 
{
	// Numbers (ids in status table) corresponding to some status
	const CONTACTING	= 1;
	const ARCHIVED 		= 2;
	const SCHEDULED 	= 3;
	const CONFIRMED 	= 4;
	const RUN			= 5;
	const CANCELED		= 6;
	const NO_SHOW		= 7;
	
	// Number (ids in study_outcome_id) corresponding to some study outcome
	const OUTCOME_RUN		= 1;
	const OUTCOME_NOSHOW	= 2;
	const OUTCOME_CANCELED	= 3;
	
	/** 
	 * @todo: add generic message here
	 **/
	
	/**
	 * Helps process form information
	 *
	 * @var Zend_Controller_Action_Helper_FormSearch
	 **/
	protected $_form;

	/**
	 * Error validation rules for form to schedule appointments
	 *
	 * @var array
	 **/
	protected $_neweditValidationRules = array(
		'myfields' => array(
			'ValidFields',
			'fields' => array('study', 'month', "day", "year", "hour", "minute", "sibling", "comments")
		),
		'study'	=> array(
			'NotEmpty',
			'messages' => "No study selected"
		)
	);
	
	/**
	 * Error validation rules for form to search
	 *
	 * @var array
	 **/
	protected $_searchValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 	=> array('researcher', 'study', 'date', 'per_page')
		),
		'researcher'	=> array(
			'NotEmpty',
			"messages"	=> "Researcher name required (or select ALL)"
		),
		'study'	=> array(
			'NotEmpty',
			"messages"	=> "Study name required (or select ALL)"
		),
		'date'			=> array(
			'Date',
			'allowEmpty' => true
		)
	);
	
	/**
	 * Error validation rules for form to confirm appointments
	 *
	 * @var array
	 **/
	protected $_confirmValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 	=> array('researcher', 'study', 'date1', 'date2', 'alldates', 'per_page')
		),
		'researcher'	=> array(
			'NotEmpty',
			"messages"	=> "Researcher name required (or select ALL)"
		),
		'study'	=> array(
			'NotEmpty',
			"messages"	=> "Study name required (or select ALL)"
		),
		'date1'			=> array(
			'Date',
			'allowEmpty' => true
		),
		'date2'			=> array(
			'Date',
			'allowEmpty' => true
		)
	);


	/**
	 * Actions:
	 * 	* although table simple this controller will list
	 * 		things that are in verify appointment
	 * 	- list (for a study or ?baby?)
	 * 	- view (for a baby + study)
	 * 	- New/Edit: lab name, name of study, appointment date/time, comments (? link to insert into google calendar)
	 * 	- delete...
	 **/
	
	function init()
	{
		//$this->view->headerFile = '_empty.phtml';
		$this->_formSearch = $this->_helper->FormSearch;
		$this->_formCreate = $this->_helper->FormCreate;
	}


/********************************
 *	SUCCESS OF ACTION FUNCTIONS	*
 ********************************/	

	/**
	 * ACTION: successful db action (e.g. insert/update)
	 **/
	public function successAction()
	{
		// Get message
		$message = $this->_getParam("message");
		// Get baby id
		$babyId = $this->_getParam("baby_id");
		// Get study id
		$studyId = $this->_getParam("study_id");
		
		if (empty($message) or empty($babyId) or empty($studyId))
			throw new Zend_Controller_Action_Exception("Message, baby id, or study id is missing.");
			
		// Get baby name
		$babyTbl = new Baby();
		$select = $babyTbl->select()->where("id = ?", $babyId);
		$babyRow = $babyTbl->fetchRow($select);
		$babyName = $babyRow->first_name . " " . $babyRow->last_name;
		// Get study name
		$studyTbl = new Study();
		$select = $studyTbl->select()->where("id = ?", $studyId);
		$studyRow = $studyTbl->fetchRow($select);
		$studyName = $studyRow->study;
		
		// Get baby name and study name into message
		$message = str_replace("BABY", $babyName, $message);
		$message = str_replace("STUDY", $studyName, $message);
		
		// Set message to view
		$this->view->message = $message;
	}



/********************************
 *	SCHEDULING BABY FUNCTIONS	*
 ********************************/	

	/**
	 * ACTION: Form to schedule a baby for study
	 **/
	public function scheduleAction()
	{
		// Get baby id (required)
		$babyId = $this->_getParam("baby_id");
		if (empty($babyId))
			throw new Zend_Controller_Action_Exception("Please provide a baby id!");
				
		// Get study id (optional)
		$studyId = $this->_getParam("study_id");
		
		// Get form + populate w/defaults
		$form = $this->_scheduleForm();
		$form->populate(array(
			"baby_id"	=> $babyId,
			"study_id"	=> $studyId
		));
		
		// Posted something?
		if($this->getRequest()->isPost()) {
			// Get data
			$formData = $this->getRequest()->getPost();
			
			// Check if valid
			if (!$form->isValid($formData)) {
				// Save error messages
				$errors = $form->getMessages();
			} else {
				try {
					// Begin transaction
					$db = Zend_Registry::get('db');
					$db->beginTransaction();
					
					// Schedule baby for new study!
					$result = $this->_insertSchedule($form->getValues());
					
					$db->commit();
					
					// Success!
					$this->_forward("success", "baby-study", null, array("message" => "Sucess! Baby 'BABY' is scheduled for study 'STUDY' on {$result}", "baby_id" => $babyId, "study_id" => $studyId));
				} catch(Exception $e) {
					$db->rollback();
					// Crap, scheduling baby did not work!
					$errors = array("ERROR" => array("info" => $e->getMessage()));
				}
			}
		}
		
		// Save vars into view
		$this->view->form = $form;
		$this->view->errors = $errors;
	}
	
	/**
	 * Creates form for scheduling babies
	 *
	 * @return Zend_Form
	 **/
	protected function _scheduleForm()
	{
		# FIELDS: baby id, study, appointment (date/time), sibling, comments
		# FIELDS TO SUBMIT: baby_id, study_id, appointment, comments
	
		# Basics
		$form = new Zend_Form();
		$form->setAction($this->view->sUrl("schedule", "baby-study"))
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
		
		# BABY ID
		$babyId = $form->createElement("hidden", "baby_id");
		$babyId->setLabel("Serial No")
				->setRequired(true);
		
		# STUDY
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(true)
				->setMultiOptions($studyOptions);
		
		# Sibling
		// Set options
		$siblingOptions = array(
			""	=> "No",
			1	=> "Yes"
		);
		// Create select field
		$sibling = $form->createElement("select", "sibling");
		$sibling->setLabel("Sibling Coming")
				->setMultiOptions($siblingOptions);
				
		# Comments
		$comments = $form->createElement("textArea", "comments");
		$comments->setLabel("Comments")
				 ->setAttrib("rows", 4)
				 ->setAttrib("cols", 42);
		
		// Add elements
		$form->addElements(array(
			$babyId,
			$study,
			$sibling,
			$comments
		));
		// Set decorators
		$form->setElementDecorators(array(
			'ViewHelper',
			'Label'
		));
		// Set universal filters
		$form->setElementFilters(array('StringTrim', 'StripTags'));
		
		# APPOINTMENT
		// Add date
		$yearRange = array("years" => 1);
		$date = new Zarrar_Form_SubForm_Date($yearRange);
		$date->setRequired(TRUE);
		$form->addSubForm($date, 'appt_date');
		// Add time
		$time = new Zarrar_Form_SubForm_Time();
		$time->setRequired(TRUE);
		$form->addSubForm($time, 'appt_time');
		
		# SUBMIT
		$submit = new Zend_Form_Element_Submit("submit", "Schedule Appointment");
		$form->addElement($submit);
		
		return($form);
	}
	
	/**
	 * SCHEDULES BABY
	 * Inserts record for baby participating in a study
	 *
	 * @param array $data Form data to insert
	 * @return string ???
	 **/
	protected function _insertSchedule(array $data)
	{
		/**
		 * Result of scheduling
		 * 	1) change scheduling status to 3 or scheduled
		 * 	2) insert into study histories
		 *  3) insert into baby studies
		 * 	4) if not checked out, then check out baby
		 * 	@todo: email researcher about study
		 * 	@todo: email parent about study
		 * 	@todo: google calendar functional
		 **/
		
		$db = Zend_Registry::get('db');
		
		// Get + add caller_id
		$callerId = $_SESSION['caller_id'];
		if (empty($callerId))
			throw new Zend_Controller_Action_Exception("Could not find the caller id in the session variables!");
		$data["caller_id"] = $callerId;
			
		// Set the appointment time
		$data['appointment'] = $data['appt_date'] . " " . $data['appt_time'];
		unset($data["appt_date"]);
		unset($data["appt_time"]);
		
		# 1. Change scheduling status to 2
		// Update status in baby table
		$babyTbl = new Baby();
		$where = $db->quoteInto("id = ?", $data["baby_id"]);
		$babyData = array(
			"status_id"		=> self::SCHEDULED
		);
		$babyTbl->filterUpdate($babyData, $where);
		
		# 2. Insert new history into study histories
		$studyHistoryTbl = new StudyHistory();
		$studyHistoryId = $studyHistoryTbl->filterInsert($data);
		
		# 3. Associate baby with study
		$babyStudyTbl = new BabyStudy();
		$babyStudyTbl->filterInsert($data + array("study_history_id" => $studyHistoryId));
		
		# 4. Checkout if not checked out
		$babyRow = $babyTbl->fetchRow($where);
		// If not checked out, then...
		if ($babyRow->checked_out == 0) {
			// a. Checkout baby + update checkout date
			$babyData = array(
				"checked_out"	=> 1,
				"checkout_date"	=> new Zend_Db_Expr('CURDATE()')
			);
			$babyTbl->filterUpdate($babyData);
			// b. Update checkout history
			$checkoutHistoryTbl = new CheckoutHistory();
			$checkoutHistoryTbl->filterInsert(array_merge($data, $babyData));
		}
		
		return $data["appointment"];
	}
	

	
/****************************************
 *	CONFIRMING/CHANGING BABY FUNCTIONS	*
 ****************************************/	
	
	/**
	 * ACTION: Form to confirm/change a baby for study
	 **/
	public function confirmAction()
	{
		// Get baby id (required)
		$babyId = $this->_getParam("baby_id");
		if (empty($babyId))
			throw new Zend_Controller_Action_Exception("Please provide a baby id!");
				
		// Get study id (required)
		$studyId = $this->_getParam("study_id");
		if (empty($studyId))
			throw new Zend_Controller_Action_Exception("Please provide a study id!");
		
		// Get form
		$form = $this->_confirmForm();
		
		// Posted something?
		if($this->getRequest()->isPost()) {
			// Get data
			$formData = $this->getRequest()->getPost();
			
			// Check if valid
			if (!$form->isValid($formData)) {
				// Save error messages
				$errors = $form->getMessages();
			} else {
				try {
					// Begin transaction
					$db = Zend_Registry::get('db');
					$db->beginTransaction();
					
					// Schedule baby for new study!
					$result = $this->_confirmStudy($form->getValues());
					
					$db->commit();
					
					// Success!
					$this->_forward("success", "baby-study", null, array("message" => "Sucess! Baby 'BABY' scheduling for study 'STUDY' on {$result}", "baby_id" => $babyId, "study_id" => $studyId));
				} catch(Exception $e) {
					$db->rollback();
					// Crap, scheduling baby did not work!
					$errors = array("ERROR" => array("info" => $e->getMessage()));
				}
			}
		} else {
			# 1. Fetch appt time and comments
			
			// Get db adapter
			$db = Zend_Registry::get('db');

			// Build query to get study comments and appointment, study name, lab name
			$query = "SELECT bs.comments, bs.appointment, bs.study_history_id FROM baby_studies AS bs WHERE bs.baby_id = ? AND bs.study_id = ?";

			// Execute query
			$stmt = $db->query($query, array($babyId, $studyId));
			$stmt->execute();
			$result = $stmt->fetchAll();

			// How many rows
			$rowCount = count($result);

			if ($rowCount < 1)
				$errors = array(
					"ERROR" => array("info" => "The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'")
				);
			else
				$row = $result[0];
						
			# 2. Populate with defaults
			$form->populate(array(
				"baby_id"			=> $babyId,
				"study_id"			=> $studyId,
				"study_history_id"	=> $row["study_history_id"],
				"appt_date" 		=> array("my_date" => $row["appointment"]),
				"appt_time" 		=> array("my_time" => $row["appointment"]),
				"comments" 			=> $row["comments"],
				"submit"			=> "Schedule Appointment"
			));
		
		}
		
		// Save vars into view
		$this->view->form = $form;
		$this->view->errors = $errors;
	}
	
	/**
	 * Creates form for scheduling babies
	 *
	 * @return Zend_Form
	 **/
	protected function _confirmForm()
	{
		# FIELDS: baby id, study, appointment (date/time), comments
		# FIELDS TO SUBMIT: baby_id, study_id, appointment, comments
	
		# Basics
		$form = new Zend_Form();
		$form->setAction($this->view->sUrl("confirm", "baby-study"))
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
		
		# BABY ID
		$babyId = $form->createElement("hidden", "baby_id");
		$babyId->setLabel("Serial No")
				->setRequired(true);
				
		# STUDY HISTORY ID
		$studyHistory = $form->createElement("hidden", "study_history_id");
		$studyHistory->setRequired(true);
		
		# STUDY
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(true)
				->setMultiOptions($studyOptions);
				
		# CHECK IN OPTION (IF CANCELING)
		$checkIn = $form->createElement("checkbox", "check_in");
		$checkIn->setLabel("Check In (if canceling)");
		
		# Comments
		$comments = $form->createElement("textArea", "comments");
		$comments->setLabel("Comments")
				 ->setAttrib("rows", 4)
				 ->setAttrib("cols", 42);
		
		// Add elements
		$form->addElements(array(
			$babyId,
			$studyHistory,
			$study,
			$checkIn,
			$comments
		));
		// Set decorators
		$form->setElementDecorators(array(
			'ViewHelper',
			'Label'
		));
		// Set universal filters
		$form->setElementFilters(array('StringTrim', 'StripTags'));
		
		# APPOINTMENT
		// Add date
		$yearRange = array("years" => 1);
		$date = new Zarrar_Form_SubForm_Date($yearRange);
		$date->setRequired(TRUE);
		$form->addSubForm($date, 'appt_date');
		// Add time
		$time = new Zarrar_Form_SubForm_Time();
		$time->setRequired(TRUE);
		$form->addSubForm($time, 'appt_time');
		
		# SUBMIT
		// Confirm
		$confirm = new Zend_Form_Element_Submit("confirm", "Confirm/Change");
		$confirm->setDecorators(array("ViewHelper"));
		$form->addElement($confirm);		
		// Cancel
		$cancel = new Zend_Form_Element_Submit("cancel", "Cancel");
		$cancel->setDecorators(array("ViewHelper"));
		$form->addElement($cancel);
		// Change
		$change = new Zend_Form_Element_Submit("save", "Change");
		$change->setDecorators(array("ViewHelper"));
		$form->addElement($change);
		
		return($form);
	}
	
	/**
	 * CONFIRM/CHANGE BABY
	 * Updates record for baby participating in a study
	 * Confirms baby appointment
	 *
	 * @param array $data Form data to insert
	 * @return string ???
	 **/
	protected function _confirmStudy(array $data)
	{
	/**
	 * Result of confirm:
	 * 	- cancel:
	 * 		1) change status to CANCELED
	 * 		2) update study history
	 * 		3) remove from baby studies
	 * 		4) check in if set
	 * 	- change:
	 * 		1) change entries in baby_studies and study_history
	 * 	- change+confirm:
	 * 		1) change entries in baby_studies and study_history
	 * 		2) change status to CONFIRMED
	 **/
				
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		# PREPROCESS
		// Set the appointment time
		$data['appointment'] = $data['appt_date'] . " " . $data['appt_time'];
		unset($data["appt_date"]);
		unset($data["appt_time"]);		
		
		# WHAT TO DO
		// Cancel
		$isCanceled = (isset($data["cancel"])) ? True : False ;
		// Save
		$isSave = (isset($data["save"]) or isset($data["confirm"])) ? True : False ;
		// Confirm + Save
		$isConfirm = (isset($data["confirm"])) ? True : False ;
		
		# SETUP COMMON TABLES
		// Baby Studies
		$babyStudyTbl = new BabyStudy();
		$babyStudyWhere = array(
			$db->quoteInto("study_history_id = ?", $data["study_history_id"]),
			$db->quoteInto("baby_id = ?", $data["baby_id"]),
		  	$db->quoteInto("study_id = ?", $data["study_id"])
		);
		// Study Histories
		$studyHistoryTbl = new StudyHistory();
		$studyHistoryWhere = $db->quoteInto("id = ?", $data["study_history_id"]);
		// Baby
		$babyTbl = new Baby();
		$babyWhere = $db->quoteInto("id = ?", $data["baby_id"]);
		
		# A. CANCEL
		if ($isCanceled) {
			// 1. Change status
			$babyData = array(
				"status_id"	=> self::CANCELED
			);
			$babyTbl->filterUpdate($babyData, $babyWhere);
			
			// 2. Update study history
			$shData = array(
				"date_cancel" 		=> new Zend_Db_Expr("CURDATE()"),
				"study_outcome_id"	=> self::OUTCOME_CANCELED
			);
			$studyHistoryTbl->filterUpdate($shData, $studyHistoryWhere);
			
			// 3. Remove from baby studies
			$babyStudyTbl->delete($babyStudyWhere);
			
			// 4. Check in if desired
			if ($data["check_in"]) {
				$babyData = array(
					'checked_out'	=> 0
				);
				$babyTbl->filterUpdate($babyData, $babyWhere);
			}
			
			return $data["appointment"] . " is CANCELED";
		}
		
		# B. SAVE
		if ($isSave) {			
			// 1. Update entry in baby studies
			$babyStudyTbl->filterUpdate($data, $babyStudyWhere);
			
			// 2. Update entry in study histories
			$studyHistoryTbl->filterUpdate($data, $studyHistoryWhere);
		}
		
		# C. CONFIRM
		if ($isConfirm) {
			// 1. Update scheduling status
			$babyData = array(
				"status_id"	=> self::CONFIRMED
			);
			$babyTbl->filterUpdate($babyData, $babyWhere);
			
			return $data["appointment"] . " is SAVED and CONFIRMED";
		}
		
		return $data["appointment"] . " is SAVED";
	}
	
	
/****************************
 *	OUTCOME BABY FUNCTIONS	*
 ****************************/

	/**
	 * ACTION: Form to set outcome for a baby in study
	 **/
	public function outcomeAction()
	{
		// Get baby id (required)
		$babyId = $this->_getParam("baby_id");
		if (empty($babyId))
			throw new Zend_Controller_Action_Exception("Please provide a baby id!");

		// Get study id (required)
		$studyId = $this->_getParam("study_id");
		if (empty($studyId))
			throw new Zend_Controller_Action_Exception("Please provide a study id!");

		// Get form
		$form = $this->_outcomeForm();

		// Posted something?
		if($this->getRequest()->isPost()) {
			// Get data
			$formData = $this->getRequest()->getPost();

			// Check if valid
			if (!$form->isValid($formData)) {
				// Save error messages
				$errors = $form->getMessages();
			} else {
				try {
					// Begin transaction
					$db = Zend_Registry::get('db');
					$db->beginTransaction();

					// Process outcome of baby in new study!
					$result = $this->_processOutcome($form->getValues());

					$db->commit();

					// Success!
					$this->_forward(
						"success", 
						"baby-study", 
						null, 
						array(
							"message" => "Sucess! Baby 'BABY' scheduled for study 'STUDY' on {$result}", 
							"baby_id" => $babyId, 
							"study_id" => $studyId
						)
					);
				} catch(Exception $e) {
					// Crap, outcome did not work!
					$db->rollback();
					$errors = array("ERROR" => array("info" => $e->getMessage()));
				}
			}
		} else {
			# 1. Fetch appt time, comments, and study history id
			$babyStudyTbl = new BabyStudy();
			$bsInfo = $babyStudyTbl->getBasics($babyId, $studyId);
			
			# 2a. Did not find anything
			if (empty($bsInfo)) {
				$errors = array(
					"ERROR" => array(
						"info" => "The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'"
				));
			}
			# 2b. Populate with defaults
			else {
				$form->populate(array(
					"baby_id"			=> $babyId,
					"study_id"			=> $studyId,
					"study_history_id"	=> $row["study_history_id"],
					"appt_date" 		=> array("my_date" => $row["appointment"]),
					"appt_time" 		=> array("my_time" => $row["appointment"]),
					"comments" 			=> $row["comments"],
					"submit"			=> "Schedule Appointment"
				));
			}
		}

		// Save vars into view
		$this->view->form = $form;
		$this->view->errors = $errors;
	}

	/**
	 * Creates form for giving outcome of baby in study
	 *
	 * @return Zend_Form
	 **/
	protected function _outcomeForm()
	{
		# FIELDS:
		#	baby id, study, study_history_id, appointment (date/time), comments
		#
		
		# Basics
		$form = new Zend_Form();
		$form->setAction($this->view->sUrl("outcome", "baby-study"))
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');

		# BABY ID
		$babyId = $form->createElement("hidden", "baby_id");
		$babyId->setLabel("Serial No")
				->setRequired(true);

		# STUDY HISTORY ID
		$studyHistory = $form->createElement("hidden", "study_history_id");
		$studyHistory->setRequired(true);

		# STUDY
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(true)
				->setMultiOptions($studyOptions);

		# CHECK IN OPTION (DEFAULT IS TO CHECK IN)
		$checkIn = $form->createElement("checkbox", "check_in");
		$checkIn->setLabel("Check In")
				->setChecked(true);

		# Comments
		$comments = $form->createElement("textArea", "comments");
		$comments->setLabel("Comments")
				 ->setAttrib("rows", 4)
				 ->setAttrib("cols", 42);

		// Add elements
		$form->addElements(array(
			$babyId,
			$studyHistory,
			$study,
			$checkIn,
			$comments
		));
		// Set decorators
		$form->setElementDecorators(array(
			'ViewHelper',
			'Label'
		));
		// Set universal filters
		$form->setElementFilters(array('StringTrim', 'StripTags'));

		# APPOINTMENT
		// Add date
		$yearRange = array("years" => 1);
		$date = new Zarrar_Form_SubForm_Date($yearRange);
		$date->setRequired(TRUE);
		$form->addSubForm($date, 'appt_date');
		// Add time
		$time = new Zarrar_Form_SubForm_Time();
		$time->setRequired(TRUE);
		$form->addSubForm($time, 'appt_time');

		# SUBMIT
		// Confirm
		$confirm = new Zend_Form_Element_Submit("confirm", "Confirm/Change");
		$confirm->setDecorators(array("ViewHelper"));
		$form->addElement($confirm);		
		// Cancel
		$cancel = new Zend_Form_Element_Submit("cancel", "Cancel");
		$cancel->setDecorators(array("ViewHelper"));
		$form->addElement($cancel);
		// Change
		$change = new Zend_Form_Element_Submit("save", "Change");
		$change->setDecorators(array("ViewHelper"));
		$form->addElement($change);

		return($form);
	}
	

	
	public function newScheduleAction()
	{		
		/** Process Form **/
		
		$result = $this->_formCreate->processForm(array("babystudy" => "BabyStudy"), "new", $dbFunction = array($this, 'insertSchedule'));
		
		
		/** Data submitted successfully **/
		
		if ($result == 0) {
			// Might want to get id?
			$this->view->success = "Success! A baby is now associated with a study.";
			// Redirect to a different page?
		} elseif ($result == -1) {
			$appointment = "";
		}
		
		
		/**
		 * New form or bad data submitted (non-zero exit)
		 * 	- setup form
		 **/
		
		// Get baby_id, if we have it
		$babyId = $this->_getParam("baby_id");
		if ($babyId) {
			$this->view->babyGiven = True;
			$this->view->baby_id = $babyId;
		}
		
		// Get study_id
		$studyId = $this->_getParam("study_id");
		$this->view->studyId = $studyId;
		// Get study list
		$study = new Study();
		$this->view->studyOptions = $study->getRecordOwners("short", false, array("" => "Choose"));
		
		// Get lab name
		$caller = new Callers();
		$this->view->lab = $caller->getLabName($_SESSION['caller_id']);
		
		// Set type
		$this->view->type = "new";
		
		// Split datetime of appointment into $date and $time
		$appointment = ($appointment) ? $appointment : $this->view->babystudy["appointment"];
		$spacePos = strpos($appointment, " ");
		$this->view->date = substr($appointment, 0, $spacePos);
		$this->view->time = substr($appointment, $spacePos);
	}
	
	public function insertSchedule($tables)
	{
		/**
		 * Result of schedule
		 * 	1) change status to 3 or scheduled
		 * 	2) insert into baby studies
		 *  3) insert into study histories
		 **/
	
		// If success, then take returned id and remove that baby + study id row from baby_studies
		foreach ($tables as $tableClass => $tableSet) {
			foreach ($tableSet as $table) {
				// Get data + specifics (outcome + allow_further)
				$data = $table->getFilteredData();
				$sibling = $data["sibling"];
				unset($data["sibling"]);
								
				// Get baby id
				$babyId = $data["baby_id"];
				// Get study id
				$studyId = $data["study_id"];
				// Get apppointment
				$appointment = $data["appointment"];
				// Get room
				$room = $data["room"];
				unset($data["room"]);
				// Get study length
				$studyLength = trim($data["study_length"]);
				unset($data["study_length"]);
				// Update status in baby table
				// Status id -> 3=scheduled
				$baby = new Baby();
				$statusId = "3";
				$where = $baby->getAdapter()->quoteInto("id = ?", $babyId);
				$baby->update(array("status_id" => $statusId), $where);
				
				// Insert new row into study histories
				$studyHistory = new StudyHistory();
				$studyHistoryId = $studyHistory->insert($data);

				// Associated baby with study
				$babyStudy = new BabyStudy();
				$data = array(
					"baby_id"			=> $babyId,
					"study_id"			=> $studyId,
					"study_history_id"	=> $studyHistoryId,
					"appointment"		=> $appointment,
					"sibling"			=> $sibling
				);
				$babyStudy->insert($data);
				
				/*
				// If everything ok, then add to google calendar
				if (!(empty($room))) {
					// Include classes
					Zend_Loader::loadClass('Zend_Gdata');
					Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
					Zend_Loader::loadClass('Zend_Gdata_Calendar');
					Zend_Loader::loadClass('Zend_Http_Client');
					
					// Get calendar id
					$calendarIds = array("nyu.baby.child@gmail.com", "03421hme216bki3ga87dltma78@group.calendar.google.com", "3a2tjkasv09gosnhk906d0ammo@group.calendar.google.com");
					$calId = $calendarIds[$room];

					// Parameters for ClientAuth authentication
					$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;
					$user = "nyu.baby.child@gmail.com";
					$pass = "testtest";
					
					// Create an authenticated HTTP client
					$client = Zend_Gdata_ClientLogin::getHttpClient($user, $pass, $service);
					
					// Setup class for event entry
					$gc = new Zend_Gdata_Calendar($client);
					$newEntry = $gc->newEventEntry();

					// Get study name for title
					$studyTbl = new Study();
					$where = $studyTbl->getAdapter()->quoteInto("id = ?", $studyId);
					$result = $studyTbl->fetchRow($where);
					// Set title
					$title = "{$result->study} - {$babyId}";
					$newEntry->title = $gc->newTitle(trim($title));
					
					// Set where happening
					$where = '6 Washington Place, NY 10003';
					$newEntry->where = array($gc->newWhere($where));

					// Set description
					$desc = $data["comments"];
					$newEntry->content = $gc->newContent($desc);
					$newEntry->content->type = 'text';

					// Set the date using RFC 3339 format.
					$startDate = str_replace(" ", "T", $appointment);
					$parse = date_parse($startDate);
					$startTimestamp = mktime($parse["hour"], $parse["minute"], $parse["second"], $parse["month"], $parse["day"], $parse["year"]);
					$endTimestamp = strtotime("+{$studyLength} minutes", $startTimestamp);
					$endDate = date("Y-m-d\TH:i:s", $endTimestamp);

					$when = $gc->newWhen();
					$tzOffset = "-04";
					$when->startTime = "{$startDate}.000{$tzOffset}:00";
					$when->endTime = "{$endDate}.000{$tzOffset}:00";
					$newEntry->when = array($when);
					
					// Insert event
					$createdEntry = $gc->insertEvent($newEntry, "http://www.google.com/calendar/feeds/{$calId}/private/full");
				}
				*/
			}
			
		}
		
		return $babyId;
	}
	
	public function newConfirmAction()
	{
		$type = "new";
		$this->view->type = $type;

		/** Process Form **/

		$result = $this->_formCreate->processForm(array("studyhistory" => "StudyHistory"), $submitCheck = null, $dbFunction = array($this, 'insertConfirm'));
		
		/** Data submitted successfully **/
		
		// Might want to get id?
		if ($result == 0) {
			$this->view->success = $_SESSION["appointment_result"];
		} else {
			$formData = $this->_formCreate->getData("studyhistory");
		}

		/**
		 * New form or bad data submitted (non-zero exit)
		 * 	- setup form
		 **/

		/** Get + Check Params **/

		// Get baby_id
		$babyId = $this->_getParam("baby_id");

		// Get study id
		$studyId = $this->_getParam("study_id");

		// Check that baby_id and study_id given, otherwise throw error
		if (empty($babyId) or empty($studyId))
			throw new Zend_Controller_Action_Exception("Baby id or Study id not given!");
		// Assign vars to view
		$this->view->baby_id = $babyId;
		$this->view->study_id = $studyId;
		
		// Get study list
		$study = new Study();
		$this->view->studyOptions = $study->getRecordOwners("short", false, array("" => "Choose"));
		
		/** Fetch Additional Default Fields **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build query to get study comments, appointment, study name, lab name
		$query = "SELECT bs.comments AS study_comments, bs.appointment, s.study, l.lab, bs.study_history_id FROM baby_studies AS bs LEFT JOIN studies AS s ON bs.study_id = s.id LEFT JOIN researchers AS r ON s.researcher_id = r.id LEFT JOIN labs as l ON r.lab_id = l.id WHERE bs.baby_id = ? AND bs.study_id = ?";
		
		// Execute query
		$stmt = $db->query($query, array($babyId, $studyId));
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// How many rows
		$rowCount = count($result);
		
		if ($rowCount < 1) {
			$this->_formCreate->addError("The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'");
			$this->_formCreate->setForm();
		} else {
			// Get only first row and save into view vars
			$row = $result[0];
			$this->view->assign($row);
			
			// Set appointment
			// Split datetime of appointment into $date and $time
			$appointment = ($formData["appointment"]) ? $formData["appointment"] : $this->view->appointment;
			$spacePos = strpos($appointment, " ");
			$this->view->date = substr($appointment, 0, $spacePos);
			$this->view->time = substr($appointment, $spacePos);
		}
	}
	
	public function insertConfirm($tables)
	{	
		/**
		 * Result of confirm (either SAVE AND/OR confirm or cancel)
		 * 	- confirm: 1) change status to CONFIRMED
		 * 	- cancel: 1) change status to CANCELED, 2) add to study histories, 3) remove from baby studies
		 **/
	
		// If success, then take returned id and remove that baby + study id row from baby_studies
		foreach ($tables as $tableClass => $tableSet) {
			foreach ($tableSet as $table) {
				// Get data + specifics (outcome + allow_further)
				$data = $table->getFilteredData();
				// Get baby id
				$babyId = $data["baby_id"];
				// Get study id
				$studyId = $data["study_id"];
				// Is confirmed or canceled?
				$isCanceled = ($data["study_outcome_id"] == 3) ? True : False ;
				// Just save?
				$isSave = ($data["save"]) ? True : False ;
				unset($data["save"]);
				// Get appointment
				$appointment = substr($data["appointment"], 0, $data["appointment"]-3);
				// Update baby table
				if (!($isSave)) {
					// Status id -> 4=confirmed, 6=canceled
					$baby = new Baby();
					$where = $baby->getAdapter()->quoteInto("id = ?", $babyId);
					$statusId = ($isCanceled) ? 6 : 4 ;
					$baby->update(array("status_id" => $statusId), $where);
				}
				
				// Setup babyStudy Stuff
				$babyStudy = new BabyStudy();
				$where = array(
					$babyStudy->getAdapter()->quoteInto("baby_id = ?", $babyId),
					$babyStudy->getAdapter()->quoteInto("study_id = ?", $this->_getParam("study_id"))
				);

				// If canceled
				if ($isCanceled) {
					// Update outcome into study history
					$table->update(array("date_cancel" => new Zend_Db_Expr('CURDATE()')));
					// Remove baby+study from baby_studies
					$babyStudy->delete($where);
					
					// Set message
					$_SESSION["appointment_result"] = "Baby '{$babyId}' has been cancelled for his/her study visit on {$appointment}!";
				}
				// Otherwise
				else {
					// Update study history
					$table->update();
					
					// Update baby_studies table
					$babyStudyData = array(
						"appointment"	=> $appointment,
						"study_id" 		=> $studyId,
						"comments"		=> $data["comments"]
					);
					$babyStudy->update($babyStudyData, $where);
					
					// Set message
					if ($isSave)
						$_SESSION["appointment_result"] = "Changes to Baby '{$babyId}' have been saved for his/her study visit on {$appointment}!";
					else
						$_SESSION["appointment_result"] = "Baby '{$babyId}' has been saved and confirmed for his/her study visit on {$appointment}!";
				}

			}
		}
		
		return $ids;
	}
	
	// Function to confirm or cancel
	// if confirm, then change status + add entry to study history
	// if cancel, then change status + take off baby_study + add entry to study history
	
	public function newOutcomeAction()
	{
		$type = "new";
		$this->view->type = $type;
	
		/** Process Form **/
		
		$result = $this->_formCreate->processForm("StudyHistory", $submitCheck = null, $dbFunction = array($this, 'insertOutcome'));
				
		/** Data submitted successfully **/
		
		if ($result == 0) {
			// Might want to get id?
			$this->view->success = $_SESSION["appointment_result"];
		}
		
		
		/**
		 * New form or bad data submitted (non-zero exit)
		 * 	- setup form
		 **/
		
		/** Get + Check Params **/
		
		// Get baby_id
		$babyId = $this->_getParam("baby_id");
		
		// Get study id
		$studyId = $this->_getParam("study_id");
		
		// Check that baby_id and study_id given, otherwise throw error
		if (empty($babyId) or empty($studyId))
			throw new Zend_Controller_Action_Exception("Baby id or Study id not given!");
		// Assign vars to view
		else {
			$this->view->baby_id = $babyId;
			$this->view->study_id = $studyId;
		}
		
		/** Fetch Additional Default Fields **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build query to get study comments, appointment, study name, lab name
		$query = "SELECT bs.comments AS study_comments, bs.appointment, s.study, l.lab, bs.study_history_id FROM baby_studies AS bs LEFT JOIN studies AS s ON bs.study_id = s.id LEFT JOIN researchers AS r ON s.researcher_id = r.id LEFT JOIN labs as l ON r.lab_id = l.id WHERE bs.baby_id = ? AND bs.study_id = ?";
		
		// Execute query
		$stmt = $db->query($query, array($babyId, $studyId));
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// How many rows
		$rowCount = count($result);
		
		if ($rowCount < 1) {
			$this->_formCreate->addError("The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'");
			$this->_formCreate->setForm();
		} else {
			// Get only first row and save into view vars
			$row = $result[0];
			$this->view->assign($row);
		}
		
		
		/** Get Select Form Options **/
		
		// Create outcome list
		$outcome = new StudyOutcome();
		$this->view->outcomeOptions = $outcome->getSelectOptions();
		
		// Create level of enthusiasm list
		$levelEnthusiasm = range(1, 5);
		$this->view->enthusiasmOptions = array_combine($levelEnthusiasm, $levelEnthusiasm);		
	}
	
	function insertOutcome($tables)
	{
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	
		/**
		 * Getting outcome of study (either in-study, cancelled, or no show)
		 * 	- DEFAULT: 1) delete entry in baby_studies, 2) add to study_history
		 * 	- IN_STUDY: 1) change status to RUN
		 * 	- CANCELLED: 1) change status to CANCELLED
		 * 	- NO SHOW:	1) change status to NO SHOW
		 * Getting not allow_further
		 * 	- archive record...?what to do for archive
		 **/
	
		// If success, then take returned id and remove that baby + study id row from baby_studies
		foreach ($tables as $tableClass => $tableSet) {
			$messages = array();
			foreach ($tableSet as $table) {
				// Get data + specifics (outcome + allow_further)
				$data = $table->getFilteredData();
				$babyId = $data["baby_id"];
				$studyId = $data["study_id"];
				$outcomeId = $data["study_outcome_id"];
				$allow = $data["allow_further"];
				$appointment = substr($data["appointment"], 0, $data["appointment"]-3);
				
				// Remove baby+study from baby_studies
				$babyStudy = new BabyStudy();
				$where = array(
					$babyStudy->getAdapter()->quoteInto("baby_id = ?", $babyId),
					$babyStudy->getAdapter()->quoteInto("study_id = ?", $studyId)
				);
				$babyStudy->delete($where);
				
				// Change status in baby table
				$baby = new Baby();
				# outcome: 1 = in study, 2 = no show, 3 = cancelled
				# status: 2 = archived, 6 = cancelled, 7 = no show, 5 = run
				switch ($outcomeId) {
					case '1':
						$statusId = 5;
						$table->update();
						$messages[] = "Baby '{$babyId}' has finished his/her study visit on {$appointment}!";
						break;
					case '2':
						$statusId = 7;
						$table->update();
						$messages[] = "Baby '{$babyId}' has been marked as a no show for his/her study visit on {$appointment}!";
						break;
					case '3':
						$statusId = 6;
						$table->update(array("date_cancel" => new Zend_Db_Expr('CURDATE()')));
						$messages[] = "Baby '{$babyId}' has been cancelled for his/her study visit on {$appointment}!";
						break;
				}
				// If not allow, then status set to 2 (or archived)
				if ($allow == "0") {
					$statusId = 2;
					$messages[] = "Baby '{$babyId}' has been archived.";
				}
				$where = $baby->getAdapter()->quoteInto("id = ?", $babyId);
				$data = array("status_id" => $statusId);
				// Update baby status
				$baby->update($data, $where);				
			}
			
			$_SESSION["appointment_result"] = implode("<br />", $messages);
		}
		
		return $ids;
	}
	
	public function searchAction()
	{
		// Process form
		$this->_form = $this->_formSearch;
		$result = $this->_form->processForm(null, $this->_confirmValidationRules, null, array($this, "modifySearch"));
		
		// Data submitted successfully
		if ($result == 0) {
			$return = $this->_prepareSearch($this->_form->getData("search"));
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			$this->_form->setForm();
		}
		// Otherwise -> Non-zero Exit!
		// Something went wrong (either new form or bad data submitted)
		$this->_searchSetup();
	}
	
	public function modifySearch(array $sectionData)
	{
		// Filter to convert array('year'=>..., 'month'=>..) into date format
		$arrayTOdate = new Zarrar_Filter_ArrayToDate();
		
		// Process date 1 field
		if (isset($sectionData["date1"]) and $sectionData['date1']) {
			$sectionData["date1"] = $arrayTOdate->filter($sectionData['date1']);
		}
		// Process date 2 field
		if (isset($sectionData["date2"]) and $sectionData['date2']) {
			$sectionData["date2"] = $arrayTOdate->filter($sectionData['date2']);
		}
		
		return $sectionData;
	}
	
	protected function _searchSetup()
	{
		/** Setup form select for research and study names **/

		// Get db adapter
		$db = Zend_Registry::get('db');

		// Get studies
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short");
		
		// Get researchers
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("short");
		
		// Set form selects
		$this->view->researcherOptions = $researcherOptions;
		$this->view->studyOptions = $studyOptions;
		// Create form select, per_page options
		$perPageOptions = array("1", "10", "25", "50", "100");
		$this->view->perPageOptions = array_combine($perPageOptions, $perPageOptions);
	}
	
	protected function _prepareSearch(array $formData)
	{
		// Not post, then forward to index
		if (!($this->getRequest()->isPost()))
			$this->forward('search', 'baby-study', null, null);
			
		/** Setup base query **/
		
		/** 
		 * Want to display these COMMON columns:
		 * 	#, #, serial no, study, researcher, lab, appointment (study date)
		 **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build select query (using fluent interface)
		$select = $db->select()
		
		// Want distinct rows
			->distinct()
		
		// Start from baby table + get baby information
			->from(array('b' => 'babies'),
	        	array('id' => 'id'))

		// Get study information (study date)
			->joinLeft(array("bs" => "baby_studies"),
			"b.id = bs.baby_id", array("study_date" => "bs.appointment"))
			
		// Get study name
			->joinLeft(array("s" => "studies"),
				"bs.study_id = s.id", array("study_id" => "s.id", "study" => "s.study"))
			
		// Get researcher name
			->joinLeft(array("r" => "researchers"),
				"s.researcher_id = r.id", array("researcher" => "r.researcher"))
				
		// Get lab name
			->joinLeft(array('l' => 'labs'),
				"r.lab_id = l.id", array("lab" => "l.lab"))
		
		// Group by study if there are multiple studies to be confirmed, etc
			->group(array("bs.study_id", "id"));
				
		// Narrow based on study and researcher desired
		if ($formData['study'] != "All")
			$select->where("bs.study_id = ?", $formData['study']);
		elseif ($formData['researcher'] != "All")
			$select->where("r.id = ?", $formData['researcher']);
		else
			$select->where("bs.study_id IS NOT NULL");
				
		// Narrow based on date
		if (empty($formData['date1']) === false and empty($formData['date2']) === false and empty($formData['alldates']))
			$select->where("bs.appointment BETWEEN {$db->quote($formData['date1'])} AND {$db->quote($formData['date2'])}");
		elseif (empty($formData['date1']) === false and empty($formData['alldates']))
			$select->where("bs.appointment LIKE ?", $formData['date1']);
		
		// Save query
		$query = $select->__toString();
				
		// Save records per page option
		$perPage = $formData['per_page'];
		
		// Get count of rows
		$select->reset(Zend_Db_Select::GROUP);
		$select->reset(Zend_Db_Select::COLUMNS);
		$select->from(null, "COUNT(DISTINCT b.id) AS count");
		$stmt = $select->query();
		$count = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		$count = $count[0];
				
		// Send error if count is below 1
		if ($count < 1)
			return False;
		
		// Have session with listType, rowCount, query, perPage
		$session = new Zend_Session_Namespace('query');
		$session->query = $query;
		$session->count = $count;
		$session->perPage = $perPage;
		
		// Send query, etc to list action
		$this->_forward("search-results", "baby-study", null);
	}
	
	public function searchResultsAction()
	{
		/* Get session variables */
		
		// Declare session namespace
		$session = new Zend_Session_Namespace('query');
		
		// Total number of rows from query (row count)
		$rowCount = $session->count;
		
		// Query
		$baseQuery = $session->query;
		
		// Rows to display per page (default is 25)
		$perPage = $session->perPage;
		
		
		/* Get params */
		
		// Current page number (default 1)
		$pageNum = ($this->_getParam("page")) ? $this->_getParam("page") : 1 ;
		
		// Order table by field (default baby's id or serial no)
		$sort = ($this->_getParam("sort")) ? $this->_getParam("sort") : "id" ;
		
		// Direction to order table (default ascending)
		$order = ($this->_getParam("order")) ? $this->_getParam("order") : "ASC" ;
		
		
		/* Build/Execute Final Query */
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Additional part of query only
		$addition = $db->select()
		// Limit query based on page number and number of rows to display per page
			->limitPage($pageNum, $perPage)
		// Order by $sort $order
			->order("{$sort} {$order}")
		// Return string to add on
			->__toString();
		
		// Combine base query and addition
		$query = $baseQuery . " " . $addition;
		
		// Fetch Rows		
		$stmt = $db->query($query);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		
		/* Setup pagination (HTML_PAGER) */
		
		// Class for pagination
		require_once 'Pager/Pager.php';
		// Options for pagination
		$params = array(
		    'mode'      	=> 'Sliding',
			'delta'			=> 2,
		    'append'    	=> false,
			'currentPage' 	=> $pageNum,
		    'path'      	=> $this->view->url(array("controller" => "baby-study", "action" => "search-results"), null, true),
		    'fileName'  	=> "page/%d/sort/{$sort}/order/{$order}",
		    'totalItems' 	=> $rowCount,
		    'perPage'   	=> $perPage
		);
		// Get pager
		$pager =& Pager::factory($params);
		// Get links (preformatted)
		$links = $pager->links;
		
		
		/* Setup column header links to sort column */
		
		// common fields to setup links for
		$urlFields = array("id", "study", "researcher", "lab", "study_date");
		
		// create the urls
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "baby-study", "action" => 'search-results', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		
		/* Setup display (view variables) */
		
		// Result (rows)
		$this->view->results = $result;
		
		// Page links
		$this->view->links = ($links) ? $links : 1 ;
		
		// Total number of rows
		$this->view->rowCount = $rowCount;
		
		// Column header links
		$this->view->assign('link', $link);		
		
		/* ALL DONE */
	}
}