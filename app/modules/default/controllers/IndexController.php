<?php

class IndexController extends Zend_Controller_Action 
{

	protected $_rawdata = array();

	function init()
	{
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}
	
	function indexAction()
	{
		
	}
	
	/**
	 * CALCULATE AGE/DATE:
	 *	a) Given a parameter of dob + date, gives the age of baby
	 *	b) Given a parameter of dob + age, gives date at that age/
	 **/
	function calculatorAction()
	{
		#$calculator = new Zarrar_AgeCalculator();
		#$calculator->setDob("1984-12-10")
		#		   ->setDate("2008-02-12");
		#echo "Dob: " . $calculator->getDob();
		#echo "<br>";
		#echo "Age: " . $calculator->getAge("full");
		#echo "<br>";
		#echo "Date: " . $calculator->getDate();
		#
		#exit();
		// Suppress header
		$this->view->headerFile = '_empty.phtml';
		
		# CREATE FORM
		
		// New Form
		$form = new Zend_Form();
		$form->setAction("")
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
		
		// 1. Dob Element
		$yearRange = array("years" => array('1980', date('Y')+1));
		$dob = new Zarrar_Form_SubForm_Date($yearRange);
		$dob->setLabel("Date of Birth");
		$form->addSubForm($dob, 'dob');
		
		// 2. Date element
		$yearRange = array("years" => 1);
		$date = new Zarrar_Form_SubForm_Date($yearRange);
		$date->setLabel("Date");
		$form->addSubForm($date, 'date');
		
		// 3. Age Element
		$yearRange = array("years" => array(0,18));
		$age = new Zarrar_Form_SubForm_Age($yearRange);
		$age->setLabel("Age");
		$form->addSubForm($age, 'age');
		
		// 4. Study
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study");
		$study->setLabel("Study")
			  ->setMultiOptions($studyOptions);
		$form->addElement($study);
		
		// 4. SUBMIT
		$submit = new Zend_Form_Element_Submit("submit", "Calculate");
		$form->addElement($submit);

	
		// Process data
		if ($this->getRequest()->isPost()) {
			// Check if valid
			$isValid = $form->isValid($this->getRequest()->getPost());
			
			// Set data
			$formData = $form->getValues();
			
			// Check if have 2 of 3 variables
			$checkVars = 0;
			$checkVars = ($formData["dob"]) ? $checkVars + 1 : $checkVars ;
			$checkVars = ($formData["date"]) ? $checkVars + 1 : $checkVars ;
			$checkVars = ($formData["age"]) ? $checkVars + 1 : $checkVars ;
			if ($checkVars != 2) {
				$errors = array(
					array("ERRORS" => array(
						"info" => "Please enter 2 variables (no more / no less)!"
				)));
				$isValid = False;
			} else {
				$errors = array();
			}
			
			if (!$isValid) {
				// Save error messages
				$this->view->errors = array_merge($errors, $form->getMessages());
			} else {		
				// Get dob
				$dob = $formData["dob"];
				// Get date
				$date = $formData["date"];
				// Get age
				$age = $formData["age"];
			
				// Load calculator
				$calculator = new Zarrar_AgeCalculator();
			
				// Option (a)
				if ($dob and $date) {
					// Set variables
					$calculator->setDob($dob)
							   ->setDate($date);	
					// Get age
					$age = $calculator->getAge("full");
					// Set to view
					$calculated = "child's age";
				}
				// Option (b)
				else if ($dob and $age) {
					// Set variables
					$calculator->setDob($dob)
							   ->setAge($age);						
					// Get date
					$date = $calculator->getDate();
					// Set to view
					$calculated = "date";
				}
				// Option (c)
				else if ($date and $age) {
					// Set variables
					$calculator->setDate($date)
							   ->setAge($age);						
					// Get date
					$dob = $calculator->getDob();
					// Set to view
					$calculated = "date of birth";
				}
				// some weird error
				else {
					throw new Exception("Some weird error occurred while calculating the age");
				}
				
				// Check for study range
				if ($studyId = $formData['study']) {
					// Fetch study info
					$studyTbl = new Study();
					$select = $studyTbl->select();
					$select->from($studyTbl, array('study', 'lower_age', 'upper_age'))
						   ->where("id = ?", $studyId);
					$studyRow = $studyTbl->fetchRow($select);
					
					// Check if have lower and upper age range
					if ($studyRow->lower_age and $studyRow->upper_age) {
						// Check if age in appropriate range
					
						// Save lower and upper ranges
						$lowerAge = $calculator->formatAge($studyRow->lower_age);
						$upperAge = $calculator->formatAge($studyRow->upper_age);
					
						// Baby age vs Study lower age
						// Must = 0 or 1 (i.e. baby age greater than lower age range)
						$cmpLower = $calculator->compareAge($studyRow->lower_age);
						if ($cmpLower == -1) {
							$this->view->errors = array(
								array("ERRORS" => array(
									"info" => "Whoops, baby is younger than your study's lower age range ({$lowerAge})!"
							)));
						}
						
						// Baby age vs Study upper age
						// Must = 0 or -1 (i.e. baby age lower than upper age range)
						$cmpUpper = $calculator->compareAge($studyRow->upper_age);
						if ($cmpUpper == 1) {
							$this->view->errors = array(
								array("ERRORS" => array(
									"info" => "Whoops, baby is older than your study's upper age range ({$upperAge})!"
							)));
						}
						
						// Save range to view
						$this->view->study = $studyRow->study;
						$this->view->lowerAge = $lowerAge;
						$this->view->upperAge = $upperAge;
					}
				}
			
				// Set view variables
				$this->view->dob = $dob;
				$this->view->age = $age;
				$this->view->date = $date;
				$this->view->calculated = $calculated;
			}
		}
		// Set defaults
		else {
			// Empty defaults
			$defaults = array();
			
			// Baby Id or Dob
			if ($babyId = $this->_getParam("baby_id")) {
				// Fetch baby row
				$babyTbl = new Baby();
				$select = $babyTbl->select();
				$select->from($babyTbl, array('dob'))
					   ->where("id = ?", $babyId);
				$babyRow = $babyTbl->fetchRow($select);
				// Save
				$defaults["dob"] = $babyRow->dob;
			} else {
				$defaults["dob"] = $this->_getParam("dob");
			}
			// Date
			$defaults["date"] = ($this->_getParam("date")) ? $this->_getParam("date") : date("Y-M-d") ;
			// Age
			$defaults["age"] = $this->_getParam("age");
			
			// Study
			$defaults["study"] = $this->_getParam("study_id");
			
			// Populate form
			$form->populate($defaults);
		}
		
		// Save form
		$this->view->form = $form; 
	}
	
