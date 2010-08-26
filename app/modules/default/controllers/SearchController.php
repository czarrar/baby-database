<?php

/**
 * SEARCHES:
 * 	- babies
 * 	- families
 **/
class SearchController extends Zend_Controller_Action 
{

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
	
	
	function init()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
	}
	

/****************************
 *	COMMON FORM FUNCTIONS	*
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
	 * 	for baby and family pages
	 * 
	 * Common elements are:
	 * 	baby: id, first name, last name, sex, dob, in study
	 * 	parents: id, first name, last name, ethnicity
	 * 	contact: city, state, zip code, phone, email
	 * 	submit (decorator is viewhelper)
	 * 
	 * Default decorators: ViewHelper and Label
	 * Default filters: StringTrim, StripTags
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
	
		
		/**
		 * 1. BABY FIELDS:
		 * 	id, first name, last name, sex, dob, and study
		 **/
	
		# 1a. BABY ID
		$baby = $form->createElement("text", "baby_id");
		$baby->setLabel("Serial No")
			->addValidator("Digits")
			->setAttribs(array(
				"size" 		=> 8,
				"maxlength"	=> 8
			));
			
		# 1b. FIRST NAME
		$firstName = $form->createElement("text", "first_name");
		$firstName->setLabel("First Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 100
			));
	
		# 1c. LAST NAME
		$lastName = $form->createElement("text", "last_name");
		$lastName->setLabel("Last Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 150
			));
	
		# 1d. SEX
		$sexOptions = array(
			""	=> "Choose",
			1	=> "Female",
			2	=> "Male"
		);
		$sex = $form->createElement("select", "sex");
		$sex->setLabel("Gender")
			->setMultiOptions($sexOptions);
		
		# 1e. DOB
		// see below
		
		# 1f. STUDY ID
		// Get study options
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short", false, array("" => "Choose"));
		// Create select field
		$study = $form->createElement("select", "study_id");
		$study->setLabel("Was In Study")
				->setMultiOptions($studyOptions);
	
			
		/**
		 * 2. PARENT FIELDS:
		 * 	id, first name, last name, ethnicity
		 **/
	
		// Set ethnicity options
		$ethnicities = new Ethnicity();
		$ethnicityOptions = $ethnicities->getSelectOptions();
	
		# 2a. FAMILY ID
		$family = $form->createElement("text", "family_id");
		$family->setLabel("Family Id")
			->addValidator("Digits")
			->setAttribs(array(
				"size"		=> 8,
				"maxlength"	=> 8
			));
	
		# MOTHER INFO
		
		# 2b. FIRST NAME
		$motherFirstName = $form->createElement("text", "mother_first_name");
		$motherFirstName->setLabel("First Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 100
			));
	
		# 2c. LAST NAME
		$motherLastName = $form->createElement("text", "mother_last_name");
		$motherLastName->setLabel("Last Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 150
			));
	
		# 2d. ETHNICITY
		$motherEthnicity = $form->createElement("select", "mother_ethnicity");
		$motherEthnicity->setLabel("Ethnicity")
				  ->setMultiOptions($ethnicityOptions);
	
		# FATHER INFO
	
		# 2e. FIRST NAME
		$fatherFirstName = $form->createElement("text", "father_first_name");
		$fatherFirstName->setLabel("First Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 100
			));

		# 2f. LAST NAME
		$fatherLastName = $form->createElement("text", "father_last_name");
		$fatherLastName->setLabel("Last Name")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 150
			));

		# 2g. ETHNICITY
		$fatherEthnicity = $form->createElement("select", "father_ethnicity");
		$fatherEthnicity->setLabel("Ethnicity")
				  ->setMultiOptions($ethnicityOptions);
		
			
		/**
		 * 3. CONTACT FIELDS:
		 * 	city, state, zip code, phone, email
		 **/
	
		# 3a. CITY
		$city = $form->createElement("text", "city");
		$city->setLabel("City")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 150
			));
	
		# 3b. STATE
		$state = $form->createElement("text", "state");
		$state->setLabel("State")
			->setAttribs(array(
				"size"		=> 10,
				"maxlength"	=> 100
			));
	
		# 3c. ZIP CODE
		$zip = $form->createElement("text", "zip");
		$zip->setLabel("Zip Code")
			->addValidator("Digits")
			->setAttribs(array(
				"size"		=> 5,
				"maxlength"	=> 5
			));
		
		# 3d. PHONE
		$phone = $form->createElement("text", "phone");
		$phone->setLabel("Phone")
			  ->addFilter("Digits")
			  ->addValidator("StringLength", false, array(7, 10))
			  ->setAttribs(array(
				"size"		=> 15,
				"maxlength"	=> 14
			));
		
		# 3e. EMAIL
		$email = $form->createElement("text", "email");
		$email->setLabel("Email")
			->addValidator("EmailAddress")
			->setAttribs(array(
				"size"		=> 15,
				"maxlength"	=> 150
			));
	
	
		/**
		 * SETUP FORM
		 **/
	
		# ADD ELEMENTS
		$addElements = array_merge(array($baby, $firstName, $lastName, $sex, $study, $family, $motherFirstName, $motherLastName, $motherEthnicity, $fatherFirstName, $fatherLastName, $fatherEthnicity, $city, $state, $zip, $phone, $email), $elements);
		$form->addElements($addElements);

		# SET DECORATORS
		$setDecorators = array_merge(array('ViewHelper', 'Label'), $elementDecorators);
		$form->setElementDecorators($setDecorators);

		# SET FILTERS
		$setFilters = array_merge(array('StringTrim', 'StripTags'), $elementFilters);
		$form->setElementFilters($setFilters);
	
	
		/**
		* ADDITIONAL FIELDS
		**/
	
		# 1d. DATE OF BIRTH
		$yearRange = array("years" => array("1980", date('Y')));
		$dob = new Zarrar_Form_SubForm_Date($yearRange);
		$dob->setLabel("Date of birth");
		$form->addSubForm($dob, 'dob');
	
		# 4. SUBMIT
		$submit = new Zend_Form_Element_Submit("submit", "Search Records ->");
		$form->addElement($submit);
	
		// Make available
		$this->_form = $form;

		return $this->_form;
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

			// Check if valid and save errors if any
			if (!$this->_form->isValid($formData))
				$this->_errors = array_merge($this->_errors, $this->_form->getMessages());
			else
				return TRUE; // form is good
		}
		# 2. SET DEFAULTS (if new form)
		else if (!empty($defaults)) {
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
	protected function _prepareSearch($distinct=true)
	{
		if (!empty($this->_errors))
			return False;
	
		// Get db adapter
		$this->_db = Zend_Registry::get('db');
		
		// Instantiate select query
		$this->_select = $this->_db->select()->distinct($distinct);
		
		return $this->_select;
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
	protected function _processSearch($type = "results", $countFrom = "b.id")
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
			#@todo: $session->perPage = $this->_form->perPage->getValue();

			// Send query, etc to list action
			$this->_forward($type, null, null, array("type" => $type));
		} catch(Exception $e) {
			// Crap, search did not work!
			$this->_errors["ERROR"] = array("info" => $e->getMessage());
			return False;
		}
	}


