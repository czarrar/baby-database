<?php


class AppointmentController extends Zend_Controller_Action 
{
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
	protected $_scheduleValidationRules = array(
		'myfields' => array(
			'ValidFields',
			'fields' => array('per_page', 'callback', 'study', 'researcher')
		),
		'owner'	=> array(
			'NotEmpty',
			'messages' => "No record owner selected"
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

	function init()
	{
		// Leave header out
		//$this->view->headerFile = '_empty.phtml';
		
		$this->_form = $this->_helper->FormSearch;
	}
	
	function indexAction()
	{
		$this->_forward('schedule', 'appointment');
	}
	
	public function listAction()
	{
		/* Get session variables */
		
		// Declare session namespace
		$session = new Zend_Session_Namespace('query');
		
		// Type of list (e.g. schedule, confirm, outcome etc)
		$listType = $session->type;
		
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
		    'path'      	=> $this->view->url(array("controller" => "appointment", "action" => "list", "type" => $listType), null, true),
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
		$urlFields = array("id", "last_name", "first_name", "dob", "sex", "mother_first_name", "mother_last_name", "father_last_name", "father_first_name", "telephone", "address", "status", "record_status", "record_owner");
		
		// set links for fields that are $listType specific
		switch ($listType) {
			case 'schedule':
				$urlFields[] = "checkout_date";
				break;
			case 'confirm':
			case 'outcome':
				$urlFields[] = "lab_researcher";
				$urlFields[] = "study";
				$urlFields[] = "study_date";				
				break;
			default:
				throw new Zend_Controller_Action_Exception("List type not recognized!");
				break;
		}
		
		// create the urls
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "appointment", "action" => 'list', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		
		/* Setup display (view variables) */
		
		// List page type (schedule, outcome, etc)
		$this->view->listType = $listType;
		
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
	
	public function scheduleAction()
	{	
		// Process form
		$result = $this->_form->processForm(null, $this->_scheduleValidationRules);
		
		// Data submitted successfully
		if ($result == 0) {
			$return = $this->_prepareList('schedule', $this->_form->getData());
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			// Set form for redisplay
			$this->_form->setForm();
		}
		
		// Otherwise -> Non-zero Exit!
		// Something went wrong (either new form or bad data submitted)
		
		/** Setup form for finding babies to start scheduling **/

		// Want to do a search of researchers where keys are researcher_id and values are CONCAT(lab : researcher)
		// will then look into babies and fetch record owner that is the researcher

		/* Get db adapter */
		$db = Zend_Registry::get('db');
		
		// Get study
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short");
		$this->view->studyOptions = $studyOptions;
		
		// Get researcher
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("short");
		$this->view->researcherOptions = $researcherOptions;
		
		// Create options for callback
		$temp = array("", "CHECK ALL", "AM", "PM", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sept", "Oct", "Nov", "Dec");
		$temp = array_combine(array("") + $temp, array("Choose") + $temp);
		$this->view->callbackOptions = $temp;
		
		// Create form select, per_page options
		$perPageOptions = array("1", "10", "25", "50", "100");
		$this->view->perPageOptions = array_combine($perPageOptions, $perPageOptions);		
	}
	
	public function confirmAction()
	{
		// Process form
		$result = $this->_form->processForm(null, $this->_confirmValidationRules, null, array($this, "modifyConfirmOutcome"));
				
		// Data submitted successfully
		if ($result == 0) {
			$return = $this->_prepareList("confirm", $this->_form->getData("confirm"));
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			$this->_form->setForm();
		}
		// Otherwise -> Non-zero Exit!
		// Something went wrong (either new form or bad data submitted)
		$this->_formConfirmOutcomeSetup();
	}
	
	public function modifyConfirmOutcome(array $sectionData)
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
	
	public function outcomeAction()
	{
		// Process form
		$result = $this->_form->processForm(null, $this->_confirmValidationRules, null, array($this, "modifyConfirmOutcome"));
		
		// Data submitted successfully
		if ($result == 0) {
			$return = $this->_prepareList("outcome", $this->_form->getData("outcome"));
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			$this->_form->setForm();
		}
		// Otherwise -> Non-zero Exit!
		// Something went wrong (either new form or bad data submitted)
		$this->_formConfirmOutcomeSetup();
	}
	
	protected function _formConfirmOutcomeSetup()
	{
		/** Setup form select for research and study names **/

		// Get db adapter
		$db = Zend_Registry::get('db');

		// Get researchers
		$researcherTbl = new Researcher();
		$researcherOptions = $researcherTbl->getRecordOwners("short");
		
		// Get studies
		$studyTbl = new Study();
		$studyOptions = $studyTbl->getRecordOwners("short");
		
		// Set select options
		$this->view->researcherOptions = $researcherOptions;
		$this->view->studyOptions = $studyOptions;
		// Create form select, per_page options
		$perPageOptions = array("1", "10", "25", "50", "100");
		$this->view->perPageOptions = array_combine($perPageOptions, $perPageOptions);
	}
	
	/**
	 * Prepares Sql Query to display the list
	 * 
	 * @param string $type
	 * @param array $formData
	 **/
	protected function _prepareList($type, $formData)
	{	
		// Not post, then forward to index
		if (!($this->getRequest()->isPost()))
			$this->forward('index', 'appointment', null, null);
		
		/** Setup base query **/
		
		/** 
		 * Want to display these COMMON columns:
		 * 	#, #, serial no, baby last, baby first, #, dob,
		 * 	sex, mother last, mother first, telephone, address,
		 *	father last, father first, ..., Status, Record Status, Record Owner
		 **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build select query (using fluent interface)
		$select = $db->select()
		
		// Want distinct rows
			->distinct()
		
		// Start from baby table + get baby information
			->from(array('b' => 'babies'),
	        	array('id' => 'id', 'last_name', 'first_name', 'dob', 'sex', "record_status" => 'checked_out', "checkout_date"))
		
		// Get family information
			->joinLeft(array('f' => 'families'),
				'b.family_id = f.id', array('family_id' => 'id', 'mother_first_name', 'mother_last_name', 'father_first_name', 'father_last_name', 'address' => new Zend_Db_Expr('CONCAT(f.address_1, ", ", f.address_2)')))
				
		// Get phone numbers
		//	a) group concatanation of multiple phones of one family
		//	b) add group by id clause so each row is only one baby (but can have multiple families)
			->joinLeft(array('fp' => 'family_phones'),
				'f.id = fp.family_id', array('telephone' => 'fp.phone_number'))
			->where("fp.family_setting_id = 1 OR fp.family_setting_id IS NULL")
			->group("b.id")
			
		// Get status
			->joinLeft(array('sta' => 'statuses'),
				'b.status_id = sta.id', array('status'))
				
		// Get record owners (lab : researcher)
			->joinLeft(array('rb' => 'researcher_babies'),
				'b.id = rb.baby_id', array())
			->joinLeft(array('s' => "studies"),
				"rb.study_id = s.id", array("study_id" => "id"))
			->joinLeft(array('r' => 'researchers'),
				'rb.researcher_id = r.id', array("record_owner" => new Zend_Db_Expr('CONCAT(r.researcher, " : ", s.study)')))
			->joinLeft(array('l' => 'labs'),
				"r.lab_id = l.id", array())
		
		// Only show babies who are 'checked out'
			->where("b.checked_out = ?", 1);

		// Go through $type (list type) for specifics
		switch ($type) {
			// Scheduling appointment specifics
			case 'schedule':
				/**
				 * Do following:
				 * 	- get most recent checkout date
				 * 	- get only subjects who are ACTIVE/RUN/CANCELLED
				 *  - restrict query to specific researcher(s)
				 * 		(i.e. record owners) if needed
				 * 	- @todo check if $select needs to be passed by reference
				 **/
				$this->_scheduleQuery($select, $formData);
				break;
			case 'verify':
				# @todo: For verify don't do this here...but get from something else
				break;
			// Confirming appointments specifics
			case 'confirm':
				/**
				 * Do following:
				 * 	- get study name, date, researcher (common)
				 * 	- narrow query to specific study or researcher (common)
				 *  - get only studies that were scheduled
				 * 	- @todo check if $select needs to be passed by reference
				 **/
				$this->_commonConfirmOutcomeQuery($select, $formData);
				$this->_confirmQuery($select, $formData);
				break;
			case 'outcome':
				/**
				 * Do following:
				 * 	- get study name, date, researcher (common)
				 * 	- narrow query to specific study or researcher (common)
				 *  - get only studies that were confirmed
				 * 	- @todo check if $select needs to be passed by reference
				 **/
				$this->_commonConfirmOutcomeQuery($select, $formData);
				$this->_outcomeQuery($select, $formData);
				break;
			default:
				// Nothing given, throw exception
				throw new Zend_Controller_Action_Exception("Type not specified for appointment list!");
				break;
		}
		
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
		$session->type = $type;
		$session->query = $query;
		$session->count = $count;
		$session->perPage = $perPage;
		
		// Send query, etc to list action
		$this->_forward("list", "appointment", null);		
	}
	
	/**
	 * Refines base query from _prepareList()
	 * to requirements specific to scheduling
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 **/
	protected function _scheduleQuery(Zend_Db_Select $select, array $formData=array())
	{
		/** Add to Query **/
		
		// Get subjects who have status: ACTIVE and RUN and CANCELLED and NO SHOW
		$select->where("b.status_id IN(1,5,6,7)");
			
		// Get babies who want to be called back
		if (isset($formData["callback"])) {
			// Get contact history table (want laleft record)
			$select->joinLeft(array("ch" => "contact_histories"), "b.id = ch.baby_id", array())
				->where("ch.attempt = (SELECT MAX(attempt) FROM contact_histories WHERE b.id = baby_id)");
		
			// Get any baby who wants to be called back
			if ($formData["callback"] == "CHECK ALL") {
				$select->where("ch.contact_callback IS NOT NULL");
			}
			// Get baby who wants to be called back at specific time
			else {
				$select->where("ch.contact_callback = ?", $formData["callback"]);
			}
		}
		
		// Rectrict search to specific study
		if ($formData['study'] != "ALL")
			$select->where("rb.study_id = ?", $formData['study']);
		// Restrict search to specific researcher
		if ($formData['researcher'] != "ALL")
			$select->where("rb.researcher_id = ?", $formData['researcher']);
		// If not admin or coordinater, then restrict 'ALL' search to just lab members
		if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator")) {
			// Join caller table to restrict display to caller's associated lab
			$select->joinLeft(array("c" => "callers"), "r.lab_id = c.lab_id", array())
			// Get only this specific caller
				->where("c.id = ?", $_SESSION['caller_id']);
		}
		
		return;
	}
	
	/**
	 * Refines base query from _prepareList() to
	 * requirements specific to both confirm and outcome
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 * @return void
	 **/
	protected function _commonConfirmOutcomeQuery(Zend_Db_Select $select, array $formData=array())
	{
		// Get baby study information
		$select->joinLeft(array("bs" => "baby_studies"),
			"b.id = bs.baby_id", array("study_date" => "bs.appointment"))
			
		// Get study name
			->joinLeft(array("s_bs" => "studies"),
				"bs.study_id = s_bs.id", array("study_id" => "s_bs.id", "study" => "s_bs.study"))
			
		// Get researcher name
			->joinLeft(array("r_bs" => "researchers"),
				"s_bs.researcher_id = r_bs.id", array("lab_researcher" => "r_bs.researcher"));
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Narrow based on study and researcher desired
		if ($formData['study'] != "ALL")
			$select->where("s_bs.id = ?", $formData['study']);
		if ($formData['researcher'] != "ALL")
			$select->where("r_bs.id = ?", $formData['researcher']);
		
		if (empty($formData['date1']) === false and empty($formData['date2']) === false and empty($formData['alldates']))
			$select->where("bs.appointment BETWEEN {$db->quote($formData['date1'])} AND {$db->quote($formData['date2'])}");
		else if (empty($formData['date1']) === false and empty($formData['alldates']))
			$select->where("bs.appointment LIKE ?", $formData['date1']);
				
		// Group by study if there are multiple studies to be confirmed, etc
		$select->group("bs.study_id");
		return;
	}
	
	/**
	 * Refines base query from _prepareList() to
	 * requirements specific to confirming appts
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 * @return void
	 **/
	protected function _confirmQuery(Zend_Db_Select $select, array $formData=array())
	{
		// Restrict to studies that were scheduled
		$select->where("b.status_id = ?", 3);
		
		return;
	}
	
	/**
	 * Refines base query from _prepareList() to
	 * requirements specific to study outcome
	 *
	 * @param obj Zend_Db_Select $select
	 * @param array $formData
	 * @return void
	 **/
	protected function _outcomeQuery(Zend_Db_Select $select, array $formData=array())
	{
		// Restrict to studies that were confirmed
		$select->where("b.status_id = 3 OR b.status_id = 4");
		
		return;
	}
	
	/**
	 * Throws an exception with desired message
	 *
	 * @throws Zend_Controller_Action_Exception
	 **/
	protected function _exception($message)
	{
		throw new Zend_Controller_Action_Exception($message);
	}
	
	
	protected function _quoteArrayVals(array $vals)
	{
		$db = Zend_Registry::get('db');
		
		foreach ($vals as $key => $value) {
			if (is_array($value) or !(is_numeric($key)))
				throw new Exception("Unexpected array given!");
			$vals[$key] = $db->quote($value);
		}
		
		return $vals;
	}
	
	//SELECT DISTINCT b.id, sh.baby_id, sh.study_id, sh.appointment,sh_laleft.study_id, sh_laleft.appointment FROM babies AS b LEFT JOIN study_histories AS sh ON b.id = sh.baby_id LEFT JOIN (SELECT baby_id, study_id, appointment FROM study_histories WHERE appointment=(SELECT MAX(sh_max.appointment) FROM study_histories AS sh_max)) AS sh_laleft ON sh_laleft.baby_id = b.id GROUP BY b.id, sh.appointment;
}