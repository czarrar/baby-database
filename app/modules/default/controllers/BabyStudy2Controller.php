<?php


class BabyStudy2Controller extends Zend_Controller_Action 
{
	// Numbers (ids in status table) corresponding to some status
	const INACTIVE		= 1;
	const ARCHIVED 		= 2;
	const CONTACTING 	= 3;
	const SCHEDULED		= 4;
	const CONFIRMED 	= 5;
	const RUN			= 6;
	const CANCELED		= 7;
	const NO_SHOW		= 8;
	
	// Number (ids in study_outcome_id) corresponding to some study outcome
	const OUTCOME_RUN		= 1;
	const OUTCOME_NOSHOW	= 2;
	const OUTCOME_CANCELED	= 3;
	
	/**
	 * Form
	 *
	 * @var Zend_Form
	 **/
	protected $_form;
	
	/**
	 * Errors from form processing for user display
	 *
	 * @var array
	 **/
	protected $_errors = array();
	
	/**
	 * Baby ID
	 *
	 * @var integer
	 **/
	 protected $_babyId;

	/**
	 * Study ID
	 *
	 * @var integer
	 **/
	protected $_studyId;
	
	/**
	 * Is this baby associated with multiple studies
	 *
	 * @var boolean
	 **/
	protected $_multipleStudies;

	/**
	 * Connection to Google Calendar Account
	 *
	 * @var boolean, Zend_Gdata_Calendar
	 **/
	protected $_gCalService;
	
	/**
	 * Specific Calendar ID to add/change Study Event
	 *
	 * @var string
	 **/
	protected $_gCalID;
	
	// Only for outcome stuff
	protected $_gCalEventID;
	
	// Initalize all actions
	// similar to class constructor
	public function init()
	{
		# 1. Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		# 2. Get url parameters
		
		// Get baby id
		$this->_babyId = $this->_getParam("baby_id");
		if (empty($this->_babyId))
			throw new Zend_Controller_Action_Exception("Please provide a baby id!");
		
		// Get study id
		$this->_studyId = $this->_getParam("study_id");
		#if (empty($this->_studyId))
		#	throw new Zend_Controller_Action_Exception("Please provide the study id!");
	}
	
	
/****************************
 *	COMMON/SHARED FUNCTIONS	*
 ****************************/
 
    // Gets the date of birth as YEAR-MONTH-DAY for baby
    protected function _getBabyDob($babyId) {
        $db = Zend_Registry::get('db');
        $babyTbl = new Baby();
		$select = $babyTbl->select()->where("id = ?", $babyId);
		$babyRow = $babyTbl->fetchRow($select);
        return $babyRow->dob;
    }
    
 	/**
	 * Check if baby is scheduled for multiple studies
	 *
	 * @return boolean
	 **/
 	protected function _checkMultipleStudies()
 	{
 	    if (empty($this->_studyId))
			throw new Zend_Controller_Action_Exception("Please provide the study id!");
 	
		// Fetch baby study rows with info on scheduled baby in studies
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->from(array('bs' => 'baby_studies'),
				array('baby_id', 'study_id', 'appointment', 'appointment_end_time', 'comments'))
			->joinLeft(array('s' => 'studies'),
				'bs.study_id = s.id', array('study'))
			->where("baby_id = ?", $this->_babyId)
			->where("study_id != ?", $this->_studyId);
		$stmt = $select->query();
		$stmt->execute();
		$babyStudyRows = $stmt->fetchAll();
		
		// Save list into view
		if(count($babyStudyRows)>0) {
			$this->_multipleStudies = TRUE;
			$this->view->hasOtherStudies = 1;
			$this->view->otherStudies = $babyStudyRows;
		} else {
			$this->_multipleStudies = FALSE;
			$this->view->hasOtherStudies = 0;
		}
		
		return $this->_multipleStudies;
 	}
 	
	 /**
	  * Fetch google calendar class with active connection
	  *
	  * @return string
	  **/
 	protected function _fetchGCal()
 	{
 		# Load Classes
 		require_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_Calendar');
		Zend_Loader::loadClass('Zend_Http_Client');
		Zend_Loader::loadClass('Zend_Gdata_App_Exception');
		Zend_Loader::loadClass('Zend_Gdata_App_CaptchaRequiredException');
		
		$errorMessage = "";
		
		if (empty($this->_studyId))
			throw new Zend_Controller_Action_Exception("Please provide the study id!");
		
		try {
			# 1. Authenticate
		
			// Get authentication information from lab table (using study id)
			$db = Zend_Registry::get('db');
			$select = $db->select()
				->distinct()
				->from(array('l' => 'labs'),
					array("gcal_username", "gcal_password"))
				->joinLeft(array('r' => 'researchers'),
					'l.id = r.lab_id', array())
				->joinLeft(array('s' => 'studies'),
					"r.id = s.researcher_id", array("gcal_calendar_id"))
				->where('s.id = ?', $this->_studyId);
			$stmt = $db->query($select);
			$stmt->execute();
			$result = $stmt->fetchAll();	// Array format
		
			// Should have one row
			if(count($result) != 1)
				throw new Zend_Gdata_App_Exception("Error fetching Google Calender info in database! Did not find one table row for given study id (" . $this->_studyId . ")");
			else
				$info = $result[0];
				
			// Convert password
			$info["gcal_password"] = base64_decode($info["gcal_password"]);
			
			// Check if have info for gcal_username, gcal_password
			if(empty($info["gcal_username"]) or empty($info["gcal_password"]))
				throw new Zend_Gdata_App_Exception("Lab associated with study (id=" . $this->_studyId . ") does not have information on a Google Calendar username and/or password.");
				
			// Check if have info for gcal_calendar_id
			if(empty($info["gcal_calendar_id"]))
				throw new Zend_Gdata_App_Exception("Study (id=" . $this->_studyId . ") does not have information on a Google Calendar ID.");
			else
				$this->_gCalID = $info["gcal_calendar_id"];
			
			// Complete client authentication
			$client = Zend_Gdata_ClientLogin::getHttpClient($info["gcal_username"], $info["gcal_password"], Zend_Gdata_Calendar::AUTH_SERVICE_NAME);
			
			// Create an instance of the Calendar service
			$this->_gCalService = new Zend_Gdata_Calendar($client);
			
		} catch (Zend_Gdata_App_CaptchaRequiredException $cre) {
			# TODO: Have a pop-up with form that asks the captcha and after submission it will update things with javascript
			$errorMessage .= "CONTACT ADMIN, CAPTCHA REQUIRED! <br />\n";
			$errorMessage .= 'URL of CAPTCHA image: ' . $cre->getCaptchaUrl() . "<br />\n";
			$errorMessage .= 'Token ID: ' . $cre->getCaptchaToken() . "<br />\n";
			$this->_gCalService = FALSE;
		} catch (Exception $e) {
			$errorMessage .= "Error connecting to Google Calendar service <br />\n";
			$errorMessage .= $e->getMessage();
			$this->_gCalService = FALSE;
		}

		return $errorMessage;
 	}
 