/********************
 *	BABY FUNCTIONS	*
 ********************/
	
	/**
	 * Search baby records (ADVANCED)
	 **/
	public function babyAction()
	{
		# 1. FORM
		// Prepare
		$this->_prepareBabyForm();
		// Process
		$formGood = $this->_processForm();

		# 2. SEARCH
		if ($formGood) {
			// Prepare (defaults)
			$this->_prepareSearch();
			// Prepare (customizations)
			$this->_prepareFamilySearch();
			// Process
			$this->_processSearch("family-results", "f.id");
		}

		# 3. PREPARE VIEW
		$this->_prepareAction();
	}
	
	/**
	 * Prepare baby form
	 *
	 * @return Zend_Form
	 **/
	protected function _prepareBabyForm()
	{
		// Get form
		$form = $this->_getForm();
		
		/* ADDITIONAL FIELDS:
		 * 	checked out, scheduling status, record status
		 * 	record date (entry, updated)
		 * 	languages
		 * 	birth weight (lbs)
		 * 	term (# weeks)
		 * 	issues: medical, ear, aud/lang
		 * 	CURRENT STUDY: study, researcher, lab, appointment
		 * 	WAS IN STUDY: study, researcher, lab, appointment, outcome
		 **/
		
		# 1. CHECKED OUT
		$checkedOut = $form->createElement("checkbox", "checked_out");
		$checkedOut->setLabel("Checked Out");
		
		$statusTbl = new Status();
		# 2. SCHEDULING STATUS
		$scheduleOptions = $statusTbl->getSelectOptions(array("columns" => array("id", "status")));
		$scheduleStatus = $form->createElement("select", "schedule_status");
		$scheduleStatus->setMultiOptions($scheduleOptions);
		
		# 3. RECORD STATUS
		$recordOptions = $statusTbl->getSelectOptions(array("columns" => array("id", "group")));
		$recordStatus = $form->createElement("select", "record_status");
		$recordStatus->setMultiOptions($recordOptions);
		
		# 4. DATE OF ENTRY
		// see below
		
		# 5. LAST UPDATED
		// see below
		
		$this->_prepareForm();
	}


