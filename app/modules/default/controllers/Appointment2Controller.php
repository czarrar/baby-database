<?php


class Appointment2Controller extends Zend_Controller_Action 
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
	
	// record status
	protected $_recordStatus = array(
		'active'		=> array(4, 5),
		'inactive'		=> array(1, 6, 7, 8),
		'archived'		=> array(2),
		'semi-active'	=> array(3)
	);
	
	/**
	 * Form
	 *
	 * @var Zend_Form
	 **/
	protected $_form;
	
	/**
	 * Form Data from $this->_form->getValues() following form submission
	 *
	 * @var array
	 **/
	protected $_formData;

	/**
	 * Errors from form processing for user display
	 *
	 * @var array
	 **/
	protected $_errors = array();

	/**
	 * Db adapter
	 *
	 * @var Zend_Db
	 **/
	protected $_db;
	
	/**
	 * Select query
	 *
	 * @var Zend_Db_Select
	 **/
	protected $_select;
	

/************************
 * GENERAL FUNCTIONS	*
 ************************/
	
	function init()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
	}
	
	public function indexAction()
	{
		$this->_forward("schedule");
	}
	
	
/****************************
 * COMMON FORM FUNCTIONS	*
 ****************************/		
	
	/**
	 * Instantiates form element with some basic stuff
	 *
	 * @return obj Zend_Form
	 **/
	protected function _getForm()
	{
		$form = new Zend_Form();
		$form->setAction("")
			->setMethod("post");
		$form->addElementPrefixPath('Zarrar_Decorator', 'Zarrar/Decorator', 'decorator');
		$form->addElementPrefixPath('Zarrar_Validate', 'Zarrar/Validate', 'validate');
		
		return($form);
	}
	
	/**
	 * Prepares common form elements
	 * 	for scheduling, confirming, and outcome pages
	 * 
	 * Common elements are:
	 * 	study, records per page, and date of study fields
	 * 
	 * Default decorators: ViewHelper and Label
	 * Default filters: StringTrim, StripTags
	 * 
	 * NOTE: Date of Study field is not subject to the default decorators or filters
	 *
	 * @param array $elements Optional
	 * 	Additional elements to add before setting default decorators + filters
	 * @param array $elementDecorators Optional
	 * 	Additional decorators to set for all elements (added to defaults)
	 * @param array $elementFilters Optional
	 * 	Additional filters to set for all elements (added to defaults)
	 * @return Zend_Form
	 **/
	protected function _prepareForm(array $elements = array(), array $elementDecorators = array(), array $elementFilters = array(), $form = NULL)
	{	
		# GET FORM
		if (empty($form)) {
			$form = $this->_getForm();
		}
		
		#. Serial No.
		$babyId = $form->createElement("text", "baby_id");
		$babyId->setLabel("Serial No.")
				->setAllowEmpty(true)
				->addValidator("Digits");
				
		# Name
		$firstName = $form->createElement("text", "first_name");
		$firstName->setLabel("First Name")
				->setAllowEmpty(true)
				->setAttrib('size', 20)
				->setAttrib('maxlength', 100);
		$lastName = $form->createElement("text", "last_name");
		$lastName->setLabel("Last Name")
				->setAllowEmpty(true)
				->setAttrib('size', 20)
				->setAttrib('maxlength', 150);
				
		# 3. RECORDS PER PAGE
		// Set options
		$perPageOptions = array("1", "10", "25", "50", "100");
		// Create select field
		$perPage = $form->createElement("select", "per_page");
		$perPage->setLabel("Records per page")
				->setValue(50)
				->setMultiOptions(array_combine($perPageOptions, $perPageOptions));
				
		$offRestrict = $form->createElement("select", "off_date_search");
		$offRestrict->setLabel("Date Range")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					0 => "No",
					1 => "Yes"
				));
		
		# ADD ELEMENTS
		$addElements = array_merge(array($offRestrict, $babyId, $firstName, $lastName, $perPage), $elements);
		$form->addElements($addElements);
		
		# SET DECORATORS
		$setDecorators = array_merge(array('ViewHelper', 'Label'), $elementDecorators);
		$form->setElementDecorators($setDecorators);
		
		# SET FILTERS
		$setFilters = array_merge(array('StringTrim', 'StripTags'), $elementFilters);
		$form->setElementFilters($setFilters);
		
		# 4. DATE OF STUDY - 2 FIELDS
		// Specify year range (one year back and one year forward)
		$yearRange = array("years" => 1);
		// Beginning Date
		$beginDate = new Zarrar_Form_SubForm_Date($yearRange);
		$beginDate->setRequired(true, "Beginning date of study is required");
		$form->addSubForm($beginDate, 'begin_date');
		// Ending Date
		$endDate = new Zarrar_Form_SubForm_Date($yearRange);
		$endDate->setRequired(true, "Ending date of study is required");
		$form->addSubForm($endDate, 'end_date');
		
		# SUBMIT
		$submit = new Zend_Form_Element_Submit("submit", "Get Records ->");
		$form->addElement($submit);
		
		// Make available
		$this->_form = $form;
	
		return $form;
	}
	
	/**
	 * Processes form if something was posted (assumes data has been posted)
	 * 
	 * Assumes form is kept in $this->_form
	 * Resets $this->_errors and puts any new errors into $this->_errors
	 *
	 * @param array $defaults Default value for form (when form is new)
	 * @return boolean
	 * 	FALSE if new form or form submitted but bad data, TRUE if form submitted and all is good
	 **/
	protected function _processForm($defaults = NULL)
	{	
		# 1. SOMETHING SUBMITTED! SHIZNIT!
		if ($this->getRequest()->isPost()) {	
			// Get data
			$formData = $this->getRequest()->getPost();
		
			// Reset errors
			$this->_errors = array();

			// Check for validity
			$isValid = $this->_form->isValid($formData);
			
			// Save values
			$this->_formData = $this->_form->getValues();
			
			// Save errors, else all good
			if (!$isValid)
				$this->_errors = array_merge($this->_errors, $this->_form->getMessages());
			else
				return TRUE; // form is good
		}
		# 2. SET DEFAULTS (if new form)
		elseif (!empty($defaults)) {
			$this->_form->populate($defaults);
		}
	
		# NEW FORM OR BAD FORM
		return False;
	}

	