  	 /**
	  * Fetch baby/family information for inserting into google calendar event
	  *
	  * @return string
	  **/
 	protected function _fetchGCalBabyInfo($data)
 	{
		/// Create database query
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->distinct()
			->from(array('b' => 'babies'),
				array("first_name", "dob", "sex"))
			->joinLeft(array('f' => 'families'),
				'f.id = b.family_id', array("mother_first_name"))
			->where('b.id = ?', $this->_babyId);
		$stmt = $db->query($select);
		/// Fetch database query in array format
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Check that you have only one row with baby info
		if (count($result)!=1)
			throw new Exception("Searching for baby info (ID {$this->_babyId}) in database but did not find the row.");
		else
			$babyInfo = $result[0];
			
		// Set sex (1=Male; 2=Female)
		$babyInfo["sex"] = ($babyInfo["sex"] == 1) ? "F" : "M";
		
		// Calculate age of baby at appointment
		/// Load calculator
		$calculator = new Zarrar_AgeCalculator();
		/// Set variables
		$calculator->setDob($babyInfo["dob"])
			->setDate($data['appt_date']);	
		/// Get age
		$babyInfo["age"] = $calculator->getAge("months");
		$babyInfo["age"] = str_replace("-", ",", $babyInfo["age"]);
		
		return $babyInfo;
 	}
 	
 	protected function _fetchGCalIDs()
 	{
 		/// Create database query
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->distinct()
			->from(array('b' => 'babies'), array())
			->joinLeft(array('bs' => 'baby_studies'),
				'bs.baby_id = b.id', array("gcal_event_id"))
			->joinLeft(array('s' => 'studies'),
				'bs.study_id = s.id', array("gcal_calendar_id", "study"))
			->where('b.id = ?', $this->_babyId);
		$stmt = $db->query($select);
		/// Fetch database query in array format
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		if(count($result)==0)
			throw new Exception("Could not find Google Calender event or calendar ids for baby (ID {$this->_babyId})");
		
		return $result;
 	}
	
	/**
	 * Processes form if something was posted (assumes data has been posted)
	 *
	 * @param string $actionType Options include 'schedule', 'confirm', 'outcome'
	 * @return boolean
	 * 	FALSE if nothing processed and TRUE if processed but data is bad
	 * 	Will forward to successAction if data is good (will not return)
	 **/
	protected function _processForm($actionType)
	{
#	    if (empty($this->_studyId))
#			throw new Zend_Controller_Action_Exception("Please provide the study id!");
	
		// Get baby id
		$babyId = $this->_babyId;
		// Get study id
		$studyId = $this->_studyId;
		
		if (empty($studyId)) {
		    if ($actionType != "schedule" || ($actionType == "schedule" && $this->getRequest()->isPost()))
		        $this->_errors["ERROR"] = array("info" => "No study given.");
		    return False;
	    }

		# Check if baby has multiple studies
		$this->_checkMultipleStudies();
	
		# Process Form
		if($this->getRequest()->isPost()) {	
			// Get data
			$formData = $this->getRequest()->getPost();

			// Check if valid
			if (!$this->_form->isValid($formData)) {
				// Save error messages
				$this->_errors = array_merge($this->_errors, $this->_form->getMessages());
			} else {
				try {
					// Begin transaction
					$db = Zend_Registry::get('db');
					$db->beginTransaction();
					
					// Fetch event and calendar id info for studies
					$gCalInfo = $this->_fetchGCalIDs();
                    
					// Schedule baby for new study!
					$message = call_user_func(array($this, "_process" . ucwords($actionType)), $this->_form->getValues());
                    
					// Set message
					$message = "BABY in study 'STUDY' at " . $message;
					
					// Fetch google calendar
					//// if any errors will continue to process
					//// and just tell user to manually enter gcal event
					$gCalErrors = $this->_fetchGCal();
					$message = "\n<br /><br />\n{$message}";
					
					if(empty($gCalErrors)) {
						$tmpMessage = call_user_func(array($this, "_gCal" . ucwords($actionType)), $this->_form->getValues(), $gCalInfo);
						$message = "{$tmpMessage}{$message}";
					} else {
						$message = "{$gCalErrors}{$message}";
					}
					
					$message = "\n<br />\n${message}";
						
					// Commit db changes
					$db->commit();
					
					// Success, tell user
					$this->_forward("success", "baby-study", null, array("message" => $message));
				} catch(Exception $e) {
					// Crap, scheduling baby did not work!
					$db->rollback();
					$this->_errors["ERROR"] = array("info" => $e->getMessage());
				}

			}
			
			return True;
		} else {
			return False;
		}
	}
	