/************************
 *	FAMILY FUNCTIONS	*
 ************************/

	/**
	 * Search family records
	 **/
	public function familyAction()
	{
		# 1. FORM
		// Prepare
		$form = $this->_prepareForm();
		// Process
		$formGood = $this->_processForm();
		
		# 2. SEARCH
		if ($formGood) {
			// Prepare (defaults)
			$this->_prepareSearch();
			// Prepare (customizations)
			$this->_prepareFamilySearch();
			// Process
			$this->_processSearch("family-results", "f.id");
		}
		
		# 3. PREPARE VIEW
		$this->_prepareAction();
	}
	
	/**
	 * Prepares select query for family specific searching
	 * 	this includes form entry options
	 *
	 * @return boolean|Zend_Db_Select Will return false if there are errors in form submission
	 **/
	protected function _prepareFamilySearch()
	{
		if (!empty($this->_errors))
			return False;
		
		$select = $this->_select
					->group("f.id")
			
		# 1. FROM TABLE
			->from(array('f' => 'families'),
        	array('id' => 'id', 'mother_name' => new Zend_Db_Expr("CONCAT_WS(', ', mother_last_name, mother_first_name)"), 'father_name' => new Zend_Db_Expr("CONCAT_WS(', ', father_last_name, father_first_name)"), "address" => new Zend_Db_Expr("CONCAT_WS(',', address_1, address_2)"), "city", "state", "zip"))

		# 2. PHONE NUMBER
			->joinLeft(
				array('fp' => 'family_phones'),
				'f.id = fp.family_id',
				array('telephone' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT fp.phone_number SEPARATOR ', ')"))
			)
			
		# 3. EMAIL
			->joinLeft(
				array('fe' => 'family_emails'),
				'f.id = fe.family_id',
				array('emails' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT fe.email SEPARATOR ', ')"))
			)
		
		# 4. BABY IDS
			->joinLeft(
				array('b' => 'babies'),
				'f.id = b.family_id',
				array('baby_ids' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT('<a href=\"{$this->view->sUrl('edit', 'baby', null, true)}/baby_id/', b.id, '\" target=\"_blank\">', b.id, '</a>') SEPARATOR ', ')"))
			);


		/**
		 * Refine query based on search results
		 *	- family id
		 * 	- parent:	mother + father
		 * 	- contact:	address, phone, email
		 * 	- baby:		id, name, sex, dob
		 **/
		
		// Get formdata
		$formData = $this->_form->getValues();
		
		# FAMILY
		
		// Family id
		if($formData['family_id'])
			$select->where("f.id = ?", $formData['family_id']);
		
		// Mother
		if ($formData["mother_first_name"])
			$select->where("f.mother_first_name LIKE ?", "%{$formData['mother_first_name']}%");
		if ($formData["mother_last_name"])
			$select->where("f.mother_last_name LIKE ?", "%{$formData['mother_last_name']}%");
		if ($formData["mother_ethnicity"])
			$select->where("f.mother_ethnicity_id <=> ?", "%{$formData['mother_ethnicity']}%");
			
		 // Father
		if ($formData["father_first_name"])
			$select->where("f.father_first_name LIKE ?", "%{$formData['father_first_name']}%");
		if ($formData["father_last_name"])
			$select->where("f.father_last_name LIKE ?", "%{$formData['father_last_name']}%");
		if ($formData["father_ethnicity"])
			$select->where("f.father_ethnicity_id <=> ?", "%{$formData['father_ethnicity']}%");
		
		# CONTACT
		
		// City
		if ($formData['city'])
			$select->where("f.city LIKE ?", "%{$formData['city']}%");
		// State
		if ($formData['state'])
			$select->where("f.state LIKE ?", "%{$formData['state']}%");
		// Zip
		if ($formData['zip'])
			$select->where("f.zip = ?", $formData['zip']);
		// Phone
		if ($formData['phone'])
			$select->where("fp.phone_number LIKE ?", "%" . $formData['phone'] . "%");
		// Email
		if ($formData['email'])
			$select->where("fe.email LIKE ?", "%" . $formData['email'] . "%");
		
		# BABY
		
		// Baby id
		if ($formData['baby_id'])
			$select->where("b.id = ?", $formData['baby_id']);
		// First Name
		if ($formData['first_name'])
			$select->where("b.first_name LIKE ?", "%{$formData['first_name']}%");
		// Last Name
		if ($formData['last_name'])
			$select->where("b.last_name LIKE ?", "%{$formData['last_name']}%");
		// Sex
		if ($formData['sex'])
			$select->where("b.sex = ?", $formData['sex']);
		// Dob
		if ($formData['dob'])
			$select->where("b.dob = ?", $formData['dob']);
		
		$this->_select = $select;
	}
	
	
	
/************************
 * LIST SEARCH RESULTS	*
 ************************/

	/**
	 * Lists generic search results
	 **/
	public function resultsAction()
	{
		// Type
		$type = $this->_getParam("type");
		if (empty($type))
			throw new Zend_Controller_Action_Exception("Could not find param 'type', required!");
		
		// Columns with links (for sorting)
		$urlFields = array("id", "last_name", "first_name", "dob", "sex", "appointment", "study", "mother_first_name", "mother_last_name", "father_last_name", "father_first_name", "telephone", "scheduling_status", "record_status", "record_owner");

		// Get Pager
		$this->_helper->Pager($type, $urlFields);
		
		// Save type to view
		$this->view->type = $type;
	}
	
	/**
	 * Lists family search results
	 **/
	public function familyResultsAction()
	{
		// Type
		$type = $this->_getParam("type");
		if (empty($type))
			throw new Zend_Controller_Action_Exception("Could not find param 'type', required!");
		
		// Columns with links (for sorting)
		$urlFields = array("id", "mother_name", "father_name", "address", "city", "state", "zip", "telephone", "email");

		// Get Pager
		$this->_helper->Pager($type, $urlFields);
		
		// Save type to view
		$this->view->type = $type;
	}
	
}
	