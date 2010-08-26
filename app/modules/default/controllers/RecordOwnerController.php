<?php

/** Zend_Controller_Action **/
require_once 'Zend/Controller/Action.php';

class RecordOwnerController extends Zend_Controller_Action
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
	
	// Number (ids in study_outcome_id) corresponding to some study outcome
	const OUTCOME_RUN		= 1;
	const OUTCOME_NOSHOW	= 2;
	const OUTCOME_CANCELED	= 3;

	/**
	 * Validation Rules for checking out a record
	 *
	 * @var array
	 **/
	protected $_checkoutValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 		=> array("record_owner", "record_id", "check")
		),
		'record_owner'	=> array(
			'NotEmpty',
			"messages" => "You must select a record owner to checkout your record"
		),
		"record_id" => array(
			"NotEmpty",
			"messages" => "Please select a baby to check-out"
		)
	);
	
	/**
	 * Validation Rules for checking in a record
	 *
	 * @var array
	 **/
	protected $_checkinValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 		=> array("record_id", "check")
		),
		"record_id" => array(
			"NotEmpty",
			"messages" => "Please select a baby to check-in"
		)
	);
	
	/**
	 * Validation Rules for searching for records to check-out
	 *
	 * @var array
	 **/
	protected $_checkoutSearchValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 		=> array('baby_id', 'lyears', 'lmonths', 'ldays', "hyears", "hmonths", "hdays", "allages", "fromDate_year", "fromDate_month", "fromDate_day", "toDate_year", "toDate_month", "toDate_day", "alldates", "sex", "numlangs", "language_id", "rate", "moreorless", "study_id", "studyname", "rname", "researcher_id", "enthusiasm", "since_month", "since_day", "since_year", "checkout", "per_page", "oddeven")
		),
		"baby_id"		=> array(
			"Digits",
			'allowEmpty'	=> true,
			"messages"		=> "Serial number must be a number"
		),
		"enthusiasm"	=> array(
			"Digits",
			"allowEmpty"	=> true,
			"messages"		=> "Level of enthusiasm must be a number"
		)
	);
	
	/**
	 * Validation Rules for searching for records to check-in
	 *
	 * @var array
	 **/
	protected $_checkinSearchValidationRules = array(
		'myfields'		=> array(
			'ValidFields',
			'fields' 		=> array('baby_id', "owner", 'lyears', 'lmonths', 'ldays', "hyears", "hmonths", "hdays", "allages", "checkin", "per_page")
		),
		"baby_id"		=> array(
			"Digits",
			'allowEmpty'	=> true,
			"messages"		=> "Serial number must be a number"
		)
	);


	function init()
	{
		$this->_db = Zend_Registry::get('db');
		$this->_form = $this->_helper->FormSearch;
	}
	
	public function listAction()
	{
		// odd/even?
		if ($_SESSION["odd_even"]) {
			if ($_SESSION["odd_even"] == "odd")
				$this->view->odd = 1;
			else
				$this->view->even = 1;			
		}
	
		$session = new Zend_Session_Namespace('query');
		
		// User wants to checkout or checkin some records
		$checkType = $this->getRequest()->getPost("check");
				
		if ($checkType) {
		
			/** PROCESS FORM **/
			switch ($checkType) {
				case 'checkout':
					$result = $this->_form->processForm(null, $this->_checkoutValidationRules);
					break;
				case 'checkin':
					$result = $this->_form->processForm(null, $this->_checkinValidationRules);
					break;
				default:
					break;
			}
			
			// Data submitted successfully, change selected records
			if ($result == 0) {
				$recs = $this->getRequest()->getPost("rec");
				// Check if any records to change submitted
				if (empty($recs)) {
					$this->_form->addError("No record to $checkType submitted!");
					$this->_form->setForm();
				} else {
					$this->_forward("change-owner", "record-owner", null, $this->_form->getData());
				}
			}
			
		} else {
			$checkType = $session->type;
		}
		
		/** Get researchers **/
		
		// Get db adapther
		$db = $this->_db;
	
		// List the output from checkout/out and checkout/in
		require_once 'Pager/Pager.php';

		// Setup record-owners
		if ($checkType == "checkout") {
			/* Setup base query */
			$select = $db->select()
				->distinct()

			 	// Get researcher_id
				// from base table (researchers)
				->from(array('s' => 'studies'),
					array("study_id" => "id", "study"))
				->joinLeft(array('r' => 'researchers'),
			        "s.researcher_id = r.id", array("researcher_id" => "id", "researcher"))
				// Get record owner (lab : researcher)
			    ->joinLeft(array('l' => 'labs'),
			        'r.lab_id = l.id', array())
				// Get the lab associated with callers
				->joinLeft(array("c" => "callers"),
					"l.id = c.lab_id", array());

			if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator")) {
				$select->where("c.id = ?", $_SESSION['caller_id']);
			}

			// Want only rows with active (to_use=1) researcher
			$select->where("r.to_use = ?", 1)
			// Don't want researcher of none
				->where("r.researcher != ?", "None")
			// Want only active studies
				->where("s.to_use = ?", 1)
			// Order by 'record_owner'
				->order("researcher");

			/* Execute Query */
			$stmt = $db->query($select);
			$stmt->execute();
			$result = $stmt->fetchAll();

			// Create form select, owner options
			$recordOwnerOptions = array("" => "Choose");
			foreach ($result as $key => $row)
				$recordOwnerOptions["{$row['researcher_id']}:{$row['study_id']}"] = "{$row['researcher']} : {$row['study']}";
			$this->view->recordOwnerOptions = $recordOwnerOptions;
		}
		
		$page = $this->_getParam('page');
		$page = (empty($page)) ? 1 : $page ;
		
		// Get the sort parameter, if empty default is b.id
		$sort = $this->_getParam('sort');
		$sort = (empty($sort)) ? 'id' : $sort ;
		
		// Get order (ASC or DESC), if empty default is ASC
		$order = $this->_getParam('order');
		$order = (empty($order)) ? 'ASC' : $order ;
		
		// Setup urls
		$urlFields = array('id', 'record_owner', "record_status", "status", "dob", "sex", "first_name", "last_name", "mother_first_name", "mother_last_name", "language");
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "record-owner", "action" => 'list', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		$this->view->assign('link', $link);
		
		// Get session
		// @todo: set a timeout
		// Empty, then just redirect to checkout
		if (empty($session->query))
			$this->_forward("{$checkType}-search", "record-owner");
		
		// Limit query based on page number and number of rows to display per page
		// Also order by $sort $order
		$addition = $db->select()->limitPage($page, $session->perPage)->order(array("{$sort} {$order}"))->__toString();
		$query = $session->query . " " . $addition;		
				
		// Fetch rows
		$stmt = $db->query($query);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Check row exists
		// change this into an error message
		if (count($result) < 1) {
			$this->_form->addError("No babies found with desired criteria", "count");
			$this->_form->setForm();
			
		}

		$data = array();
		
		$params = array(
		    'mode'      => 'Sliding',
			'delta'		=> 2,
		    'append'    => false,
			'currentPage' => $page,
		    'path'      => $this->view->url(array('controller' => "record-owner", 'action' => "list"), null, true),
		    'fileName'  => "page/%d/sort/{$sort}/order/{$order}",
		    'totalItems'  => $session->count,
		    'perPage'   => $session->perPage
		);
		$pager =& Pager::factory($params);
		
		// Columns to display
		$this->view->results = $result;
		$this->view->links = ($pager->links) ? $pager->links : 1 ;
		$this->view->type = $checkType;
		$this->view->rowCount = $session->count;
	}

	/**
	 * Searches through baby records, allowing user
	 * 	to 'check-out' a bay.  Checking out locks the
	 * 	baby from use by other users until it is
	 * 	checked back in.
	 **/
	public function checkoutSearchAction()
	{
		/**
		 * PROCESS FORM
		 * 
		 * Will filter+validate, resulting in:
		 * 	1) success
		 * 	2) no success: new form
		 * 	3) no success: bad form data
		 * 
		 **/
		$this->_form
			->setValidationRules($this->_checkoutSearchValidationRules);
		$result = $this->_form->processForm();
		
		/** Successful Data Submission **/
		
		if ($result == 0) {
			// Prepare records to display in table
			// this function will forward to listAction if everything is good
			$return = $this->_prepareList('checkout', $this->_form->getData());
			
			// Something went wrong in the preparation, return error to user
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			
			// Set some view values
			$this->_form->setForm();
		}
		
		
		/** Form not submitted...prepare search form **/
		
		// Get study select options
		$study = new Study();
		$studyOptions = $study->getSelectOptions();
		
		// Get researcher select options
		$researcher = new Researcher();
		$researcherOptions = $researcher->getSelectOptions();
		
		// Get language select options
		$language = new Language();
		$languageOptions = $language->getSelectOptions();
		
		// Create form select, per_page options
		$perPageOptions = array("1", "10", "25", "50", "100");
		$this->view->perPageOptions = array_combine($perPageOptions, $perPageOptions);
		
		// Set view values for select options
		$this->view->assign(compact('studyOptions', "researcherOptions", "languageOptions"));
	}
	
	/**
	 * Searches through baby records, allowing user
	 * 	to check records back in for use by other people
	 **/
	public function checkinSearchAction()
	{
		/**
		 * PROCESS FORM
		 **/
		$this->_form->setValidationRules($this->_checkinSearchValidationRules);
		$result = $this->_form->processForm();
		
		/** Successful Data Submission **/
		
		if ($result == 0) {
			// Prepare records to display in table
			// this function will forward to listAction if everything is good
			$return = $this->_prepareList('checkin', $this->_form->getData());
			
			// Something went wrong in the preparation, return error to user
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			
			// Set some view values
			$this->_form->setForm();
		}
		
		/** Form is new **/
		elseif ($result == -1) {
			// Set default view values
			$this->view->allages = 1;
		}
		
		/** Form new or not properly submitted...prepare search form **/
		
		// Get form select options for record owners
		// based on user lab affliation and/or access level
		$study = new Study();
		$ownerOptions = $study->getRecordOwners();
		
		// Set view values
		$this->view->ownerOptions = $ownerOptions;
		
		// Create form select, per_page options
		$perPageOptions = array("1", "10", "25", "50", "100");
		$this->view->perPageOptions = array_combine($perPageOptions, $perPageOptions);
	}
	
	/**
	 * Prepare an sql query for displaying baby records in a table
	 * 
	 * Called by checkoutSearchAction or checkinSearchAction
	 *
	 * @param string $listType Can be 'checkout' or 'checkin'
	 **/
	protected function _prepareList($listType, $formData)
	{
		// Not post, whoops forward to index
		if (!($this->getRequest()->isPost()))
			$this->forward('index', 'appointment', null, null);
				
		/** Setup base query **/
		
		/**
		 * Want to display these COMMON columns:
		 * 	baby_id (or serial no), contact history (link), record owner,
		 * 	checkout status, status, study history (link), checkout history (link), 
		 * 	dob, sex, baby first name,baby last name, mother first name, 
		 * 	mother last name, language
		 **/
		
		// Load db adapter
		$db = Zend_Registry::get('db');
		
		// Init db select class (using fluent interface)
		$select = $db->select()
		
		// Want distinct rows
			->distinct()
			->group('b.id')
			
		// Start from baby table + get baby information
			->from(array('b' => 'babies'),
		        array('id' => 'id', 'last_name', 'first_name', 'dob', 'sex', "record_status" => 'checked_out', 'date_of_entry'))
		
		// Get baby languages spoken + percent
		// Get through
		//	a) concatanating language and percent per week into one field
		//	b) group concatanation of multiple languages that one baby is exposed to
		//	c) add group by id clause so each row is only one baby
		    ->joinLeft(array('bl' => 'baby_languages'),
		        'b.id = bl.baby_id', array())
			->joinLeft(array('l' => 'languages'),
				'bl.language_id = l.id', array("language" => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT CONCAT_WS(":", l.language, bl.percent_per_week))')))

		// Get family information
			->joinLeft(array('f' => 'families'),
				'b.family_id = f.id', array('family_id' => 'id', 'mother_first_name', 'mother_last_name'))

		// Get status
			->joinLeft(array('sta' => 'statuses'),
				'b.status_id = sta.id', array('status'))

		// Get study history for possible search parameters
			->joinLeft(array("sh" => "study_histories"),
				"b.id = sh.baby_id", array())

		// Get study owners (i.e. researchers)
			->joinLeft(array("s" => "studies"),
				"sh.study_id = s.id", array("study_owner_id" => "researcher_id"));

		# End base query
		
		# Start conditional query additions
		
		/**
		 *	Things to search (for checking out and some for checking in)
		 * 	- subtract ages from today (if check mark then don't do search)
		 *  - do between search for date_of_entry (if check mark then don't do search)
		 *  - language search
		 *  - replicate study + researcher but see if want multi select box
		 *  - level of enthusiasm in prior study
		 *  - not been in study since...
		 **/
				
		// If serial number given then just look for that
		$baby_id = $formData['baby_id'];
		if (!empty($baby_id)) {
			$select->where("b.id = ?", $baby_id);
		} else {
			// If all ages not checked
			// then fetch ages of babies between desired range
			if ($formData['allages'] != 1) {
				try {
					$curdate = new Zend_Date();				
					// Convert lower range age into a dob
					// lyear, lmonth, lday => YYYY-MM-DD
					$curdate->sub($formData['ldays'], "d");
					$curdate->sub($formData['lmonths'], "M");
					$curdate->sub($formData['lyears'], "Y");
					$higher_dob = $curdate->toString("YYYY-MM-dd");

					$curdate = new Zend_Date();
					// Convert higher range age into a dob
					// hyear, hmonth, hday => YYYY-MM-DD
					$curdate->sub($formData['hdays'], "d");
					$curdate->sub($formData['hmonths'], "M");
					$curdate->sub($formData['hyears'], "Y");
					$lower_dob = $curdate->toString("YYYY-MM-dd");

					// Find kids with dates of birth between $lower_dob and $higher_dob
					$select->where("b.dob BETWEEN " . $db->quote($lower_dob) . " AND " . $db->quote($higher_dob));
				} catch(Exception $e) {
					return False;
				}		
			}
		}
		
		// Add to query depending on type of list
		switch ($listType) {
			case 'checkout':
				$this->_checkoutQuery($select, $formData);
				break;
			case 'checkin':
				$this->_checkinQuery($select, $formData);
				break;
			default:
				throw new Zend_Controller_Action_Exception("List type not recognized, should be either 'checkout' or 'checkin'");
				break;
		}
		
		// Save query
		$query = $select->__toString();
				
		// Save record per page option
		$perPage = $formData['per_page'];
		
		// Get count of rows
		$select->reset(Zend_Db_Select::GROUP);
		$select->reset(Zend_Db_Select::COLUMNS);
		$select->from(null, "COUNT(DISTINCT b.id) AS count");
		$stmt = $select->query();
		$count = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
		$count = $count[0];
				
		// Send an error if count is below 1
		if ($count < 1)
			return False;
		
		// Have session with listType, rowCount, query, perPage
		$session = new Zend_Session_Namespace('query');
		$session->type = $listType;
		$session->query = $query;
		$session->perPage = $perPage;
		$session->count = $count;

		// Send query etc to list action
		$this->_forward("list", "record-owner", null);
	}
	
	/**
	 * Adds query parameters that are specific to checking out
	 * 	(this means searching for records to checkout and not actually
	 * 	checking a record out)
	 * 	
	 * Called by $this->_prepareList()
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 * @return void
	 **/
	protected function _checkoutQuery($select, $formData)
	{
		// Load db adapter
		$db = Zend_Registry::get('db');
	
		// Don't want any babies that are already checked out
		$select->where("b.checked_out = ?", 0)		
		// Don't want archived babies
			->where("b.status_id != ?", 2);
	
		// If all date of entries not selected
		// then fetch babies who were entered between the desired dates
		if ($formData['alldates'] != 1) {
			// Convert parts of date into YYYY-MM-DD
			$from_date = "{$formData['fromDate_year']}-{$formData['fromDate_month']}-{$formData['fromDate_day']}";
			$to_date = "{$formData['toDate_year']}-{$formData['toDate_month']}-{$formData['toDate_day']}";

			// Find kids who were entered into the db $from_date to $to_date
			$select->where("b.date_of_entry BETWEEN " . $db->quote($from_date) . " AND " . $db->quote($to_date));
		}

		// If one sex selected, then fetch only that sex
		if (empty($formData['sex']) === false)
			$select->where("b.sex = ?", $formData['sex']);

		// Given number of languages
		// search for babies speaking those languages at percent per week specified
		foreach ($formData['language'] as $key => $lang) {
			if (empty($lang['language_id']))
				continue;
			
			$select->where("bl.language_id = ?", $lang['language_id']);
			
			// Add percentage to query, if not empty
			if (!(empty($lang['rate']))) {
				// Want more or less than a certain percent
				switch ($lang['moreorless']) {
					case 'more':
						$select->where("bl.percent_per_week > ?", $lang['rate']);
						break;
					case 'less':
						$select->where("bl.percent_per_week < ?", $lang['rate']);
						break;
					default:
						# Do nothing
						break;
				}
			}
		}

		// If prior study... selected,
		// search for babies who have completed a specific study
		if (empty($formData['studyname']) === false) {
			// Actually study name is study_id
			$studyId = $formData['studyname'];
			// Don't want baby to be in any studies
			if ($studyId == 'None')
				$select->where("sh.study_id IS NULL");
			// Want baby to have been in this study
			$select->where("sh.study_id = ?", $studyId);
		}

		// Exclude certain studies from search
		foreach ($formData['notstudy'] as $key => $notStudy) {
			$studyId = $notStudy['study_id'];
			// Skip if field empty
			if (empty($studyId))
				continue;
			// Else add to query
			else
				$select->where("sh.study_id != ?", $studyId);
		}

		// Want studies from a specific researcher (study owner)
		if (empty($formData['rname']) === false) {
			// Actually not name but id
			$researcherId = $formData['rname'];
			$select->where("study_owner_id = ?", $researcherId);
		}

		// Don't want studies from specific researchers (study owners)
		foreach ($formData['notrname'] as $key => $notStudy) {
			$researcherId = $notStudy['researcher_id'];
			// Skip if field empty
			if (empty($researcherId))
				continue;
			// Else add to query
			else
				$select->where("study_owner_id != ?", $researcherId);
		}

		// Want babies who displayed a certain level of enthusiasm
		if (isset($formData['enthusiasm']))
			$select->where("sh.level_enthusiasm > ?", $formData['enthusiasm']);

		// Want babies who have not been in a study since some date
		if (!(empty($formData['since_year'])) and !(empty($formData['since_month'])) and !(empty($formData['since_day']))) {
			$sinceDate = "{$formData['since_year']}-{$formData['since_month']}-{$formData['since_day']}";
			$select->where("b.date_of_entry > ?", $sinceDate);
		}
		
		// Do only odd/even search
		if (!(empty($formData["oddeven"])))
			$_SESSION["odd_even"] = $formData["oddeven"];
		else
			unset($_SESSION["odd_even"]);
	}
	
	/**
	 * Adds query parameters that are specific to checking IN
	 * 	(this means searching for records to checkIN and not actually
	 * 	checking a record IN)
	 * 	
	 * Called by $this->_prepareList()
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 * @return void
	 **/
	protected function _checkinQuery($select, $formData)
	{
		// Only want babies that are checkout and need to be checked in
		$select->where("b.checked_out = ?", 1)
		
		//Only want babies whose status is active, run, cancelled, no show, and contacting
			->where("b.status_id IN("
				. self::INACTIVE . ","
				. self::CONTACTING . ","
				. self::RUN . ","
				. self::CANCELED . ","
				. self::NO_SHOW . ")"
			)
		
		// Get record owners (i.e. 'lab : researcher')
			->joinLeft(array('bs' => 'baby_studies'),
				'b.id = bs.baby_id', array());
		
		// Restrict search to a specific record owner
		if ($formData['owner'] != "All")
			$select->where("bs.study_id = ?", $formData['owner']);
	}
	
	public function changeOwnerAction()
	{
		// Get db adapter
		$db = $this->_db;
				
		// Type of change (checkout or checkin)
		$checkType = strtolower($this->_getParam("check"));
		
		// If there is no post, then redirect to checkout-search
		if (empty($checkType))
			$this->_redirect(array("controller" => "record-owner", "action" => "checkout-search"));
		
		// Get record owners
		if ($checkType == "checkout") {
			// Get record owner (for checking out)
			$recordOwner = $this->_getParam("record_owner");
			// Split record owner
			list($researcherId, $studyId) = explode(":", $recordOwner);
		}
		
		// Get records (array)
		$records = $this->_getParam("rec");
		$records = (isset($records[0])) ? $records : array($records) ;
		// Check if any records
		if (count($records) < 1) {
			$this->view->numRecords = 0;
			$this->view->babyRecords = 0;
			$this->view->type = $checkType;
			return False;
		}
		
		$babyIds = array();
		
		// Go through each record that user wants to checkout
		// @todo: check for exceptions? and record them for error display
		foreach ($records as $record) {
			$db->beginTransaction();
			try {
				// Get baby id (serial no)
				$babyId = $record['record_id'];
				$babyIds[] = $babyId;
				
				// Default baby status (active)
				$statusId = self::INACTIVE;
				
				// Update babies table (change checked-in to checked-out)
				$babies = new Baby();
				$brow = $babies->fetchRow($where);
				
				// Calculate baby age
				$curdate = new Zend_Date();
				$curdate->sub($brow->dob, "YYYY-MM-dd");
				$babyAge = $curdate->toString("YYYY-MM-dd");
			
				// If Checking-In
				if ($checkType == "return") {
					// Fetch Current Studies
					$bsTbl = new BabyStudy();
					$bsSelect = $bsTbl->select()->where("baby_id = ?", $babyId);
					$bsRows = $bsTbl->fetchAll($bsSelect);
					$bsNum = count($bsRows);
					// Insert records into checkout history
					$chTbl = new CheckoutHistory();
					// (optional) Insert records into study history
					$shTbl = new StudyHistory();
					// Add to checkout history
					if ($bsNum < 1) {
						// No current studies, just add to checkout history
						$chData = array(
							"baby_id"		=> $babyId,
							"checked_out"	=> ($checkType == "checkout") ? 1 : 0 ,	// 1=Checkout, 0=Checkedin
							"baby_age"		=> $babyAge
						);
						$checkoutHistoryIds[] = $chTbl->insert($chData);
					} else {
						// Set status id as canceled because canceling study
						$statusId = self::CANCELED;
						// Loop through current studies
						foreach ($bsRows as $bsRow) {
							// Insert record into study history
							$shData = array(
								"baby_id"			=> $babyId,
								"study_id"			=> $bsRow->study_id,
								"caller_id"			=> $_SESSION['caller_id'],
								"appointment"		=> $bsRow->appointment,
								"date_cancel"		=> new Zend_Db_Expr('CURDATE()'),
								"study_outcome_id" 	=> self::OUTCOME_CANCELED,
								"comments"			=> "Automatic check-in of record, thus scheduling status was marked as canceled."
							);
							$shTbl->insert($shData);
							// Add to checkout history
							$chData = array(
								"baby_id"		=> $babyId,
								"study_id"		=> $bsRow->study_id,
								"checked_out"	=> ($checkType == "checkout") ? 1 : 0 ,	// 1=Checkout, 0=Checkedin
								"baby_age"		=> $babyAge
							);
							$checkoutHistoryIds[] = $chTbl->insert($chData);
							// Remove current study
							$bsRow->delete();
						}
					}
				}
				// TOOK OUT CHECK_OUT FUNCTION
				
				// Change checkout status and scheduling status
				$babyData = array(
					'checked_out' 			=> ($checkType == "checkout") ? 1 : 0 , 	// 1=Checkout, 0=Checkedin
					"status_id" 			=> $statusId,
					"checkout_date" 		=> new Zend_Db_Expr('CURDATE()'),
					"checkout_caller_id"	=> $_SESSION["caller_id"]
				);
				$where = $db->quoteInto("id = ?", $babyId);
				$babies->update($babyData, $where);
				
				$db->commit();
			} catch(Exception $e) {
				$db->rollback();
				$this->view->numRecords = count($checkoutHistoryIds);
				$this->view->babyRecords = $babyIds;
				$this->view->type = $checkType;
				throw new Exception("An error has occured: <br/>\n" . $e->getMessage());
			}
		}
		
		// Tell user how many records checkout or checkedin
		// @todo: give link where user can see there checkouts
		$this->view->numRecords = count($checkoutHistoryIds);
		$this->view->babyRecords = $babyIds;
		$this->view->type = $checkType;
	}
}