	/**
	 * Instantiates form element with some basic stuff
	 *
	 * @return obj Zend_Form
	 **/
	protected function _getForm()
	{
		if (empty($this->_form)) {
			// Need create new form
			$form = new Zend_Form();
			$form->setAction("")
				->setMethod("post");
			$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
			$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
			
			// Add as class member
			$this->_form = $form;
		}
		
		return $this->_form;
	}
	
	/**
	 * Prepares common form elements
	 * 	for scheduling, confirming, and outcome pages
	 * 
	 * Common elements are:
	 * 	baby id, study id, study date/time, and comments fields
	 * 
	 * Default decorators: ViewHelper and Label
	 * Default filters: StringTrim, StripTags
	 * 
	 * NOTE: Date/Time of Study field is not subject to the default decorators or filters
	 *
	 * @param array $elements Optional
	 * 	Additional elements to add before setting default decorators + filters
	 * @param array $elementDecorators Optional
	 * 	Additional decorators to set for all elements (added to defaults)
	 * @param array $elementFilters Optional
	 * 	Additional filters to set for all elements (added to defaults)
	 * @return Zend_Form
	 **/
	protected function _prepareForm(array $elements = array(), array $elementDecorators = array(), array $elementFilters = array())
	{
		$form = $this->_getForm();
			
		/* FIELDS: baby id, study id, study date/time, comments */
		
		# 1. BABY ID
		$baby = $form->createElement("hidden", "baby_id");
		$baby->setLabel("Serial No")
				->setRequired(true);
        // also give the dob
        $dob = $form->createElement("hidden", "baby_dob");
		$dob->setRequired(true);
		
		# 2. STUDY ID
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				#->setAttrib("disabled", "disabled")
				->setMultiOptions($studyOptions);
				
		# 3. COMMENTS
		$comments = $form->createElement("textarea", "comments");
		$comments->setLabel("Comments")
				 ->setAttrib("rows", 4)
				 ->setAttrib("cols", 42);
				 
		# 4. Has other studies
		$otherStudies = $form->createElement("hidden", "other_studies");
				
		# ADD ELEMENTS
		$addElements = array_merge(array($baby, $dob, $study, $researcher, $perPage, $comments, $otherStudies), $elements);
		$form->addElements($addElements);

		# SET DECORATORS
		$setDecorators = array_merge(array('ViewHelper', 'Label'), $elementDecorators);
		$form->setElementDecorators($setDecorators);

		# SET FILTERS
		$setFilters = array_merge(array('StringTrim', 'StripTags'), $elementFilters);
		$form->setElementFilters($setFilters);
				
		# 5. APPOINTMENT
		// Add date
		$yearRange = array("years" => 1);
		$date = new Zarrar_Form_SubForm_Date($yearRange);
		$date->setRequired(TRUE);
		$form->addSubForm($date, 'appt_date');
		// Add time
		$time = new Zarrar_Form_SubForm_Time(array("addBy" => 15, "limitTime" => array(8,21)));
		$time->setRequired(TRUE);
		$form->addSubForm($time, 'appt_time');
		// Add end time
		$time = new Zarrar_Form_SubForm_Time(array("addBy" => 15, "limitTime" => array(8,21)));
		$time->setRequired(TRUE);
		$form->addSubForm($time, 'appointment_end_time');
		
		$this->_form = $form;
	
		return $this->_form;
	}
	
	

/********************************
 *	SUCCESS ACTION FUNCTIONS	*
 ********************************/	

	/**
	 * ACTION: successful db action (e.g. insert/update)
	 **/
	public function successAction()
	{
	    if (empty($this->_studyId))
			throw new Zend_Controller_Action_Exception("Please provide the study id!");
	
		// Get message
		$message = $this->_getParam("message");
		// Get baby id
		$babyId = $this->_babyId;
		// Get study id
		$studyId = $this->_studyId;

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
		
		// Get study id
		$studyId = $this->_getParam("study_id");
		
		// Get start and end age of study
		$startDate = $this->_getParam("start_date");
		$endDate = $this->_getParam("end_date");
		if (!empty($startDate) or !empty($endDate)) {
			// Get dob
			$babyTbl = new Baby();
			$where = $babyTbl->select()->where("id = ?", $babyId);
			$babyRow = $babyTbl->fetchRow($where);
			$dob = $babyRow->dob;
			
			// Use age calculator
			$ageCalculator = new Zarrar_AgeCalculator();
			
			// Set dob
			$ageCalculator->setDob($dob);
						
			// Get age at start date
			$ageCalculator->setDate($startDate);
			$startAge = $ageCalculator->calculateAge();
			
			// Get age at end date
			$ageCalculator->setDate($endDate);
			$endAge = $ageCalculator->calculateAge();
			
			// Set info into view
			$this->view->startDate = $startDate;
			$this->view->startAge = $startAge;
			$this->view->endDate = $endDate;
			$this->view->endAge = $endAge;
		}

		// Setup form
		$this->_scheduleForm();
		
		// Process (if submitted)
		$didProcess = $this->_processForm("schedule");
		
		// Set default values (if new form)
		if (!$didProcess) {
			$this->_form->populate(array(
				"baby_id"		=> $babyId,
				"baby_dob"      => $this->_getBabyDob($babyId),
				"caller_id"		=> $_SESSION["caller_id"],
				"study_id"		=> $studyId,
				"appt_date"		=> array("my_date" => date('Y-m-d H:i:s')),
				"other_studies"	=> $this->view->hasOtherStudies
			));
			// If there are other studies then set the time to the other study time
			if($this->view->hasOtherStudies == 1) {$this->_form->populate(array(	
					"appt_date"	=> array("my_date" => $this->view->otherStudies[0]["appointment"]),
					"appt_time"	=> array("my_time" => $this->view->otherStudies[0]["appointment"]),
					"appointment_end_time" => array("my_time" => $this->view->otherStudies[0]["appointment_end_time"]),
					"comments"	=> $this->view->otherStudies[0]["comments"]
				));
			}
		}
		
		// Set form and errors to view
		$this->view->dob = $dob;
		$this->view->form = $this->_form;
		$this->view->errors = $this->_errors;
	}