	function testAction()
	{
		var_dump(Zend_Date::isDate("01:10", "HH:mm"));
		exit();
	
		$endDate = new Zend_Date("2007-01-10", "YYYY-MM-dd");
		$endDate->sub("0000-00-00", "YYYY-MM-dd");
		$endDate->add("0", "d");
		$youngestBaby = $endDate->get("YYYY-MM-dd");
		var_dump($youngestBaby);
		exit();
	
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
	
		$form = new Zend_Form;
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElement('text', 'username', array("label" => "User"));
		#$form->addElement('textarea', 'password', array("label" => "Pass"));
		$form->populate(array("username" => "me"));
		$form->username->setDecorators(array("ViewHelper", "Label"));
		//$form->setDecorators(array(
		//	array(
		//		'decorator'	=> "ViewScript",
		//		"options"	=> array("viewscript" => "test.phtml")
		//	)
		//));
		$this->view->form = $form;
		/*
		- Want to make a db through which you can enter new entries!
		- Want to be able to checkout these record?
		*/		

		$projax = new Projax();
		$this->view->projax = $projax;
		$task = isset($_GET['task']) ? $_GET['task'] : 'view';
        
		if ( $task == 'test' ) {
		    // Don't want to render any view scripts
    	    // Clear any existing view scripts (i.e. header.phtml)
            #$this->getResponse()->clearBody();
			// Don't want to render any view scripts
		    $this->_helper->viewRenderer->setNoRender();
		    // Clear any existing view scripts (i.e. header.phtml)
	        $this->getResponse()->clearBody();
			$response = $this->getResponse();
            echo "hey";
			$response->setHttpResponseCode(500);
			echo $response->getHttpResponseCode();
            return;
        }
        
        
        
	    if(isset($_SESSION['test']))
	        unset($_SESSION['test']);
	}
	
	// Ajax action handles ajax request from index
	function indexAjaxAction()
	{
	    // Clear any existing view scripts (i.e. header.phtml)
        $this->getResponse()->clearBody();
        // Don't want to render any view scripts
	    $this->_helper->viewRenderer->setNoRender();
        
        // Declare Projax
        $projax = new Projax();
	  
        // See what I need to do
        $task = isset($_GET['task']) ? $_GET['task'] : 'view';
    
        if( $task == 'ajax' ) {
            //The ajax output
            echo nl2br($_POST['value']);
        }
        elseif ( $task == 'test' ) {
            echo "hey2";
            exit();
        }      
	}
	
	/*
	 * Retrieve the current URL so that the AuthSub server knows where to
	 * redirect the user after authentication is complete.
	 */
	protected function getCurrentUrl()
	{
		global $_SERVER;

		// Filter php_self to avoid a security vulnerability.
		$php_request_uri =
		    htmlentities(substr($_SERVER['REQUEST_URI'],
		                        0,
		                        strcspn($_SERVER['REQUEST_URI'], "\n\r")),
		                        ENT_QUOTES);

		if (isset($_SERVER['HTTPS']) &&
		    strtolower($_SERVER['HTTPS']) == 'on') {
		    $protocol = 'https://';
		} else {
		    $protocol = 'http://';
		}
		$host = $_SERVER['HTTP_HOST'];
		if ($_SERVER['HTTP_PORT'] != '' &&
		    (($protocol == 'http://' && $_SERVER['HTTP_PORT'] != '80') ||
		    ($protocol == 'https://' && $_SERVER['HTTP_PORT'] != '443'))) {
		    $port = ':' . $_SERVER['HTTP_PORT'];
		} else {
		    $port = '';
		}
		
		return $protocol . $host . $port . $php_request_uri;
	}
	