/****************************
 *	COMMON ACTION FUNCTIONS	*
 ****************************/

	/**
	 * Prepares search page for display
	 * 	e.g. put things into view
	 *
	 * @return void
	 **/
	protected function _prepareAction()
	{
		// Save vars into view (form and errors)
		$this->view->form = $this->_form;
		$this->view->errors = $this->_errors;
	}


/****************************
 *	COMMON SEARCH FUNCTIONS	*
 ****************************/

	/**
	 * Prepares $this->_select with the common things in life
	 *
	 * @param boolean $distinct Default is TRUE
	 * @return boolean|Zend_Db_Select Will return false if there are errors in form submission
	 **/
	protected function _prepareQuery($distinct=true)
	{
		if (!empty($this->_errors))
			return False;

		// Get db adapter
		$db = Zend_Registry::get('db');
		$this->_db = $db;

		// Instantiate select query
		$select = $this->_db->select()
	
		// Want distinct rows
			->distinct($distinct)
		
		// Group by baby id and study id (in case baby in 2+ studies)
			->group("b.id")
			->group("bs.study_id")
	
		// BASE TABLE: babies
			->from(array('b' => 'babies'),
	        	array('id' => 'id', 'last_name', 'first_name', 'dob', 'sex'))
	
		// Get baby languages spoken + percent
		// Get through
		//	a) concatanating language and percent per week into one field
		//	b) group concatanation of multiple languages that one baby is exposed to
		//	c) add group by id clause so each row is only one baby
		    ->joinLeft(array('bl' => 'baby_languages'),
		        'b.id = bl.baby_id', array())
			->joinLeft(array('l' => 'languages'),
				'bl.language_id = l.id', array("language" => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT CONCAT_WS(":", l.language, bl.percent_per_week))')))
	
		// TABLE: families
			->joinLeft(array('f' => 'families'),
				'b.family_id = f.id',
				array('family_id' => 'id', 'mother_first_name', 'mother_last_name', 'father_first_name', 'father_last_name'))
			
		// TABLE: phone numbers
		//	a) group concatanation of multiple phones of one family
		//	b) add group by id clause so each row is only one baby
			->joinLeft(array('fp' => 'family_phones'),
				'f.id = fp.family_id', array('telephone' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT fp.phone_number SEPARATOR ', ')")))
		
		// INFO/TABLE: status (related to babies table)
			->joinLeft(array('sta' => 'statuses'),
				'b.status_id = sta.id', array('scheduling_status' => 'status', 'record_status' => 'group'))
			
		// Get study (researcher : study)
			->joinLeft(array('bs' => 'baby_studies'),
				'b.id = bs.baby_id', array("study_id", "appointment"))
			->joinLeft(array('s' => "studies"),
				"bs.study_id = s.id", array())
			->joinLeft(array('r' => 'researchers'),
				's.researcher_id = r.id', array("study" => new Zend_Db_Expr('CONCAT(r.researcher, " : ", s.study)')))
		
		// Get callers associated with a study
			->joinLeft(array("c" => "callers"), "bs.caller_id = c.id", array("caller" => "name"));
		
		
		// Get data (in case have to restrict for baby with given serial no)
		$data = $this->_formData;
		if ($data["baby_id"])
			$select->where("b.id = ?", $data["baby_id"]);
				
		// If not admin or coordinater, then restrict 'ALL' search to just lab members
		if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator"))
		{
			if (empty($data["show_active"])) {
				// Join caller table to restrict display to caller's associated lab
				$select->joinLeft(array("c2" => "callers"), "r.lab_id = c2.lab_id", array())
					// Get only this specific caller
					->where("c2.id = " . $this->_db->quote($_SESSION['caller_id']) . " OR c2.id IS NULL");	
			}
		}
		
		// Restrict based on first and last name
		# Baby Name
		if (!empty($data['first_name'])) {
			$select->where("b.first_name LIKE ?", "%{$data['first_name']}%");
		}
		if (!empty($data['last_name'])) {
			$select->where("b.last_name LIKE ?", "%{$data['last_name']}%");
		}
		
		// Save
		$this->_select = $select;

		return $select;
	}
	
	/**
	 * Common Query to both confirm and outcome searches
	 * 	- narrow search based on selected study or researcher
	 * 	- narrow to appointment in desired period
	 *
	 * @return Zend_Db_Select
	 **/
	protected function _commonConfirmOutcomeQuery()
	{
		// Get select
		$select = $this->_select;
		
		// Get data
		$data = $this->_formData;
	
		// Only want show appointments for a specific study
		if ($data["study_id"])
			$select->where("s.id = ?", $data["study_id"]);
			
		// Only want show appointments for a specific researcher		
		if ($data["researcher_id"])
			$select->where("r.id = ?", $data["researcher_id"]);
		
		if ($data["off_date_search"] != 1) {
			// Only want show appointments during a desired period
			$hackEndDate = "{$data['end_date']} 23:59";
			if (!empty($data["begin_date"]) and !empty($data["end_date"])) {
				$select->where(
					"(bs.appointment
						BETWEEN
							{$this->_db->quote($data['begin_date'])}
						AND
							{$this->_db->quote($hackEndDate)})"
				);
			}
			elseif (!empty($data["begin_date"])) {
				$select->where(
					"bs.appointment LIKE ?", $data["begin_date"]
				);
			}
		}
		
		// Save select
		$this->_select = $select;
		
		return $select;
	}

	/**
	 * Process search query, if all good forwards to search results page
	 *
	 * @param string $type Type of page (e.g. 'family', 'baby')
	 * 	This will be used to set session namespace and listType for search results
	 * 	Default is 'search'
	 * @return boolean
	 * 	Will return false if there are errors from search or if there are errors from form submission
	 * 	Otherwise it will just forward to the search results page
	 **/
	protected function _processQuery($type, $defaultParams = array(), $forward = "list", $countFrom = "b.id")
	{
		if (!empty($this->_errors))
			return False;

		try {
			$select = $this->_select;

			// Save query
			$query = $select->__toString();
			
			// Get count of rows
			$select->reset(Zend_Db_Select::GROUP);
			$select->reset(Zend_Db_Select::COLUMNS);
			$select->from(null, "COUNT(DISTINCT $countFrom) AS count");
			$stmt = $select->query();
			$count = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
			$count = $count[0];

			// Send error if count is below 1
			if ($count < 1)
				throw new Exception("Search did not return any records!");

			// Have session with listType, rowCount, query, perPage
			$session = new Zend_Session_Namespace($type);
			$session->type = $type;
			$session->query = $query;
			$session->count = $count;
			$session->params = $defaultParams;
			$session->studyId = $this->_formData["study_id"];
			#@todo: $session->perPage = $this->_form->perPage->getValue();

			// Send query, etc to list action
			$params = array_merge(array("type" => $type), $defaultParams);
			$this->_forward($forward, null, null, $params);
		} catch(Exception $e) {
			// Crap, search did not work!
			$this->_errors["ERROR"] = array("info" => $e->getMessage());
			return False;
		}
	}
	
	
	
/********************************************
 * BABY-STUDY SCHEDULING SEARCH FUNCTIONS	*
 ********************************************/


	//public function scheduleAction()
	//{
	//	# 1. FORM
	//	// Prepare
	//	$form = $this->_getForm();
	//	// Get study options
	//	$studyTbl = new Study();
	//	$studyOptions = $studyTbl->getRecordOwners("long", false, array("" => "None"));
	//	// Create select field
	//	$study = $form->createElement("select", "study_id");
	//	$study->setLabel("Study")
	//			->setRequired(false)
	//			->setMultiOptions($studyOptions);
	//	$form->addElement($study);
	//	// Submit field
	//	$submit = new Zend_Form_Element_Submit("submit", "Start Search");
	//	$form->addElement($submit);
	//	// Make form available
	//	$this->_form = $form;
	//	// Process
	//	$formGood = $this->_processForm();
	//	
	//	# 2. SEARCH
	//	if ($formGood) {
	//		$this->_redirector = $this->_helper->getHelper('Redirector');
	//		$this->_redirector->goto("schedule2", null, null, array("study_id" => $this->_formData["study_id"]));
	//	}
	//	
	//	# 3. PREPARE VIEW
	//	$this->_prepareAction();
	//}

	/**
	 * Search babies to schedule them
	 **/
	public function scheduleAction()
	{
		# Get study id (from study select field)
		$studyId = $this->_getParam("study_id");
		#if(empty($studyId))
		#	throw new Zend_Controller_Action_Exception("You must specify a study id for the second step of schedule search!");
		
		
		# 1. FORM
	    // Prepare
	    $this->_prepareScheduleForm($studyId);
	    
	    // Set default study date
		$defaults = array(
			"study_id"		=> $studyId,
			"begin_date"	=> date('Y-m-d', strtotime("+1 day")),
			"end_date"		=> date('Y-m-d', strtotime("+1 week +1 day"))
		);
		
		// Process
		$formGood = $this->_processForm($defaults);

		# 2. SEARCH
		if ($formGood) {
		    try {
    			// Prepare 
    			$studyRow = $this->_prepareScheduleQuery();
    			// Want to know study range for list
    			// Actual lower age range used
    			if (empty($studyRow)) {
    			    $params['lower_age'] = '**NO LOWER AGE**';
    			} elseif(!empty($this->_formData['lower_age'])) {
    				$params['lower_age'] = $this->_formData['lower_age'];
    				$params['study_lower_age'] = $studyRow->lower_age;
    			} else {
    				$params['lower_age'] = $studyRow->lower_age;
    			}
    			// Actual upper age range used
    			if (empty($studyRow)) {
    			    $params['upper_age'] = '**NO UPPER AGE**';
    			} elseif(!empty($this->_formData['upper_age'])) {
    				$params['upper_age'] = $this->_formData['upper_age'];
    				$params['study_upper_age'] = $studyRow->upper_age;
    			} else {
    				$params['upper_age'] = $studyRow->upper_age;
    			}
			    // Process
    			$this->_processQuery("schedule", $params);
			} catch(Exception $e) {
    			// Crap, search did not work!
    			$this->_errors["ERROR"] = array("info" => $e->getMessage());
    		}
		}

		# 3. PREPARE VIEW
		$this->_prepareAction();
	}
	
	/**
	 * Setup form for scheduling search
	 *
	 * @return Zend_Form
	 **/
	protected function _prepareScheduleForm($studyId)
	{
	
		# FIELDS: study, researcher, date_of_study, callback, records_per_page
		
		$form = $this->_getForm();
		
		# Study
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("long", false, array("" => "None"));		
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(false)
				->setMultiOptions($studyOptions);
		$form->addElement($study);
		
		# SET NON-COMMON ELEMENTS
		
		# CALLBACK
		// Set options
		$temp = array("All", "AM", "PM", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sept", "Oct", "Nov", "Dec");
		$callbackOptions = array_combine(array("") + $temp, array("None") + $temp);
		// Create select field
		$callback = $form->createElement("select", "callback");
		$callback->setLabel("Callback Best Times")
				->setMultiOptions($callbackOptions);
		
		# SPECIFIC SCHEDULING STATUS
		// Set options
		$statusTbl = new Status();
		$forSelect = array(
			"columns" 	=> array("id", "status"),
			"where"		=> array("`group` = 'inactive' OR `group` = 'semi-active'")
		);
		$scheduleOptions = $statusTbl->getSelectOptions($forSelect, null, array("" => "Set Scheduling Status"));
		// Create select field
		$scheduleStatus = $form->createElement("select", "schedule_status");
		$scheduleStatus->setMultiOptions($scheduleOptions);
		
		# SEX
		// Add all, odd, even (select)
		$sex = $form->createElement("select", "sex");
		$sex->setLabel("Sex")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					"" => "All",
					1  => "Female",
					2  => "Male"
				));
        
		# List ID
		// Set options
		$listTbl = new RList();
		$listOptions = $listTbl->getSelectOptions(null, null, array("" => "*Auto*", "-1" => "*All*"));
		// Create select field
		$listId = $form->createElement("select", "list_id");
		$listId->setLabel("List")
		       ->setAllowEmpty(true)
		       ->setMultiOptions($listOptions);


		# Advanced Options
		
		# Ignore list id
		$ignoreList = $form->createElement("select", "ignore_list");
		$ignoreList->setLabel("List")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					0 => "No",
					1 => "Yes"
				));
		
		# Show babies who are currently participating in a study
		$showActive = $form->createElement("select", "show_active");
		$showActive->setLabel("Show Active Babies")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					0 => "No",
					1 => "Yes"
				));
		
		# Age
		$yearRange = array("years" => array(0,18));
		// Lower Age
		$lowerAge = new Zarrar_Form_SubForm_Age($yearRange);
		$form->addSubForm($lowerAge, 'lower_age');
		// Upper Age
		$upperAge = new Zarrar_Form_SubForm_Age($yearRange);
		$form->addSubForm($upperAge, 'upper_age');
		
		// If study id is set, find the actual study age range
		if(!empty($studyId)) {
			// Get study row with relevant info
			$studyTbl = new Study();
			$studySelect = $studyTbl->select()->where("id = ?", $studyId);
			$studyRow = $studyTbl->fetchRow($studySelect);
			// Get lower and upper age range
			$form->populate(array('lower_age' => $studyRow->lower_age));
			$form->populate(array('upper_age' => $studyRow->upper_age));
			// Set study name to view
			$this->view->studyName = $studyRow->study;
		}
		
		// Add age subforms
		

		# Languages (2 of them)
		$languageFields = array();
		for ($i=1; $i < 3; $i++) {
			// set number
			$langNum = "language" . $i;

			// Set language
			$languageTbl = new Language();
			$languageOptions = $languageTbl->getSelectOptions(null, "language");
			$language = $form->createElement("select", $langNum);
			$language->setLabel("Language")
				->setMultiOptions($languageOptions);
			// Set degree/rate field
			$rate = $form->createElement("text", $langNum . "_rate");
			$rate->setLabel("Degree")
				->setAllowEmpty(true) 
				->addValidator("Digits")
				->setAttrib("size", 4)
				->setAttrib("maxlength", 3);
			// Set more or less field
			$amount = $form->createElement("select", $langNum . "_amount");
			$amount->setAllowEmpty(true)
				->setMultiOptions(array(
					'more' 	=> "or more",
					'less'	=> "or less"
				));

			// Add array values
			$languageFields[] = $language;
			$languageFields[] = $rate;
			$languageFields[] = $amount;
		}

		# Previous Study
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$prevStudy = $form->createElement("select", "previous_study");
		$prevStudy->setLabel("Previous Study")
				->setMultiOptions($studyOptions);

		# GET FORM (add non-common elements)
		$formFields = array_merge(array($study, $callback, $scheduleStatus, $sex, $prevStudy, $lowerAge, $upperAge, $showActive, $listId, $ignoreList), $languageFields);
		$form = $this->_prepareForm($formFields, array(), array(), $form);

		# Update study attributes to allow dynamic upload of page for study ages
		$baseUrl = $this->view->url(array('action' => 'schedule', 'controller' => 'appointment2'), null, true);
		$form->study_id->setAttrib("onChange", "updateAge('{$baseUrl}')");
		$form->study_id->setValue($studyId);

		// Save
		$this->_form = $form;

		return($form);
	}
	
	/**
	 * Add to base query from _prepareSearch
	 * 	adds parameters specific to scheduling
	 * 	add where commands
	 * 
	 * Unlike the others, will return a study table row
	 *
	 * @return Zend_Db_Table_Row
	 **/
	protected function _prepareScheduleQuery($distinct = True)
	{
		// Check + prepare query at same time
		if (empty($this->_select) and !$this->_prepareQuery($distinct))
			return False;

		// Get select
		$select = $this->_select;

		// Get data
		$data = $this->_formData;
        
		// Join study history table
		$select->joinLeft(array('sh' => 'study_histories'),
			'b.id <=> sh.baby_id',
			array()
		);

		# ONLY WANT certain scheduling status entries
		// new, run, canceled, no show, or contacting
		if(empty($data["show_active"]))
			$select->where("sta.group <=> 'inactive' OR sta.group <=> 'semi-active'");
		# TODO: else -> add restriction preventing showing baby that is scheduled for the current study -> so joinLeft and where

		# Do not want babies having done current study
		if (!empty($data['study_id']))
    		$select->where("
    			sh.study_id IS NULL"
    			. " OR "
    			. $this->_db->quoteInto("sh.study_id != ?", $data['study_id'])
    			. " OR ("
    			. $this->_db->quoteInto("sh.study_id = ?", $data['study_id']) . " AND " . $this->_db->quoteInto("sh.study_outcome_id != ?", self::OUTCOME_RUN) . ")"
    			);
        
		# STUDY/DATE-OF-STUDY

		/* Get study details */
		if (!empty($data['study_id'])) {
		    $studyTbl = new Study();
    		$studySelect = $studyTbl->select()->where("id = ?", $data["study_id"]);
    		$studyRow = $studyTbl->fetchRow($studySelect);
		} else {
		    $studyRow = NULL;
		}
		
		/* 1.1 restrict to labs based on list id or select specific one */
		if (empty($data['list_id'])) {
		    // Auto set the list ids to use
		    if (!empty($data['study_id'])) {
		        $listIds = $studyTbl->getListIds($data['study_id']);
		        foreach ($listIds as $key => $value)
		            $listIds[$key] = $this->_db->quote($value);
		        $listIds = implode(", ", $listIds);
		        $select->where("b.list_id IN ({$listIds})");
	        }
		} elseif ($data['list_id'] != -1) {
		    $select->where("b.list_id = ?", $data['list_id']);
		}
		

	 	/* 2. combined with date of study, get babies in specified age range */
		if ($data["off_date_search"] != 1) {
			# Get lower age
			if(!empty($data["lower_age"]))
				$lowerAge = $data["lower_age"];
			elseif (!empty($data['study_id']))
				$lowerAge = $studyRow->lower_age;
			# Get upper age
			if(!empty($data["upper_age"]))
				$upperAge = $data["upper_age"];
			elseif (!empty($data['study_id']))
				$upperAge = $studyRow->upper_age;
			
			if (empty($lowerAge) or empty($upperAge)) {
			    # Only if study is none, is this ok
			    if (!empty($data['study_id']))
			        throw new Zend_Controller_Action_Exception("This study does not have an age range specified or a custom one was not specific by user, please select yes to 'Ignore Date Range' if you don't want one specified.");
			} 
			elseif (!empty($data["begin_date"]) and !empty($data["end_date"])) {
				// Need a calculator
				$calculator = new Zarrar_AgeCalculator();

				// Get oldest babies (earliest dob)
				// Upper age - Lower age 
				// begin date - end date
				// begin date - lower age -> first range (compare)
				// begin date - upper age -> first range
				// end date - lower age -> second range
				// end date - upper age -> second range (compare)

				$calculator->setDate($data["begin_date"], "YYYY-MM-dd")
						   ->setAge($lowerAge);
				$lateDob = $calculator->calculateDob("YYYY-MM-dd");
				$lateTimestamp = $calculator->getDob("YYYY-MM-dd");

				// Get youngest babies (latest dob)
				$calculator->setDate($data["end_date"], "YYYY-MM-dd")
						   ->setAge($upperAge);
				$earlyDob = $calculator->calculateDob("YYYY-MM-dd");
				$earlyTimestamp = $calculator->getDob("YYYY-MM-dd");
	// heys			
				// Compare the two dob (must be: $earlyDob < $lateDob)
				if ($earlyTimestamp >= $lateTimestamp)
					throw new Zend_Controller_Action_Exception("Cannot find babies who will be in this age range ({$lowerAge} to {$upperAge}) because study dates ({$data['begin_date']} to {$data["end_date"]}) are too far apart");

				// Set between DOB search criteria
				$select->where("b.dob BETWEEN {$this->_db->quote($earlyDob)} AND {$this->_db->quote($lateDob)}");
			}
			else {
				throw new Zend_Controller_Action_Exception("Please specify either <b><i>start and end date for study</i></b> OR <b><i>no dates at all</i></b>.");
			}
		}

		# CALLBACK

		if (!empty($data["callback"])) {
			// Get contact history table (want laleft record)
			$select->joinLeft(array("ch" => "contact_histories"), "b.id = ch.baby_id", array())
				->where("ch.attempt = (SELECT MAX(attempt) FROM contact_histories WHERE b.id = baby_id)");

			// Get any baby who wants to be called back
			if ($data["callback"] == "All")
				$select->where("ch.contact_callback IS NOT NULL");
			// Get baby who wants to be called back at specific time
			else
				$select->where("ch.contact_callback = ?", $data["callback"]);
		}

		# SEX
		if (!empty($data['sex'])) {
			// Set the search criteria for babies sex
			$select->where("b.sex = ?", $data["sex"]);
		}

		# Baby Name
		if (!empty($data['first_name'])) {
			$select->where("b.first_name LIKE ?", "%{$data['first_name']}%");
		}
		if (!empty($data['last_name'])) {
			$select->where("b.last_name LIKE ?", "%{$data['last_name']}%");
		}

		# Languages
		$languages = array(
			"language1" => array(
				"language"	=> $data["language1"],
				"rate" 		=> $data["language1_rate"],
				"amount" 	=> $data["language1_amount"]
			),
			"language2" => array(
				"language"	=> $data["language2"],
				"rate" 		=> $data["language2_rate"],
				"amount" 	=> $data["language2_amount"]
			)
		);

		foreach ($languages as $language) {
			if(!empty($language["language"])) {
				// Look for babies with this language
				$select->where("bl.language_id = ?", $language['language']);
				// and this % exposure
				if (!empty($language["rate"])) {
					switch ($language['amount']) {
						case 'more':
							$select->where("bl.percent_per_week >= ?", $language['rate']);
							break;
						case 'less':
							$select->where("bl.percent_per_week <= ?", $language['rate']);
							break;
						default:
							# Do nothing
							break;
					}
				}
			}
		}

		# Previous Study
		if(!empty($data['previous_study'])) {
			$select->where(
				$this->_db->quoteInto("sh.study_id = ?", $data["previous_study"]) . 
				" AND " .
				$this->_db->quoteInto("sh.study_outcome_id = ?", self::OUTCOME_RUN)
			);
		}

		// Save
		$this->_select = $select;

		return $studyRow;
	}