	/**
	 * Creates form for scheduling babies
	 *
	 * @return Zend_Form
	 **/
	protected function _scheduleForm()
	{
		/* FIELDS: baby id, study, appointment (date/time), sibling, comments
		 * FIELDS TO SUBMIT: baby_id, study_id, appointment, comments
		 */
		
		// Get form
		$form = $this->_getForm();
		
		# SIBLING
		// Set options
		$siblingOptions = array(
			""	=> "No",
			1	=> "Yes"
		);
		// Create select field
		$sibling = $form->createElement("select", "sibling");
		$sibling->setLabel("Sibling Coming")
				->setMultiOptions($siblingOptions);
				
		# CALLER ID
		$caller = $form->createElement("hidden", "caller_id");
		$caller->setRequired(true);

		# SUBMIT
		$submit = new Zend_Form_Element_Submit("submit", "Schedule Appointment");
		
		# ADD TO COMMON ELEMENTS + GET FORM
		return $this->_prepareForm(array($sibling, $caller, $submit));
	}

	/**
	 * SCHEDULES BABY
	 * Inserts record for baby participating in a study
	 *
	 * @param array $data Form data to insert
	 * @return string ???
	 **/
	protected function _processSchedule(array $data)
	{
		/**
		 * Result of scheduling
		 * 	1) a. change scheduling status to 3 or scheduled
		 * 	   b. update checkout stuff
		 *  2) insert into baby studies
		 * 	@todo: email researcher about study
		 * 	@todo: email parent about study
		 * 	@todo: google calendar functional
		 **/

		$db = Zend_Registry::get('db');
        
		// Throw error if baby is already participating in this study
		$babyStudyTbl = new BabyStudy();
		$select = $babyStudyTbl->select()->where("baby_id = ?", $data["baby_id"])->where("study_id = ?", $data["study_id"]);
		$thisStudyRow = $babyStudyTbl->fetchAll($select);
		if(count($thisStudyRow)>0)
			throw new Zend_Controller_Action_Exception("This baby is already scheduled for this study!");
        
		// Set the appointment time
		$data['appointment'] = $data['appt_date'] . " " . $data['appt_time'];
		unset($data["appt_date"]);
		unset($data["appt_time"]);
		
		# 1. UPDATE CHECKOUT HISTORY (if checking out)
		$babyTbl = new Baby();
		$babySelect = $babyTbl->select()->where("id = ?", $data["baby_id"]);
		$babyRow = $babyTbl->fetchRow($babySelect);
		if ($babyRow->checked_out == 0) {
			$chTbl = new CheckoutHistory();
			$chData = array(
				"baby_id"		=> $data["baby_id"],
				"study_id"		=> $data["study_id"],
				"checked_out"	=> 1
			);
			$chTbl->insert($chData);
		}
		
		# 2. UPDATE CHECKOUT + STATUS UPDATE
		$babyTbl = new Baby();
		$where = $db->quoteInto("id = ?", $data["baby_id"]);
		$babyData = array(
			"checkout_date"	=> new Zend_Db_Expr('CURDATE()'),
			"checkout_caller_id" => $data["caller_id"]
		);
		// Already scheduled for other studies?
		if($data["other_studies"] != 1) {
			$babyData["status_id"] = self::SCHEDULED;
			$babyData["checked_out"] = 1;
		}
		$babyTbl->update($babyData, $where);
		
		# 3. ASSOCIATE BABY W/ STUDY
		$babyStudyTbl = new BabyStudy();
		$babyStudyTbl->filterInsert($data);
		
		# 4. Update any other studies
		if($data["other_studies"] == 1) {
			// Fetch rows
			$babyStudyTbl = new BabyStudy();
			$select = $babyStudyTbl->select()
				->where("baby_id = ?", $data["baby_id"])
				->where("study_id != ?", $data["study_id"]);
			$babyStudyRows = $babyStudyTbl->fetchAll($select);
			// Loop through and save info
			foreach($babyStudyRows as $bsRow) {
				$bsRow->appointment = $data["appointment"];
				$bsRow->appointment_end_time = $data["appointment_end_time"];
				$bsRow->comments = $data["comments"];
				$bsRow->save();
			}
		}
		
		return $data["appointment"] . " is SCHEDULED";
	}