	function tempNewAction()
	{
		$curdate = new Zend_Date();
		$curdate->sub("1984-01-10", "YYYY-MM-dd");
		echo $curdate->toString("YYYY-MM-dd");
		exit();
	}
	
	function tempAction()
	{	
		include_once "spyc.php5";
		
		# TODO:
		# 1. figure out how to change message for required
		# 2. Create formElement stuff to allow for date and time (how do you use it then?)
		# 3. Want something common for new and update, and then have unique stuff
		# 4. Setup formatting for errors (both at top and below form field)
		# 5. Do we want to extend Element so that getValue will only give something if is not null? or maybe at level of entry throw out empty values
		# 6. Extend Zend Form to allow for default validators + filters
		# 7. Switch warnings to just show up on the next page with confirmation
		#	- need to find out how to have data posted back and posted forward
		
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
		
		$config = Spyc::YAMLLoad(ROOT_DIR . '/app/etc/test.yaml');
				
		$baby = new Zend_Form_SubForm($config["baby"]);
		$baby->list->setMultiOptions(array("hey", "you"));
		$family = new Zend_Form_SubForm($config["family"]);
		
		$baby->setElementDecorators(array(
	       'ViewHelper',
			'Errors',
			'Label'
	   	));
		
		$family->setElementDecorators(array(
	       'ViewHelper',
			'Errors',
			'Label'
	   	));
		
		$form = new Zend_Form($config["main"]);
		$form->addSubForm($baby, "baby");
		$form->addSubForm($family, "family");
		
		$form->setDecorators(array(
	       'FormElements',
	       'Form'
		));
		$form->submit->setDecorators(array(
	       array(
	           'decorator' => 'ViewHelper',
	           'options' => array('helper' => 'formSubmit'))
	   	));
		
		$form->addPrefixPath('Zarrar_View_Helper', 'Zarrar/View/Helper');
	
		if ($this->getRequest()->isPost()) {
			$form->isValid($_POST);
		}
				
		// Can set options later..
		
		$this->view->form = $form;
		
		var_dump($form->getValues());

	}

/*
# Not needed below

	# Fix enthusiasm field in study history by adding 1 to every non-zero entry
	function fixEnthusiasmAction() {
		$shTbl = new StudyHistory();
		$shRows = $shTbl->fetchAll($shTbl->select()->where("level_enthusiasm IS NOT NULL"));
		$numRows = count($shRows);
		echo "Number of Rows: {$numRows}";
		echo "<br /><br />\n";
		foreach ($shRows as $shRow) {
			echo "Row Id: {$shRow->id}, ";
			echo "Old Enthusiasm: {$shRow->level_enthusiasm}";
			#$shRow->level_enthusiasm = $shRow->level_enthusiasm + 1;
			#$shRow->save();
			#echo "New Enthusiasm: {$shRow->level_enthusiasm}";
			echo "<br/>";
		}
		exit();
	}

	function calendarAction()
	{
		require_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_Calendar');
		Zend_Loader::loadClass('Zend_Http_Client');
		Zend_Loader::loadClass('Zend_Gdata_App_CaptchaRequiredException');
		Zend_Loader::loadClass('Zend_Gdata_App_AuthException');

		$test = convert_uuencode("ZarrarAliaA101!");
		echo $test;
		echo "<br />";
		echo convert_uudecode($test);
		exit();

		# 1. Authenticate
		// Get authentication information from lab table (using study id)
		$studyId = 324;
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->distinct()
			->from(array('l' => 'labs'),
				array("gcal_username", "gcal_password"))
			->joinLeft(array('r' => 'researchers'),
				'l.id = r.lab_id', array())
			->joinLeft(array('s' => 'studies'),
				"r.id = s.researcher_id", array("gcal_calendar_id"))
			->where('s.id = ?', $studyId);
		$stmt = $db->query($select);
		$stmt->execute();
		$result = $stmt->fetchAll();	// Array format
		// Should have one row
		if(count($result) != 1)
			throw new Zend_Controller_Action_Exception("Found more than one table row for given study id (" . $this->studyId . ")");
		else
			$info = $result[0];
		// Complete client authentication
		try {
			$client = Zend_Gdata_ClientLogin::getHttpClient($info["gcal_username"], $info["gcal_password"], Zend_Gdata_Calendar::AUTH_SERVICE_NAME);
		} catch (Zend_Gdata_App_CaptchaRequiredException $cre) {
			# TODO: Have a pop-up with form that asks the captcha and after submission it will update things with javascript
			echo "CONTACT ADMIN, CAPTCHA REQUIRED! <br /> \n";
			echo 'URL of CAPTCHA image: ' . $cre->getCaptchaUrl() . "<br />\n";
			echo 'Token ID: ' . $cre->getCaptchaToken() . "<br />\n";
			exit();
		}	
		
		// Create an instance of the Calendar service
		$service = new Zend_Gdata_Calendar($client);
		
		// Create URI for future use
		$calID = $info["gcal_calendar_id"];
		$uri = "http://www.google.com/calendar/feeds/{$calID}/private/full";
		
		// Create new event using the calendar service's magic factory method
		$event = $service->newEventEntry();
		
		// Setup start and end time for event
		$when = $service->newWhen();
		$when->startTime = "2009-07-05T10:00:00.000";
		$when->endTime = "2009-07-05T11:00:00.000";
		
		// Populate the event with the desired information
		// Note that each attribute is crated as an instance of a matching class
		$event->title = $service->newTitle("BABY: 222 - SCHEDULED");
		$event->when = array($when);
		$event->content = $service->newContent("testing");
		
		// Upload the event to the calendar server
		// A copy of the event as it is recorded on the server is returned
		$newEvent = $service->insertEvent($event, $uri);
		$eventId = $newEvent->getId()->text;
		$pos = strrpos($eventId, "/") + 1;
		$eventId = substr($eventId, $pos);
				
		$query = $service->newEventQuery();
		$query->setUser($info["gcal_calendar_id"]);
		$query->setVisibility('private');
		$query->setProjection('full');
		$query->setEvent($eventId);

		try {
			$event = $service->getCalendarEventEntry($query);
			$event->title = $service->newTitle($event->title->text . " EDITED");
			$event->save();
		} catch (Zend_Gdata_App_Exception $e) {
			echo "Error: " . $e->getMessage();
		}
		
		
		echo "<br />";
		echo "ID: ";
		var_dump($newEvent->getId());
		echo "<br/>";
		var_dump($newEvent->getEditLink());
		
		echo "<br/>";
		
		$query = $service->newEventQuery();
		$query->setUser($info["gcal_calendar_id"]);
		$query->setVisibility('private');
		$query->setProjection('full');
		
		# Lab has a username and password for the google calender
		# Study has a id associated with the calendar in the account
		# Have event id to be stored in baby-studies table, must be unique		
		# Scheduling -> Create event (title has babyid - STATUS), description has comments
		
		# 1. add fields to database
		#	- lab (gcal_user, gcal_pwd)
		#	- study (gcal_calendar_id)
		#	- baby_studies (gcal_event_id)
		# 2. Edit Scheduling in babystudy
		#	- authenticate
		#	- ...
		
		// Authenticate
		
		
		// Retrieve the event list from the calendar server
		try {
			$eventFeed = $service->getCalendarEventFeed($query);
		} catch (Zend_Gdata_App_Exception $e) {
			echo "Error: " . $e->getMessage();
		}

		// Iterate through the list of events, outputting them as an HTML list
		echo "<ul>";
		foreach ($eventFeed as $event) {
			echo "<li>" . $event->title . " (Event ID: " . $event->id . ")</li>";
		}
		echo "</ul>";


		
		#// Try this
		#try {
		#    $listFeed= $service->getCalendarListFeed();
		#	echo "<h1>Calendar List Feed</h1>";
		#	echo "<ul>";
		#	foreach ($listFeed as $calendar) {
		#		echo "<li>" . $calendar->title . " (Event Feed: " . $calendar->id . ")</li>";
		#		if ($calendar->title == "Lab Meeting") {
		#			$temp = $calendar;
		#		}
		#	}
		#	echo "</ul>";			
#
		#} catch (Zend_Gdata_App_Exception $e) {
		#    echo "Error: " . $e->getResponse();
		#}
		
		#$defaultId = "nyu.baby.child@gmail.com";
		#$roomOneId = "03421hme216bki3ga87dltma78@group.calendar.google.com";
		#$roomTwoId = "3a2tjkasv09gosnhk906d0ammo@group.calendar.google.com";
		#		
		#$gc = new Zend_Gdata_Calendar($client);
		#$newEntry = $gc->newEventEntry();
		#
		#// Set title
		#$title = 'STUDY NAME - BABY ID';
		#$where = '6 Washington Place, NY 10003';
		#$newEntry->title = $gc->newTitle(trim($title));
		#$newEntry->where  = array($gc->newWhere($where));
		#
		#// Set description
		#$desc='COMMENTS FROM SCHEDULING';
		#$newEntry->content = $gc->newContent($desc);
		#$newEntry->content->type = 'text';
		#
		#// Set the date using RFC 3339 format.
		#$startDate = "2008-03-13";
		#$startTime = "14:00";
		#$endDate = "2008-03-13";
		#$endTime = "16:00";
		#$tzOffset = "-05";
#
		#$when = $gc->newWhen();
		#$when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}:00";
		#$when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}:00";
		#$newEntry->when = array($when);
#
		#$createdEntry = $gc->insertEvent($newEntry, "http://www.google.com/calendar/feeds/{$roomOneId}/private/full");
	}


	function addStudyHistoryAction()
	{
		set_time_limit(300);
	
		# REQ: baby_id, study_id, datetime, comments
		# 0: baby_id, 1+2: appointment, 3: study, 5: comments
		# 4: Cancelled->6, Completed->5, No Show->7
		
		# new below
		# allow insertion of new study ids
		# look for cancel + no show in comments
		# study outcome: completed->1, cancel->3, no show->2
		
		$db = Zend_Registry::get('db');
		$babyTbl = new Baby();
		$studyTbl = new Study();
		$researcherTbl = new Researcher();
		$shTbl = new StudyHistory();

		$rowMin = 11000;
		$rowMax = 21000;

		$rowNum = 1;
		$handle = fopen("/home/content/b/a/b/babydb/html/babydb/app/etc/z_studyhistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";

			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;

			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			// Blank row
			$shRow = array();
			$studyId = "";

			// Check id
			if(empty($data[0])) {
				echo "ERROR: baby_id not found, skipping entry #{$rowNum}";
				continue;
			}

			// Deal with status first
			$bRow = array();
			if(empty($data[4]) === false) {
				if(stripos($data[4], "cancel") !== false) {
					$bRow["status_id"] = 6;
					$shRow["study_outcome_id"] = 3;
				}
				elseif(stripos($data[4], "completed") !== false) {
					$bRow["status_id"] = 5;
					$shRow["study_outcome_id"] = 1;
				}
				elseif(stripos($data[4], "no show") !== false) {
					$bRow["status_id"] = 7;
					$shRow["study_outcome_id"] = 2;
				}
			}

			// Deal with study history
			
			if(empty($data[1])) {
				echo "ERROR: study date not found, skipping entry #{$rowNum}";
				continue;
			}
			if (empty($data[3])) {
				echo "ERROR: study field empty, skipping entry #{$rowNum}";
				continue;
			}
			
			// Get date
			$date = $data[1];
			if (empty($data[2]) === false) {
				$time = $data[2];
				if(strlen($time) == 2)
					$time = $time . ":00";

				$datetime = $date . " " . $time;
			}
			$date = date("Y-m-d", strtotime($date));
			$datetime = date("Y-m-d H:i", strtotime($datetime));
			
			// Get study_id
			$study = $data[3];
			try {
				$where = $studyTbl->getAdapter()->quoteInto("study LIKE ?", "%{$study}%");
				$row = $studyTbl->fetchRow($where);
			} catch(Exception $e) {
				echo "ERROR: entry #{$rowNum}<br />";
				echo $data[0] . ": " . $e . " <br />";
				continue;
			}
			
			if($row)
				$studyId = $row->id;
			else {
				if(empty($data[6]) === false) {
					$lab = strtolower($data[6]);

					// Find researcher id + lab id
					$where = $researcherTbl->getAdapter()->quoteInto("researcher LIKE ?", $lab );
					$row = $researcherTbl->fetchRow($where);
					
					$dataTbl = array(
						"date_of_entry"	=> $date,
						"study"			=> $study
					);
					
					if($row) {
						$dataTbl["researcher_id"] = $row->id;
					} else {
						$dataTbl["researcher_id"] = 1;
					}
						
					$db->beginTransaction();
					try {
						$studyId = $studyTbl->insert($dataTbl);
						$db->commit();
					} catch (Exception $e) {
						echo $data[0] . ": " . $e . " <br />";
						$db->rollback();
					}
				} else {
					echo "ERROR: could not find id for study '$study' and lab field '$data[6]' was empty or unknown, skipping entry #{$rowNum}";
					continue;
				}
			}
			
			$comments = $data[5];
			# look for cancel + no show in comments
			# study outcome: completed->1, cancel->3, no show->2
			if(empty($comments) === false) {
				$shRow["comments"] = $comments;
				if(stripos($comments, "cancel") !== false) {
					$bRow["status_id"] = 6;
					$shRow["study_outcome_id"] = 3;
				} elseif(stripos($comments, "no show") !== false) {
					$bRow["status_id"] = 7;
					$shRow["study_outcome_id"] = 2;
				}
			}
			
			$shRow["baby_id"] = $data[0];
			$shRow["appointment"] = $datetime;
			$shRow["study_id"] = $studyId;

			var_dump($data);
			echo "<br>";
			var_dump($shRow);
			echo "<br>";
			continue;
			
			if(empty($bRow) === false) {
				$db->beginTransaction();
				try {
					var_dump($where);
					echo "<br>";
				   	
					$db->commit();
				} catch (Exception $e) {
					echo $shRow["baby_id"] . ": " . $e . " <br />";
					$db->rollback();
				}
			}
			
			$db->beginTransaction();
			try {
				if(empty($bRow) === false) {
					$where = $babyTbl->getAdapter()->quoteInto("id = ?", $data[4]);
					$babyTbl->update($bRow, $where);
				}
				
				$shTbl->insert($shRow);
				$db->commit();
			} catch (Exception $e) {
				echo $shRow["baby_id"] . ": " . $e . " <br />";
				$db->rollback();
			}
		}

		fclose($handle);
		exit();

		
	}
	
	function addContactHistoryAction()
	{
		set_time_limit(600);
	
		$db = Zend_Registry::get('db');
		$babyTbl = new Baby();
		$chTbl = new ContactHistory();
		$callerTbl = new Callers();
		$researcherTbl = new Researcher();
		$labTbl = new Lab();
		$studyTbl = new Study();
		
		$rowMin = 40001;
		$rowMax = 45000;
		
		$rowNum = 1;
		$handle = fopen("/home/content/b/a/b/babydb/html/babydb/app/etc/z_contacthistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";
			
			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;
			
			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			// Blank row
			$chRow = array();
			$noLab = FALSE;
			$blankLab = FALSE;
			
			// Set baby_id
			if (empty($data[1]) === false) {
				$chRow["baby_id"] = (int) $data[1];
			} else {
				echo "ERROR: baby_id field is blank, skipping this entry #{$rowNum}";
				continue;
			}

			// Fix/set date
			$date = date("Y-m-d", strtotime($data[2]));
			$chRow["DATETIME"] = $date;

			// Get lab + researcher id
			$lId = "";
			$rId = "";
			if (empty($data[5]) === false) {
				$lab = strtolower($data[5]);

				// Find researcher id + lab id
				$where = $researcherTbl->getAdapter()->quoteInto("researcher LIKE ?", $lab );
				$row = $researcherTbl->fetchRow($where);

				if ($row == null) {
					echo "ERROR: lab '$lab' not found in researcher table for this entry #{$rowNum}";
					echo "<br>";
					$noLab = TRUE;
				} else {
					$lId = $row->lab_id;
					$rId = $row->id;

					$chRow["researcher_id"] = $rId;
				}
			} else {
				$blankLab = TRUE;
			}

			// Get caller_id
			if (empty($data[3]) === false) {
				$caller = $data[3];
				if (stripos($caller, "mom") !== false) {
					$chRow["contact_type_id"] = 2;
				} elseif (stripos($caller, "mother") !== false) {
					$chRow["contact_type_id"] = 2;
				} elseif (stripos($caller, "dad") !== false) {
					$chRow["contact_type_id"] = 2;
				} elseif (stripos($caller, "father") !== false) {
					$chRow["contact_type_id"] = 2;
				} else {
					$where = $callerTbl->getAdapter()->quoteInto("name LIKE ?", $caller);
					$row = $callerTbl->fetchRow($where);

					if ($row) {
						$cId = $row->id;
						$chRow["caller_id"] = $cId;
					}
					elseif ($noLab == false) {
						$db->beginTransaction();
						try {
							if ($blankLab)
								$dataTbl = array(
									"name" 		=> $caller,
									"lab_id"	=> 1
								);
							else
								$dataTbl = array(
									"name"		=> $caller,
									"lab_id"	=> $lId
								);
							$cId = $callerTbl->insert($dataTbl);
							// Done
							$db->commit();
						} catch (Exception $e) {
							echo $chRow["baby_id"] . ": " . $e . " <br />";
							$db->rollback();
						}
						$chRow["caller_id"] = $cId;
					}
				}
			}

			// Get study_id
			if (empty($data[4]) === false) {
				try {
					$study = strtolower($data[4]);
					$where = $studyTbl->getAdapter()->quoteInto("study LIKE ?", $study);
					$row = $studyTbl->fetchRow($where);
				} catch(Exception $e) {
					echo "ERROR: entry #{$rowNum}<br />";
					echo $chRow["baby_id"] . ": " . $e . " <br />";
					continue;
				}

				if ($row) {
					$sId = $row->id;
					if ($noLab or $blankLab)
						$rId = $row->researcher_id;
					elseif ($row->researcher_id == 1) {
						$studyTbl->update(array("researcher_id" => $rId), "id = $row->id");
					}
					$chRow["study_id"] = $sId;
				} elseif($noLab == false) {
					echo "ERROR: study '$study' not found in database in row #{$rowNum}";
					echo "<br>";
					
					$db->beginTransaction();
					try {
						$dataTbl = array(
							"date_of_entry"	=> $date,
							"study"			=> ucwords($study)
						);
						if ($blankLab == false)
							$dataTbl["researcher_id"] = $rId;
						else
							$dataTbl["researcher_id"] = 1;
						$sId = $studyTbl->insert($dataTbl);
						$chRow["study_id"] = $sId;
						// Done
						$db->commit();
					} catch (Exception $e) {
						echo $chRow["baby_id"] . ": " . $e . " <br />";
						$db->rollback();
					}
				}
			}

			// Set comments
			if (empty($data[6]) === false) {
				if(stripos($data[6], "entered") === false) {
					$chRow["comments"] = $data[6];
				} else {
					$db->beginTransaction();
					try {
						$where = $babyTbl->getAdapter()->quoteInto("id = ?", $chRow["baby_id"]);
						$babyTbl->update(array("date_of_entry" => $date), $where);
						$db->commit();
						continue;
					} catch (Exception $e) {
						echo $chRow["baby_id"] . ": " . $e . " <br />";
						$db->rollback();
					}
				}
			}

			if(empty($chRow)) {
				echo "ERROR: row empty for entry #{$rowNum}";
				echo "<br>";
			} else {
				#var_dump($data);
				#echo "<br>";
				#var_dump($chRow);
				#echo "<br><br>";
				#continue;
				
				$db->beginTransaction();
				try {
					// Set attempt number
					$query = "SELECT MAX(attempt) FROM contact_histories WHERE baby_id = ?";
					$stmt = $db->query($query, array($chRow["baby_id"]));
					$stmt->execute();
					$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
					$chRow["attempt"] = ($result[0]) ? $result[0]+1 : 1 ;
					
					if(empty($chRow["caller_id"]))
						$chRow["caller_id"] = 4;

					// Insert baby row
					var_dump($chRow);
					echo "<br>";
					$chTbl->insert($chRow);

					// Done
					$db->commit();
				} catch (Exception $e) {
					echo $chRow["baby_id"] . ": " . $e . " <br />";
					$db->rollback();
				}
			}
			
		}
		
		fclose($handle);
		
		exit();
	}
	
	function addStudyAction()
	{
		set_time_limit(300);
	
		$study = new Study();
		$db = Zend_Registry::get('db');
		
		$row = 1;
		$handle = fopen("/home/content/b/a/b/babydb/html/babydb/app/etc/z_studies.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $row: <br /></p>\n";

			// Skip header
			$row++;
			if ($row == 2)
				continue;
			
			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}
			
			if(empty($data[0]))
				continue;
			
			$sRow = array(
				"date_of_entry" => date("Y-m-d", strtotime($data[3])),
				"study"			=> $data[0],
				"researcher_id"	=> 1
			);
			
			#var_dump($data);
			#echo "<br>";
			#var_dump($sRow);
			#echo "<br><br>";
			#continue;
			
			$db->beginTransaction();
			try {
			
				// Insert baby row
				$study->insert($sRow);
				// Done
				$db->commit();
			} catch (Exception $e) {
				echo $sRow["id"] . ": " . $e . " <br />";
				$db->rollback();
			}
		}
		
		fclose($handle);
		
		exit();
	}
	
	function addBabyAction()
	{
		set_time_limit(300);
	
		// Baby Table
		$baby = new Baby();
		// Family Table
		$family = new Family();
		$db = Zend_Registry::get('db');
		
		
		$row = 1;
		$handle = fopen("/home/content/b/a/b/babydb/html/babydb/app/etc/z_child.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $row: <br /></p>\n";

			// Skip header
			$row++;
			if ($row == 2)
				continue;
			
			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			$sex = strtolower($data[5]);
			$sex = substr($sex, 0, 1);

			$brow = array(
				"id" 			=> $data[1],
				"family_id"		=> $data[0],
				"last_name"		=> $data[2],
				"first_name"	=> $data[3],
				"sex"			=> ($sex == "m") ? 2 : 1 ,
				"dob"			=> date("Y-m-d", strtotime($data[11])),
				"status_id"		=> 1,
				"comments"		=> ""
			);

			// Add middle name $data[4] to comments
			if (empty($data[4]) === false)
				$brow["comments"] .= "middle name: " . $data[4] . PHP_EOL . PHP_EOL;

			// Add siblings to comments $data[15] + $data[16]
			if (empty($data[15]) === false)
				$brow["comments"] .= "sibling: " . $data[15] . PHP_EOL . PHP_EOL;
			if (empty($data[16]) === false)
				$brow["comments"] .= "sibling dob: " . $data[16] . PHP_EOL . PHP_EOL;

			// Add $data[18] to family how_heard (if not null)
			$frow = array();
			if (empty($data[18]) === false) {	
				if (stripos($data[18], "tisch") !== false)
					$frow["contact_source_id"] = 2;
				elseif (stripos($data[18], "bellevue") !== false)
					$frow["contact_source_id"] = 1;
				else
					$frow["how_heard"] = $data[18];
			}

			// term if yes or full term then add otherwise to comments
			if (empty($data[22]) === false) {
				$brow["comments"] .= "term info: " . $data[22] . PHP_EOL . PHP_EOL;
				$term = strtolower($data[22]);

				// If yes
				if (strpos($term, "yes") !== false)
					$brow["term"] = 40;
				elseif (strpos($term, "full") !== false)
					$brow["term"] = 40;
				else {
					$termLen = strlen($term);
					$termNum = (float) $term;

					if (strpos($term, "weeks") !== false) {
						$termLen2 = strlen($termNum) + 6;
						if ($termLen == $termLen2)
							$brow["term"] = $termNum;
					} elseif (strpos($term, "wks") !== false) {
						$termLen2 = strlen($termNum) + 4;
						if ($termLen == $termLen2)
							$brow["term"] = $termNum;
					}
				}
			}

			if (empty($data[28]) === false)
				$brow["comments"] .= "ethnicity: " . $data[28] . PHP_EOL . PHP_EOL;

			if(empty($brow["id"]) === false) {
				#var_dump($data);
				#echo "<br>";
				#var_dump($brow);
				#echo "<br>";
				#var_dump($frow);
				#echo "<br><br>";
				#continue;
			
				$db->beginTransaction();
				try {
					// Insert baby row
					$baby->insert($brow);
					// Update family
					if(empty($frow) === false) {
						$where = $family->getAdapter()->quoteInto('id = ?', $data[0]);
						$family->update($frow, $where);
					}
					// Done
					$db->commit();
				} catch (Exception $e) {
					echo $brow["id"] . ": " . $e . " <br />";
					$db->rollback();
				}
			}
		}
		
		fclose($handle);
		
		exit();
	}
	
	function addFamilyAction()
	{
		set_time_limit(300);
	
		// Family Table
		$family = new Family();
		// Family Email Table
		$femail = new FamilyEmail();
		// Family Owner Table
		$fphone = new FamilyPhone();
	
		$conv = array(
			0 => "id",
			1 => "father_last_name", # only if 3 blank
			2 => "",
			3 => "father_last_name",
			4 => "father_first_name",
			5 => "mother_first_name",
			6 => "mother_last_name",
			7 => 1,	# family_phones: phone_number => ..., family_id => [0], family_setting_id => 1
			8 => 3, # family_phones: phone_number => ..., family_id => [0], family_setting_id => 3
			9 => "address_1",
			10 => "city",
			11 => "state",
			12 => "zip",
			13 => "zip_plus",
			14 => "",
			15 => "date_of_entry", # only if not empty
			16 => "comments", # add 'calling status: ' and then line break at end
			17 => "",
			18 => "comments", # add 'languages: ' and then line break at end
			19 => "",
			20 => "",
			21 => "",
			22 => 2, # family_phones: phone_number => ..., family_id => [0], family_setting_id => 2
			23 => "email", # family_emails: email => ..., family_id => [0]
			24 => ""
		);
		$db = Zend_Registry::get('db');

		$row = 1;
		$handle = fopen("/home/content/b/a/b/babydb/html/babydb/app/etc/z_family.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $row: <br /></p>\n";

			// Skip header
			$row++;
			if ($row == 2)
				continue;

			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
		    }

			# Create family row
			// Get basics
			$frow = array(
				"id" 				=> $data[0],
				"father_last_name"	=> ($data[3] == "" and $data[4] != "") ? $data[1] : $data[3] ,
				"father_first_name"	=> $data[4],
				"mother_first_name"	=> $data[5],
				"mother_last_name"	=> $data[6],
				"address_1"			=> $data[9],
				"city"				=> $data[10],
				"state"				=> $data[11],
				"zip"				=> $data[12],
				"zip_plus"			=> (empty($data[13])) ? NULL : $data[13],
				"last_update"		=> new Zend_Db_Expr('NOW()'),
				"comments"			=> ""
			);
			// Get date of entry
			if(empty($data[15]) === false)
				$frow["date_of_entry"] = date("Y-m-d", strtotime($data[15]));
			// Get comments
			if(empty($data[16]) === false)
				$frow["comments"] .= "CALLING STATUS: " . $data[16] . PHP_EOL . PHP_EOL;
			if(empty($data[18]) === false)
				$frow["comments"] .= "LANGUAGES: " . $data[18] . PHP_EOL . PHP_EOL;

			# Create family email row
			$ferow = array();
			if(empty($data[23]) === false) {
				$len = strpos($data[23], "#");
				$email = substr($data[23], 0, $len);
				$ferow = array(
					"family_id"	=> $data[0],
					"email"		=> $email
				);
			}

			# Create family phone rows
			$fprow = array();
			if(empty($data[7]) === false)
				$fprow[] = array(
					"family_id"			=> $data[0],
					"family_setting_id"	=> 1,
					"phone_number"		=> $data[7]
				);
			if(empty($data[8]) === false)
				$fprow[] = array(
					"family_id"			=> $data[0],
					"family_setting_id"	=> 3,
					"phone_number"		=> $data[8]
				);
			if(empty($data[22]) === false)
				$fprow[] = array(
					"family_id"			=> $data[0],
					"family_setting_id"	=> 2,
					"phone_number"		=> $data[22]
				);

			if (empty($frow["id"]) === false) {
				$db->beginTransaction();
				try {
					// Insert family row
					$family->insert($frow);
					// Insert email
					if(empty($ferow) === false) {
						$femail->insert($ferow);
					}
					// Insert phone
					if (empty($fprow) === false) {
						foreach ($fprow as $row)
							$fphone->insert($row);
					}
					// Done
					$db->commit();
				} catch (Exception $e) {
					echo $frow["id"] . ": " . $e . " <br />";
					$db->rollback();
				}
			}
			
			if ($row == 4)
				break;
		}
		fclose($handle);
		
		exit();
	}
*/
	
}