/************************************************
 * BABY-STUDY *CONFIRM/CHANGE* SEARCH FUNCTIONS	*
 ************************************************/

	/**
	 * Search babies to confirm/change them
	 **/
	public function confirmAction()
	{
		# 1. FORM
		// Prepare
		$this->_prepareConfirmForm();
		// Set default study date
		$defaults = array(
			'begin_date'	=> date('Y-m-d', strtotime("+1 day")),
			'end_date'		=> date('Y-m-d', strtotime("+1 day"))
		);
		// Process
		$formGood = $this->_processForm($defaults);

		# 2. SEARCH
		if ($formGood) {
			// Prepare 
			$this->_prepareConfirmQuery();
			// Process
			$this->_processQuery("confirm");
		}

		# 3. PREPARE VIEW
		$this->_prepareAction();
	}

	/**
	 * Setup form for confirming/changing search
	 *
	 * @return Zend_Form
	 **/
	protected function _prepareConfirmForm()
	{
		# FIELDS: study, researcher, date_of_study, records_per_page

		$form = $this->_getForm();
		
		# 1. STUDY FIELD
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("long", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(true)
				->setMultiOptions($studyOptions);

		# RESEARCHER FIELD
		// Get researcher options
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$researcher = $form->createElement("select", "researcher_id");
		$researcher->setLabel("Researcher")
					->setMultiOptions($researcherOptions);
					
		# Show Confirmed
		$showConfirmed = $form->createElement("select", "show_confirmed");
		$showConfirmed->setLabel("Show Confirmed Participants")
				->setAllowEmpty(true)
				->setMultiOptions(array(
					0 => "No",
					1  => "Yes"
				));

		// Get form
		$form = $this->_prepareForm(array($study, $researcher, $showConfirmed));

		// Change study to not be required
		$form->getElement("study_id")->setRequired(false);

		// Save
		$this->_form = $form;

		return($form);
	}

	/**
	 * Only look for specific statuses when confirming a baby
	 *
	 * @return Zend_Db_Select Can also return false if errors
	 **/
	protected function _prepareConfirmQuery()
	{
		// Check + prepare query at same time
		if (empty($this->_select) and !$this->_prepareQuery($distinct))
			return False;
			
		// Load form data
		$data = $this->_formData;

		// Common stuff
		$select = $this->_commonConfirmOutcomeQuery();

		if ($data["show_confirmed"] == 1) {
			// Want both babies that have been scheduled and those that have been confirmed
			$select->where("sta.group = ?", "active");
		} else {
			// Only want babies that have been scheduled
			$select->where("b.status_id = ?", self::SCHEDULED);
		}

		// Save
		$this->_select = $select;

		return $select;
	}


/********************************************
 * BABY-STUDY *OUTCOME* SEARCH FUNCTIONS	*
 ********************************************/

	/**
	 * Search scheduled babies to given study outcome
	 **/
	public function outcomeAction()
	{
		# 1. FORM
		// Prepare
		$this->_prepareOutcomeForm();
		// Set default study date
		$defaults = array(
			'begin_date'	=> date('Y-m-d'),
			'end_date'		=> date('Y-m-d')
		);
		// Process
		$formGood = $this->_processForm($defaults);

		# 2. SEARCH
		if ($formGood) {
			// Prepare 
			$this->_prepareOutcomeQuery();
			// Process
			$this->_processQuery("outcome");
		}

		# 3. PREPARE VIEW
		$this->_prepareAction();
	}

	/**
	 * Setup form for outcome of study
	 *
	 * @return Zend_Form
	 **/
	protected function _prepareOutcomeForm()
	{
		# FIELDS: study, researcher, appointment, callback, records_per_page

		$form = $this->_getForm();
		
		# 1. STUDY FIELD
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("long", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Study")
				->setRequired(true)
				->setMultiOptions($studyOptions);

		# RESEARCHER FIELD
		// Get researcher options
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$researcher = $form->createElement("select", "researcher_id");
		$researcher->setLabel("Researcher")
					->setMultiOptions($researcherOptions);

		$form = $this->_prepareForm(array($study, $researcher));

		// Change study to not be required
		$form->getElement("study_id")->setRequired(false);

		// Save
		$this->_form = $form;

		return($form);
	}

	/**
	 * Only look for specific statuses when giving outcome of a baby
	 *
	 * @return Zend_Db_Select
	 **/
	protected function _prepareOutcomeQuery ()
	{
		// Check + prepare query at same time
		if (empty($this->_select) and !$this->_prepareQuery($distinct))
			return False;

		// Common stuff
		$select = $this->_commonConfirmOutcomeQuery();

		// Only want babies that have been scheduled or confirmed
		$select->where("sta.group = ?", "active");

		// Save
		$this->_select = $select;

		return $select;
	}



/************************
 * LIST SEARCH RESULTS	*
 ************************/

	/**
	 * Lists search results
	 **/
	public function listAction()
	{
		// Get type of page
		$type = $this->_getParam("type");
		if (empty($type))
			throw new Zend_Controller_Action_Exception("Need to specify type of page (e.g. schedule, confirm, outcome), see administrator.");

		// Set columns to sort
		$urlFields = array("id", "last_name", "first_name", "dob", "sex", "appointment", "mother_first_name", "mother_last_name", "father_last_name", "father_first_name", "telephone", "study", "record_status", "scheduling_status", "language");

		// Pager action
		$this->_helper->Pager($type, $urlFields);

		// Set the study id to view
		$session = new Zend_Session_Namespace($type);
		$this->view->studyId = $session->studyId;

		// Set type to view
		$this->view->type = $type;

		// If schedule, set age range
		if ($type == "schedule") {
			$this->view->lowerAge = $session->params['lower_age'];
			$this->view->upperAge = $session->params['upper_age'];
			$this->view->studyLowerAge = $session->params['study_lower_age'];
			$this->view->studyUpperAge = $session->params['study_upper_age'];
		}
	}

}