	/**
	 * Add google calendar event for this baby in this study
	 *
	 * @param array $data User form data
	 * @return string Message for success page
	 **/
	protected function _gCalSchedule($data, $gCalInfo)
	{
	    if (empty($this->_studyId))
			throw new Zend_Controller_Action_Exception("Please provide the study id!");
	
		# 1. INSANITY CHECKS
	
		// Check connection just in case
		if(empty($this->_gCalService))
			throw new Zend_Gdata_App_Exception("No google calendar connection!");
		
		// Check calendar id exists
		if(empty($this->_gCalID))
			throw new Zend_Gdata_App_Exception("No google calendar id!");
		
		// Store any messages to pass
		$message = "";
		
		try {
			# 2. Setup Event Details
		
			// Create URI for future use
			$uri = "http://www.google.com/calendar/feeds/{$this->_gCalID}/private/full";
			
			// Setup baby/family info for event
			$babyInfo = $this->_fetchGCalBabyInfo($data);
			
			// Setup start and end time for event
			$when = $this->_gCalService->newWhen();
			$when->startTime = "{$data['appt_date']}T{$data['appt_time']}:00.000";
			$when->endTime = "{$data['appt_date']}T{$data['appointment_end_time']}:00.000";
			
			// Add sibling info to comments
			if($data["sibling"]==1)
				$data["comments"] = "Sibling coming. {$data['comments']}";
			
			# 3. Create Event
			
			// Create new event using the calendar service's magic factory method
			$event = $this->_gCalService->newEventEntry();
		
			// Populate the event with the desired information
			// Note that each attribute is created as an instance of a matching class
			$event->title = $this->_gCalService->newTitle("{$babyInfo['mother_first_name']} / {$babyInfo['first_name']} ({$babyInfo['sex']}) {$this->_babyId} {$babyInfo['age']}");
			$event->when = array($when);
			$event->content = $this->_gCalService->newContent($data["comments"]);
			
			// Upload the event to the calendar server
			// A copy of the event as it is recorded on the server is returned
			$newEvent = $this->_gCalService->insertEvent($event, $uri);
			
			# 4. Save New Event ID into Database
			
			$eventId = $newEvent->getId()->text;
			$pos = strrpos($eventId, "/") + 1;
			$eventId = substr($eventId, $pos);
			
			// Save event ID along with database data (so need to do this before the commit)
			$bsTbl = new BabyStudy();
			$select = $bsTbl->select()
						->where("baby_id = ?", $this->_babyId)
						->where("study_id = ?", $this->_studyId);
			$bsRow = $bsTbl->fetchRow($select);
			$bsRow->gcal_event_id = $eventId;
			$bsRow->save();
			
			// Update message
			$message = "Succesfully added event to Google Calendar";
			
			# 5. Update any other studies
			if ($data["other_studies"] == 1) {
				$message .= "<br />\n";
				$message .= $this->_gCalConfirm($data, $gCalInfo);
			}
		} catch (Exception $e) {
			$message = "Error adding event to Google Calendar <br />\n";
			$message .= $e->getMessage();
		}
			
		
		return $message;
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
		$this->_confirmForm($babyId);
		
		// Process (if submitted)
		$this->_processForm("confirm");
		
		// Set default values (if new form)
		if (!$didProcess) {
			# 1. Fetch appt time, comments, and study history id
			$bsTbl = new BabyStudy();
			$bsInfo = $bsTbl->getBasics($babyId, $studyId);

			# 2a. Did not find anything
			if (empty($bsInfo)) {
				$this->_errors["ERRORS"] = array(
					"info" => "The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'"
				);
			}
			# 2b. Populate with defaults
			else {
				$this->_form->populate(array(
					"baby_id"	=> $babyId,
					"baby_dob"      => $this->_getBabyDob($babyId),
					"study_id"	=> $studyId,
					"caller_id"	=> $_SESSION["caller_id"],
					"appt_date"	=> array("my_date" => $bsInfo["appointment"]),
					"appt_time" => array("my_time" => $bsInfo["appointment"]),
					"appointment_end_time" => array("my_time" => $bsInfo["appointment_end_time"]),
					"comments" 	=> $bsInfo["comments"],
					"other_studies"	=> $this->view->hasOtherStudies
				));
			}
		}
		
		// Set form and errors to view
		$this->view->form = $this->_form;
		$this->view->errors = $this->_errors;
	}

	/**
	 * Creates form for scheduling babies
	 *
	 * @return Zend_Form
	 **/
	protected function _confirmForm($babyId)
	{
		# FIELDS: baby id, study id, appointment (date/time), comments
		
		// Get/add baby table
		$babies = new Baby();
		$where = $babies->getAdapter()->quoteInto("id = ?", $babyId);
		$baby = $babies->fetchRow($where);
		if ($baby === null)
			throw new Zend_Controller_Action_Exception("Could not find baby with the id: $babyId");
		
		// Get form
		$form = $this->_getForm();
		
		# CHECK IN OPTION (IF CANCELING)
		$checkIn = $form->createElement("checkbox", "check_in");
		$checkIn->setLabel("Record Status")
			->setChecked(true);
		
		# 4. CALLER ID
		$caller = $form->createElement("hidden", "caller_id");
		$caller->setRequired(true);
		
		# Status (Scheduled or Confirmed?)
		$statusId = $form->createElement("select", "status_id");
		$statusId->setLabel("Scheduling Status")
			->setMultiOptions(array(
					""				=> "ERROR",
					self::SCHEDULED	=> "SCHEDULED",
					self::CONFIRMED	=> "CONFIRMED"
				))
			->setValue($baby->status_id);

		# SUBMIT
		// Confirm
		$confirm = new Zend_Form_Element_Submit("confirm", "Confirm/Save");
		$confirm->setDecorators(array("ViewHelper"));
		// Cancel
		$cancel = new Zend_Form_Element_Submit("cancel", "Cancel Appointment");
		$cancel->setDecorators(array("ViewHelper"));
		// Change
		$change = new Zend_Form_Element_Submit("save", "Save");
		$change->setDecorators(array("ViewHelper"));
			
		# ADD TO COMMON ELEMENTS + GET FORM
		return $this->_prepareForm(array($checkIn, $caller, $confirm, $cancel, $change, $statusId));
	}

