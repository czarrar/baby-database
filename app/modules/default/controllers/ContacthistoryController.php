<?php

# New, Edit, Delete, List
# Given baby_id, select study + attempt, datetime,
# Given caller, type + means + outcome of contact, callback
# Comments

# In list, add researcher

class ContacthistoryController extends Zend_Controller_Action 
{
	// Numbers (ids in status table) corresponding to some status
	const INACTIVE		= 1;
	const ARCHIVED 		= 2;
	const CONTACTING	= 3;
	const SCHEDULED 	= 4;
	const CONFIRMED 	= 5;
	const RUN			= 6;
	const CANCELED		= 7;
	const NO_SHOW		= 8;
	
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

	function init()
	{
		// Leave header out
		$this->view->headerFile = '_empty.phtml';
	}
	
	function indexAction()
	{
		$this->_forward('new');
	}
	
	public function listAction()
	{
		$dirs = Zend_Registry::get('dirs');
	
		// Attach additional css file for table
		$this->view->headLink()
			->appendStylesheet("{$dirs->styles}/sortable_tables.css", "screen, projection");
		
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
		// type, method, study, researcher, caller,
		// date/time, attempt, outcome, comments
		
	 	// Get attempt, datetime, method, callback, comments
		// from base table (contact_histories)
		->from(array('ch' => 'contact_histories'),
	        array('attempt', 'datetime', "method" => 'contact_method', "callback" => 'contact_callback', "callback_date", "callback_time_begin", "callback_time_end", "comments"))
		// Get study name
	    ->joinLeft(array('s' => 'studies'),
	        'ch.study_id = s.id', "study")
		// Get researcher name
		->joinLeft(array('r' => 'researchers'),
			"s.researcher_id = r.id", "researcher")
		// Get caller
		->joinLeft(array("c" => "callers"),
			"ch.caller_id = c.id", array("caller" => "name"))
		// Get type
		->joinLeft(array('ct' => "contact_types"),
			"ch.contact_type_id = ct.id", "type")
		// Get method
		->joinLeft(array("co" => "contact_outcomes"),
			"ch.contact_outcome_id = co.id", "outcome")
		
		// Want only rows with given baby_id
		->where("ch.baby_id = ?", $babyId)
		// Order the rows by the datetime of their entry
		->order("ch.datetime DESC");
		
		/* Execute Query */
		$db->setFetchMode(Zend_Db::FETCH_OBJ);
		$stmt = $db->query($select);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Save into view
		$this->view->assign("results", $result);
			
	}
	
	public function viewAction()
	{
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		// Set the script action file
		$this->_helper->viewRenderer->setScriptAction('crud');
		// Set partial to display
		$this->view->displayPartial = "view";
		
		// Get primary keys: baby id..., if none then throw exception
		if ($this->_getParam('baby_id') and $this->_getParam('study_id') and $this->_getParam('attempt')) {
			$babyId = $this->_getParam('baby_id');
			$this->view->babyId = $babyId;
			$studyId = $this->_getParam('study_id');
			$attempt = $this->_getParam("attempt");
		}
		else
			throw new Exception("Primary keys: 'baby_id', 'study_id', and 'attempt' not given! Nothing to display");
		
		// Get row + display
		/* Get db adapter */
		
		$db = Zend_Registry::get('db');
		
		/* Setup base query */
		
		$select = $db->select()
			->distinct()
		
		// Want to display these columns:
		// type, method, study, researcher, caller,
		// date/time, attempt, outcome, comments
		
	 	// Get attempt, datetime, method, callback, comments
		// from base table (contact_histories)
		->from(array('ch' => 'contact_histories'),
	        array("baby_id", 'attempt', 'datetime', "method" => 'contact_method', "callback" => 'contact_callback', "callback_date", "callback_time_begin", "callback_time_end", "comments"))
		// Get study name
	    ->joinLeft(array('s' => 'studies'),
	        'ch.study_id = s.id', "study")
		// Get researcher name
		->joinLeft(array('r' => 'researchers'),
			"s.researcher_id = r.id", "researcher")
		// Get caller
		->joinLeft(array("c" => "callers"),
			"ch.caller_id = c.id", array("caller" => "name"))
		// Get type
		->joinLeft(array('ct' => "contact_types"),
			"ch.contact_type_id = ct.id", "type")
		// Get method
		->joinLeft(array("co" => "contact_outcomes"),
			"ch.contact_outcome_id = co.id", "outcome")
		
		// Want only rows with given baby_id, study_id, and attempt#
		->where("ch.baby_id = ?", $babyId)
		->where("ch.study_id = ?", $studyId)
		->where("ch.attempt = ?", $attempt);
		
		/* Execute Query */
		$stmt = $select->query();
		$stmt->execute();
		$result = $stmt->fetchAll();
		$result = $result[0];
		
		// Save into view
		$this->view->assign($result);
	}
	