	/**
	 * CONFIRM/CHANGE BABY
	 * Updates record for baby participating in a study
	 * Confirms baby appointment
	 *
	 * @param array $data Form data to insert
	 * @return string ???
	 **/
	protected function _processConfirm(array $data)
	{
	/**
	 * Result of confirm:
	 * 	- cancel:
	 * 		1) change status to CANCELED
	 * 		2) new entry to study history
	 * 		3) remove from baby studies
	 * 		4) check in if set
	 * 	- change:
	 * 		1) change entries in baby_studies
	 * 	- change+confirm:
	 * 		1) change entries in baby_studies
	 * 		2) change status to CONFIRMED
	 **/

		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Add study id
		$studyId = $this->_getParam("study_id");
		if($data["other_studies"] != 1)
			$data["study_id"] = $studyId;
		else
			unset($data["study_id"]);
		
		// Get if there are other studies
		$babyStudyTbl = new BabyStudy();
		$select = $babyStudyTbl->select()->where("baby_id = ?", $data["baby_id"]);
		$babyStudyRows = $babyStudyTbl->fetchAll($select);

		# PREPROCESS
		// Set the appointment time
		$data['appointment'] = $data['appt_date'] . " " . $data['appt_time'];
		unset($data["appt_date"]);
		unset($data["appt_time"]);
		
		# WHAT TO DO
		// Cancel
		$isCanceled = ($data["cancel"]) ? True : False ;
		// Save
		$isSave = ($data["save"]) ? True : False ;
		## OLD
		#$isSave = ($data["save"] or $data["confirm"]) ? True : False ;
		#// Confirm + Save
		#$isConfirm = ($data["confirm"]) ? True : False ;
		## OLD
		
		# SETUP COMMON TABLES
		// Baby Studies
		$bsTbl = new BabyStudy();
		$bsWhere = $db->quoteInto("baby_id = ?", $data["baby_id"]);
		// Baby
		$babyTbl = new Baby();
		$babyWhere = $db->quoteInto("id = ?", $data["baby_id"]);

		# A. CANCEL
		if ($isCanceled) {
			// 1. Change status
			$babyData = array(
				"status_id"	=> self::CANCELED
			);
			$babyTbl->update($babyData, $babyWhere);

			// 2. Add to study history
			$shTbl = new StudyHistory();
			$shData = array(
				"date_cancel" 		=> new Zend_Db_Expr("CURDATE()"),
				"study_outcome_id"	=> self::OUTCOME_CANCELED
			);
			foreach ($babyStudyRows as $babyStudyRow) {
				$shTbl->filterInsert(array_merge($data, $shData, array("study_id" => $babyStudyRow->study_id)));
			}

			// 3. Remove from baby studies
			$bsTbl->delete($bsWhere);

			// 4. Check in
			// a. update baby table
			$babyData = array(
				'checked_out'	=> 0
			);
			$babyTbl->update($babyData, $babyWhere);
			// b. add to checkout history
			$chTbl = new CheckoutHistory();
			$chData = array(
				"baby_id"		=> $data["baby_id"],
				"study_id"		=> $studyId,
				"checked_out"	=> 0
			);
			$chTbl->insert($chData);

			$display = $data["appointment"] . " is CANCELED";
			if($data["other_studies"] == 1)
				$display = $display . " <br />(NOTE: other studies were also canceled)";
				
			return $display;
		}

		# B. SAVE
		$status_id = $data["status_id"];
		if ($isSave) {
			// 1. Update scheduling status
			$babyData = array(
				"status_id"	=> $status_id
			);
			$babyTbl->update($babyData, $babyWhere);
			unset($data["status_id"]);
					
			// 2. Update entry in baby studies
			$bsTbl->filterUpdate($data, $bsWhere);
		}

		# OLD
		## C. CONFIRM
		#if ($isConfirm) {
		#	// 1. Update scheduling status
		#	$babyData = array(
		#		"status_id"	=> self::CONFIRMED
		#	);
		#	$babyTbl->update($babyData, $babyWhere);
        #
		#	return $data["appointment"] . " is SAVED and CONFIRMED";
		#}
		# OLD

		$schedulingStatus = array();
		$schedulingStatus[self::SCHEDULED] = "SCHEDULED";
		$schedulingStatus[self::CONFIRMED] = "CONFIRMED";
		
		$display = $data["appointment"] . " is SAVED with scheduling status of " . $schedulingStatus[$status_id];
		if($data["other_studies"] == 1)
			$display = $display . " <br />(NOTE: other studies were also updated)";
		
		return $display;
	}
	