		 /**
	  * Fetch google calendar class with active connection
	  *
	  * @return string
	  **/
 	protected function _fetchGCal($studyId)
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
		
		try {
			# 1. Authenticate
		
			// Get authentication information from lab table (using study id)
			$db = Zend_Registry::get('db');
			$select = $db->select()
				->distinct()
				->from(array('l' => 'labs'),
					array("gcal_username", "gcal_password", "gcal_contact_calendar_id"))
				->joinLeft(array('r' => 'researchers'),
					'l.id = r.lab_id', array())
				->joinLeft(array('s' => 'studies'),
					"r.id = s.researcher_id", array())
				->where('s.id = ?', $studyId);
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
			if(empty($info["gcal_contact_calendar_id"]))
				throw new Zend_Gdata_App_Exception("Study (id=" . $this->_studyId . ") does not have information on a Google Calendar ID.");
			else
				$this->_gCalID = $info["gcal_contact_calendar_id"];
			
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
 	protected function _fetchGCalBabyInfo($babyId, $studyId)
 	{
		/// Create database query
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->distinct()
			->from(array('b' => 'babies'),
				array("first_name"))
			->joinLeft(array('f' => 'families'),
				'f.id = b.family_id', array("mother_first_name"))
			->where('b.id = ?', $babyId);
		$stmt = $db->query($select);
		/// Fetch database query in array format
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Check that you have only one row with baby info
		if (count($result)!=1)
			throw new Exception("Searching for baby info (ID {$babyId}) in database but did not find the row.");
		else
			$babyInfo = $result[0];
			
		// Create new database query
		$studyTbl = new Study();
		$result = $studyTbl->fetchRow($studyTbl->select()->where("id = ?", $studyId));
		
		// Check that you have only one row
		if (count($result)!=1)
			throw new Exception("Searching for study info (ID {$studyId}) in database but did not find the row.");
		else
			$babyInfo['study'] = $result->study;
		
		return $babyInfo;
 	}
	
	/**
	 * Add Event to Google Callback Calendar
	 *
	 **/
	protected function _gCalCallback($data)
	{
		# 1. Connect to Google Calendar and Authenticate
		$gCalErr = $this->_fetchGCal($data["study_id"]);
		if(!empty($gCalErr))
			return $gCalErr;
		
		// Store any messages to pass
		$message = "";
		
		try {
			# 2. Setup Event Details
		
			// Create URI for future use
			$uri = "http://www.google.com/calendar/feeds/{$this->_gCalID}/private/full";
			
			// Setup baby/family info for event
			$babyInfo = $this->_fetchGCalBabyInfo($data["baby_id"], $data["study_id"]);
			
			// Check for data
			if (empty($data["callback_date"])) {
				$message .= "No callback date given for Google Calendar. Please manually enter into calendar.";
				return $message;
			}
			
			// Setup start and end time for event
			$when = $this->_gCalService->newWhen();
			if(empty($data["callback_time_begin"]) and empty($data["callback_time_end"])) {
				$message .= "Missing callback times for Google Calendar, going to make this event for the whole day of {$data['callback_date']}. Please manually change as necessary.<br />\n";
				$when->startTime = "{$data['callback_date']}";
				$when->endTime = "{$data['callback_date']}";
			} else if (empty($data["callback_time_begin"]) or empty($data["callback_time_end"])) {
				$when->startTime = "{$data['callback_date']}T{$data['callback_time_begin']}.000";
				$when->endTime = "{$data['callback_date']}T{$data['callback_time_begin']}.000";
				
			} else {
				$when->startTime = "{$data['callback_date']}T{$data['callback_time_begin']}.000";
				$when->endTime = "{$data['callback_date']}T{$data['callback_time_end']}.000";
			}
			
			# 3. Create Event
			
			// Create new event using the calendar service's magic factory method
			$event = $this->_gCalService->newEventEntry();
		
			// Populate the event with the desired information
			// Note that each attribute is created as an instance of a matching class
			$event->title = $this->_gCalService->newTitle("{$babyInfo['mother_first_name']} / {$babyInfo['first_name']} [{$babyInfo['study']}] {$data['baby_id']}");
			$event->when = array($when);
			$event->content = $this->_gCalService->newContent($data["comments"]);
			
			// Upload the event to the calendar server
			// A copy of the event as it is recorded on the server is returned
			$newEvent = $this->_gCalService->insertEvent($event, $uri);
			
			// Update message
			$message .= "Succesfully added callback event to Google Calendar";
		} catch (Exception $e) {
			$message .= "Error adding event to Google Calendar <br />\n";
			$message .= $e->getMessage();
		}
		
		return $message;
	}
	
	/**
	 * Page for new contact history
	 *
	 **/
	public function newAction()
	{
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
	
        $this->view->headLink()->appendStylesheet(
            "http://ajax.googleapis.com/ajax/libs/dojo/1.5/dijit/themes/claro/claro.css");
        
		$db = Zend_Registry::get('db');
		
		// Set the script action file
		$this->_helper->viewRenderer->setScriptAction('crud');
		// Set partial to display
		$this->view->displayPartial = "new";
		
		// Get the baby id, if none then throw exception
		if ($this->_getParam('baby_id')) {
			$babyId = $this->_getParam('baby_id');
			$this->view->babyId = $babyId;
		}
		else
			throw new Exception("No baby id given! Nothing to display");
			
		// Get studyId
		$studyId = $this->_getParam('study_id');
		$this->view->studyId = $studyId;
		
		// Ability to adjust record and scheduling status
		if ($_SESSION["user_privelages"] == "admin" or $_SESSION["user_privelages"] == "coordinator") {
			$this->view->isAdminOrCoord = True;
		} else {
		    $this->view->disableCallDate = NULL;
		}
		
		// Get type
		$type = $this->_getParam("type");
		switch ($type) {
			case 'schedule':
				$this->view->type = 1;
				break;
			case 'confirm':
				$this->view->type = 3;
				break;
		}
		
		// Get options for select form fields
		// options for study, contact type, contact outcome
		$contactHistory = new ContactHistory();
		$study = new Study();
		$this->view->studyOptions = $study->getStudies(array("" => "Choose"), TRUE, TRUE);
		$this->view->callerOptions = $contactHistory->getRefSelectOptions('Callers', 'Callers');
		$this->view->typeOptions = $contactHistory->getRefSelectOptions("ContactType", "Type");
		$this->view->outcomeOptions = $contactHistory->getRefSelectOptions('ContactOutcome', "Outcome");
				
		// Set other options for select form fields manually
		// options for contact methods and contact callback
		$this->view->methodOptions = array("" => "Choose", "Phone" => "Phone", "Email" => "Email", "Mail" => "Mail");
		$temp = array("N/A", "AM", "PM", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sept", "Oct", "Nov", "Dec");
		$this->view->callbackOptions = array_combine($temp, $temp);
		
		// To Callback Options
		$this->view->toCallbackOptions = array("" => "No", "1" => "Yes");
		
		/* Process Form */
		
		$tableClasses = "ContactHistory";
		
		$formCreateRow = $this->_helper->FormCreate;
		$result = $formCreateRow->processForm(array("contact" => $tableClasses));
		$formData = $formCreateRow->getData("contact");

		// Successful form, redirect to view page
		if($result == 0) {
			$this->view->displayPartial = "success";
			
			// If set contact_outcome_id=4, then add to google calendar
			$data = $formCreateRow->getData("contact");
			if($data["to_callback"]) {
				$gCalResult = $this->_gCalCallback($data);
				$this->view->addtlMessage = "<br />\n" . $gCalResult;
			} else {
				$this->view->addtlMessage = "<br />\n Callback option not selected, nothing added to Google Calendar.";
			}
			
			// Check baby out
			// If activate or inactivate exist
			if ($formData["activate"] or $formData["inactivate"]) {
				// record status
				$checkedOut = ($formData["activate"]) ? 1 : 0 ;
				// scheduling status
				$schedulingId = ($formData["activate"]) ? self::CONTACTING : self::INACTIVE ;
				
				# 1. UPDATE CHECKOUT HISTORY
				$chTbl = new CheckoutHistory();
				$chData = array(
					"baby_id"		=> $babyId,
					"study_id"		=> $studyId,
					"checked_out"	=> $checkedOut
				);
				$chTbl->filterInsert($chData);
				
				# 2. UPDATE CHECKED_OUT + STATUS
				$babyTbl = new Baby();
				$where = $db->quoteInto("id = ?", $babyId);
				$babyData = array(
					"checked_out"			=> $checkedOut,
					"checkout_date"			=> new Zend_Db_Expr('CURDATE()'),
					"checkout_caller_id"	=> $_SESSION["caller_id"],
					"status_id"				=> $schedulingId
				);
				$babyTbl->filterUpdate($babyData, $where);
				
				# 3. ADD/REMOVE TO/FROM BABY-STUDIES
				$bsTbl = new BabyStudy();
				if ($formData["activate"]) {
					// add
					$bsData = array(
						"baby_id"		=> $babyId,
						"study_id"		=> $studyId,
						"caller_id"		=> $_SESSION["caller_id"],
						"appointment"	=> new Zend_Db_Expr('CURDATE()')
					);
					$bsTbl->filterInsert($bsData);
				} else {
					// delete
					$where = array(
						$db->quoteInto("baby_id = ?", $babyId),
						$db->quoteInto("study_id = ?", $studyId)
					);
					$bsTbl->delete($where);
				}
			}
		// Populate date + time column
		}
		else {

			// New Form
			if ($result == -1) {
				// Set current date
				$datetime = new Zend_Date();

				// Get attempt number
				$db = Zend_Registry::get('db');
				$query = "SELECT MAX(attempt) FROM contact_histories WHERE baby_id=?";
				$stmt = $db->query($query, array($babyId));
				$stmt->execute();
				$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
				$result = ($result[0]) ? $result[0]+1 : 1 ;
								
				// Set attempt number + checkout status
				$formCreateRow->pushData("contact", "attempt", $result);			
			}
			// Form with bad data
			else {			
				$datetime = new Zend_Date($formData["datetime"], "YYYY-MM-dd HH:mm");
				
				$this->view->callback_date = $formData["callback_date"];
				$this->view->callback_time_begin = $formData["callback_time_begin"];
				$this->view->callback_time_end = $formData["callback_time_end"];
				
			}
			
			// Get if active or contacting
			$babyTbl = new Baby();
			$select = $babyTbl->select()->setIntegrityCheck(false);
			$select->from($babyTbl, array("checked_out", "family_id", "first_name", "last_name"))
					->where("babies.id = ?", $babyId)
					->join("statuses",
						'babies.status_id <=> statuses.id', array("status", "group"));
			$babyRow = $babyTbl->fetchAll($select)->current();
			
			// Set baby's name
			$this->view->babyName = $babyRow->first_name . " " . $babyRow->last_name;
			
			// Set record and scheduling status for view
			$this->view->recordStatus = ($babyRow->checked_out) ? "ACTIVE" : "INACTIVE";
			$this->view->schedulingStatus = strtoupper($babyRow->status);
			
			// If baby inactive -> give option to activate baby record
			// else if baby contacting -> give option to check-in
			$this->view->isInactive = ($babyRow->checked_out == 0);
			$this->view->isContacting = ($babyRow->status == "contacting");
			
			// Call Date/Time
			$this->view->date = $datetime->get("YYYY-MM-dd");
			$this->view->time = $datetime->get("HH:mm");
			
			// Add to form
			$formCreateRow->pushData("contact", "checkout", $babyRow->checked_out);
			$formCreateRow->setForm();
			
			// Get family id and siblings
			$familyTbl = new Family();
			$familyRowset = $familyTbl->find($babyRow->family_id);
			$familyRow = $familyRowset->current();
			$familySelect = $familyTbl->select();
			$familySelect->from(new Baby(), array('id'));
			$familyBabies = $familyRow->findDependentRowset('Baby', 'Family', $familySelect);
			$this->view->siblings = $familyBabies;
			$this->view->numSiblings = count($familyBabies) - 1;
		}
	}
}