	/**
	 * Change google calendar event for this baby in this study
	 *
	 * @param array $data User form data
	 * @return string Message for success page
	 **/
	protected function _gCalConfirm($data, $gCalInfo)
	{
		# 1. INSANITY CHECKS
	
		// Check connection just in case
		if(empty($this->_gCalService))
			throw new Zend_Gdata_App_Exception("No google calendar connection!");
		
		// Store any messages to pass
		$message = "";
		
		try {
			# 2. Setup Event Details
			
			// Cancel?
			$isCanceled = ($data["cancel"]) ? True : False ;
				
			// Get baby/family info for event
			$babyInfo = $this->_fetchGCalBabyInfo($data);
			
			// Create start and end time for event
			$when = $this->_gCalService->newWhen();
			$when->startTime = "{$data['appt_date']}T{$data['appt_time']}:00.000";
			$when->endTime = "{$data['appt_date']}T{$data['appointment_end_time']}:00.000";
			
			# 3. Edit Each Event
			
			// Loop through each Google Calendar Info
			foreach ($gCalInfo as $g) {
				// Check info
				if(empty($g["gcal_calendar_id"])) {
					$message .= "No Google Calendar ID for Study ({$g['study']})";
					$message .= "<br />\n";
					continue;
				}
				if(empty($g["gcal_event_id"])) {
					$message .= "No GCal Event ID for Baby particiating in Study ({$g['study']})";
					$message .= "<br />\n";
					continue;
				}
				
				// Create gcal query to find event
				$query = $this->_gCalService->newEventQuery();
				$query->setUser($g["gcal_calendar_id"]);
				$query->setVisibility('private');
				$query->setProjection('full');
				$query->setEvent($g["gcal_event_id"]);
				
				// Fetch event
				$event = $this->_gCalService->getCalendarEventEntry($query);
				
				if($isCanceled) {
					// Delete event if canceled appointment
					$event->delete();
					$message .= "Succesfully removed Google Calendar event for study ({$g['study']})";
					$message .= "<br />\n";
				} else {
					// Populate the event with the desired information
					$event->title = $this->_gCalService->newTitle("{$babyInfo['mother_first_name']} / {$babyInfo['first_name']} ({$babyInfo['sex']}) {$this->_babyId} {$babyInfo['age']}");
					$event->when = array($when);
					$event->content = $this->_gCalService->newContent($data["comments"]);
				
					// Save Event
					$event->save();
					// Message
					$message .= "Succesfully updated Google Calendar event for study ({$g['study']})";
					$message .= "<br />\n";
				}
			}
		} catch (Exception $e) {
			$message = "{$message} Error updating event to Google Calendar <br />\n";
			$message .= $e->getMessage();
		}
		
		return $message;
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
		
		// Process (if submitted)
		$didProcess = $this->_processForm("outcome");
		
		// Set default values (if new form)
		if (!$didProcess) {
			# 1. Fetch appt time and comments
			$bsTbl = new BabyStudy();
			$bsInfo = $bsTbl->getBasics($babyId, $studyId);

			# 2a. Did not find anything
			if (empty($bsInfo)) {
				$this->view->errors["ERROR"] = array(
					"info" => "The baby with serial no '{$babyId}' does not seem to be associated with the following study id '{$studyId}'"
				);
			}
			# 2b. Populate with defaults
			else {
				$form->populate(array(
					"baby_id"		=> $babyId,
					"baby_dob"      => $this->_getBabyDob(),
					"study_id"		=> $studyId,
					"caller_id"		=> $_SESSION["caller_id"],
					"appt_date" 	=> array("my_date" => $bsInfo["appointment"]),
					"appt_time" 	=> array("my_time" => $bsInfo["appointment"]),
					"appointment_end_time" => array("my_time" => $bsInfo["appointment_end_time"]),
					"comments" 		=> $bsInfo["comments"],
					"other_studies"	=> $this->view->hasOtherStudies
				));
			}
		}
		
		// Set form and errors to view
		$this->view->form = $this->_form;
		$this->view->errors = $this->_errors;
	}

	/**
	 * Creates form for giving outcome of baby in study
	 *
	 * @return Zend_Form
	 **/
	protected function _outcomeForm()
	{
		# FIELDS:
		#	baby id, study, appointment (date/time), comments
		#	check in, outcome, level of entusiasm, caller id
		
		// Get form
		$form = $this->_getForm();
		
		# 1. CHECK IN OPTION
		$checkIn = $form->createElement("checkbox", "check_in");
		$checkIn->setLabel("Record Status")
				->setChecked(true);
		
		# 2. OUTCOME
		// Create option list
		$soTbl = new StudyOutcome();
		$outcomeOptions = $soTbl->getSelectOptions();
		// Create select field
		$outcome = $form->createElement("select", "study_outcome_id");
		$outcome->setLabel("Outcome")
				->setRequired(true)
				->setMultiOptions($outcomeOptions);
		
		# 3. LEVEL OF ENTHUSIASM
		// Create option list
		$enthusiasmOptions = array(
			""	=> "Choose",
			1	=> 1,
			2	=> 2,
			3	=> 3,
			4	=> 4,
			5	=> 5
		);
		// Create select field
		$enthusiasm = $form->createElement("select", "level_enthusiasm");
		$enthusiasm->setLabel("Level of Entusiasm")
				   ->setMultiOptions($enthusiasmOptions);
				
		# 4. CALLER ID
		$caller = $form->createElement("hidden", "caller_id");
		$caller->setRequired(true);
		
		# SUBMIT
		// Confirm
		$allow = new Zend_Form_Element_Submit("allow", "Allow Further Study");
		$allow->setDecorators(array("ViewHelper"));
		// Cancel
		$noAllow = new Zend_Form_Element_Submit("no_allow", "Do Not Allow Further Study");
		$noAllow->setDecorators(array("ViewHelper"));
			
		# ADD TO COMMON ELEMENTS + GET FORM
		$form = $this->_prepareForm(array($checkIn, $outcome, $enthusiasm, $caller, $allow, $noAllow));
		
		# Set readonly for appt_time and appt_date
		#$form->appt_time->disabled();
		#$form->appt_date->disabled();
		# Add hidden field for submission
		
		
		return($form);
	}

	/**
	 * OUTCOME BABY
	 * ONLY DO FOR ONE BABYSTUDY AT A TIME (unlike confirm appt)
	 *
	 * @todo: forward to archive form if do not allow
	 * @todo: set messages for each outcome option
	 * @param array $data Form data to insert
	 * @return string ???
	 **/
	protected function _processOutcome(array $data)
	{
		/**
		 * Result of outcome:
		 * 	1) delete from baby studies
		 * 	2) change status:	
		 * 		a) In Study: to RUN
		 * 		b) Canceled: to CANCELED
		 * 		c) No Show: to NO SHOW
		 * 	3) add to study histories
		 * 	4) IF NO ALLOW -> archive
		 * 	5) check-in, if desired
		 **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Add study id
		$studyId = $this->_getParam("study_id");
		$data["study_id"] = $studyId;

		# PREPROCESS
		// Set the appointment time
		$data['appointment'] = $data['appt_date'] . " " . $data['appt_time'];
		unset($data["appt_date"]);
		unset($data["appt_time"]);
		
		// Set allow study
		if (!empty($data["allow"])) {
			$data["allow_further"] = 1;
			unset($data["allow"]);
		}
		
		// If enthusiasm empty, take out
		if (empty($data["level_enthusiasm"]))
			unset($data["level_enthusiasm"]);
		
		// Start message
		$message = $data['appointment'];
		
		# 1. DELETE BABY STUDIES ENTRY (only for current study)
		$bsTbl = new BabyStudy();
		$select = $bsTbl->select()
					->where("baby_id = ?", $data["baby_id"])
					->where("study_id = ?", $data["study_id"]);
		$bsRow = $bsTbl->fetchRow($select);
		// Save Event ID first
		$this->_gCalEventID = $bsRow->gcal_event_id;
		// Then delete
		$bsRow->delete();
		
		# 2. CHANGE SCHEDULING STATUS (AND MORE IF CANCELED)
		# only change if there are not other studies
		if($data["other_studies"]!=1) {
			$babyTbl = new Baby();
			$babyWhere = $db->quoteInto("id = ?", $data["baby_id"]);
			$babyData = array();
			switch ($data["study_outcome_id"]) {
				case self::OUTCOME_RUN:
					$babyData["status_id"] = self::RUN;
					// Check that have enthusiasm
					if (!isset($data["level_enthusiasm"]))
						throw new Zend_Controller_Action_Exception("Level of enthusiasm must be given if baby has been run!");
					break;
				case self::OUTCOME_CANCELED:
					$data["date_cancel"] = new Zend_Db_Expr("CURDATE()");
					$babyData["status_id"] = self::CANCELED;
					break;
				case self::OUTCOME_NOSHOW:
					$babyData["status_id"] = self::NO_SHOW;
					break;
				default:
					throw new Zend_Controller_Action_Exception("Study outcome id is invalid!");
					break;
			}
			$babyTbl->update($babyData, $babyWhere);	
		}
		
		# 3. ADD TO STUDY HISTORIES
		$shTbl = new StudyHistory();
		$shTbl->filterInsert($data);
		
		# 4. ARCHIVING?
		if ($data["no_allow"]) {
			if($data["other_studies"]==1)
				throw new Exception("Cannot archive babies scheduled for multiple studies. You can only archive a baby when you have completed the outcome of all studies except one.");
			$this->_forward("baby", "archive", null, array("id" => $data['baby_id']));		
		}
		
		# 5. CHECK IN
		if ($data["other_studies"]!=1 and $data["check_in"]) {
			// a. update baby table
			$babyData = array(
				"checked_out"	=> 0,
			);
			$babyTbl->update($babyData, $babyWhere);
			// b. insert in checkout history
			$chTbl = new CheckoutHistory();
			$chData = array(
				"baby_id"		=> $data["baby_id"],
				"study_id"		=> $studyId,
				"checked_out"	=> 0
			);
			$chTbl->insert($chData);
			
			$message .= " is INACTIVE and ";
		}
		
		$message .= " the OUTCOME was recorded";
		
		if($data["other_studies"] == 1)
			$message .= "\n<br />NOTE: Baby's scheduling and record status were not changed as this baby was scheduled for multiple studies. Ensure that you complete the outcome for the other studies as well";
		
		return $message;
	}
	
	/**
	 * Set outcome for google calendar event
	 *
	 * @param array $data User form data
	 * @return string Message for success page
	 **/
	protected function _gCalOutcome($data, $gCalInfo)
	{
		# 1. INSANITY CHECKS
	
		// Check connection just in case
		if(empty($this->_gCalService))
			throw new Zend_Gdata_App_Exception("No google calendar connection!");
		
		// Check calendar id exists
		if(empty($this->_gCalID))
			throw new Zend_Gdata_App_Exception("No google calendar id!");
		
		// Store any messages to pass
		$message = "";
		
		try {
			# 2. Setup Event Details
			
			// Set Outcome
			switch ($data["study_outcome_id"]) {
				case self::OUTCOME_RUN:
					$outcome = "RUN IN STUDY";
					break;
				case self::OUTCOME_CANCELED:
					$outcome = FALSE;
					break;
				case self::OUTCOME_NOSHOW:
					$outcome = "NO SHOW";
					break;
				default:
					throw new Zend_Controller_Action_Exception("Study outcome id is invalid!");
					break;
			}
			
			// Get baby/family info for event
			$babyInfo = $this->_fetchGCalBabyInfo($data);
			
			// Create start and end time for event
			$when = $this->_gCalService->newWhen();
			$when->startTime = "{$data['appt_date']}T{$data['appt_time']}:00.000";
			$when->endTime = "{$data['appt_date']}T{$data['appointment_end_time']}:00.000";
			
			# 3. Edit Event
					
			// Create gcal query to find event
			$query = $this->_gCalService->newEventQuery();
			$query->setUser($this->_gCalID);
			$query->setVisibility('private');
			$query->setProjection('full');
			$query->setEvent($this->_gCalEventID);
			
			// Fetch event
			$event = $this->_gCalService->getCalendarEventEntry($query);
			
			if($outcome) {
				// Populate the event with the desired information
				$event->title = $this->_gCalService->newTitle("{$babyInfo['mother_first_name']} / {$babyInfo['first_name']} ({$babyInfo['sex']}) {$this->_babyId} {$babyInfo['age']} - {$outcome}");
				$event->when = array($when);
				$event->content = $this->_gCalService->newContent($data["comments"]);
			
				// Save Event
				$event->save();
				// Message
				$message = "Succesfully updated event to Google Calendar";
			} else {
				// Delete event if canceled appointment
				$event->delete();
				// Message
				$message = "Succesfully deleted event from Google Calendar";
			}
		} catch (Exception $e) {
			$message = "Error updating event to Google Calendar <br />\n";
			$message .= $e->getMessage();
		}
		
		return $message;
	}
}

