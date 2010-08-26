<?php

class FamilyController extends Zend_Controller_Action 
{

	protected $_searchFilterRules = array(
		'phone_number'	=> 'Digits',
		'zip'			=> 'Digits',
	);
	
	protected $_searchValidationRules = array(
		'myfields' => array(
			'ValidFields',
			'fields' => array('first_name', 'last_name', "ethnicity_id", "city", "state", "baby_first_name", "baby_last_name", "baby_sex", "month", "day", "year", "family_search", "family_id")
		),
		'baby_id'	=> array(
			"Digits",
			'allowEmpty' => true,
			"messages"	=> "Serial number can only be a number."
		),
		'zip'		=> array(
			'Digits',
			array('StringLength', 5, 5),
			'allowEmpty' => true,
			'messages' => array(
				0 => "Zip code has non-numeric characters.",
				1 => "Zip code must be 5 characters long."
			)
		),
		'phone_number'	=> array(
			array('StringLength', 10, 10),
			'allowEmpty' => true,
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => 'The phone number (%value%) must be 10 digits long (xxx-xxx-xxxx)',
			)
		),		
		'email'	=> array(
			'EmailAddress',
			array('Uniqueness', "FamilyEmail", 'email'),
			'allowEmpty' => true,
			'breakChainOnFailure' => true,
			'messages' => array(
				0 => 'The email address (%value%) is not valid',
				1 => 'This email address (%value%) already exists in the database'
			)
		)
	);

	function init()
	{
		$this->_form = $this->_helper->FormSearch;
	}
	
	public function searchAction()
	{
		/**
		 * Process Form
		 **/
		
		// Process form
		$result = $this->_form->processForm($this->_searchFilterRules, $this->_searchValidationRules);
		$formData = $this->_form->getData();
		
		// Convert dob
		if (empty($formData['baby_dob']) === false) {
			// Filter array to string
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$formData['baby_dob'] = $arrayToDate->filter($formData['baby_dob']);
			// Validate
			$validDate = new Zend_Validate_Date();
			if (!($validDate->isValid($formData['baby_dob']))) {
				$this->_form->addError(array_values($validDate->getMessages()));
				$this->_form->setForm();
				$result == 1;
			}
		}		
		
		// Data submitted successfully
		if ($result == 0 && $this->getRequest()->getPost("family_search")) {
			unset($formData["family_search"]);
						
			$return = $this->_prepareList($formData);
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			// Set form for redisplay
			$this->_form->setForm();
		}
	
		/**
		 * Generate Select Options
		 * 	- ethnicity
		 * 	- sex
		 **/
		
		// Ethnicity
		$ethnicity = new Ethnicity();
		$this->view->ethnicityOptions = $ethnicity->getSelectOptions();
		// Gender/Sex
		$this->view->sexOptions = array("" => 'M/F', 0 => 'Female', 1 => 'Male');
		
	}
	
	protected function _prepareList(array $formData, $returnOnlyIds=False)
	{
		/** Setup base query **/
		
		/** 
		 * Want to display these COMMON columns:
		 * 	family_id, baby_ids, mother name (last first), father name (last first),
		 * 	address, city, state, zip, telephone (all), email (all)
		 **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build select query (using fluent interface)
		$select = $db->select()
		
		// Want distinct rows
			->distinct()
		
		// Group by family id
			->group("f.id")
		
		// Start from family table + get family information
			->from(array('f' => 'families'),
	        	array('family_id' => 'id', 'mother_name' => new Zend_Db_Expr("CONCAT_WS(', ', mother_last_name, mother_first_name)"), 'father_name' => new Zend_Db_Expr("CONCAT_WS(', ', father_last_name, father_first_name)"), "address" => new Zend_Db_Expr("CONCAT_WS(',', address_1, address_2)"), "city", "state", "zip"))
		
		// Get phone information
			->joinLeft(array('fp' => 'family_phones'),
				'f.id = fp.family_id', array('telephone' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT phone_number SEPARATOR ', ')")))
		
		// Get email information
			->joinLeft(array('fe' => 'family_emails'),
				'f.id = fe.family_id', array('emails' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT email SEPARATOR ', ')")))
				
		// Get baby ids
			->joinLeft(array('b' => 'babies'),
				'f.id = b.family_id', array('baby_ids' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT('<a href=\"{$this->view->sUrl('edit', 'baby', null, true)}/baby_id/', b.id, '\" target=\"_blank\">', b.id, '</a>') SEPARATOR ', ')")));
//				'<a href=\'', {}, b.id, '\'', b.id, '</a>')
				
		
		/**
		 * Refine query based on search results
		 *	- family id
		 * 	- parent:	mother + father
		 * 	- contact:	address, phone, email
		 * 	- baby:		id, name, sex, dob
		 **/
		
		// Family id
		if($formData['family_id'])
			$select->where("f.id = ?", $formData['family_id']);
		
		 // Parent (mother)
		$mother = $formData['mother'];
		if ($mother) {
			// First name
			($mother['first_name']) ? $select->where("f.mother_first_name LIKE ?", "%{$mother['first_name']}%") : "" ;
			// Last name
			($mother['last_name']) ? $select->where("f.mother_last_name LIKE ?", "%{$mother['last_name']}%") : "" ;
			// Ethnicity
			($mother['ethnicity_id']) ? $select->where("f.mother_ethnicity_id <=> ?", $mother['ethnicity_id']) : "" ;
		}
		 // Parent (father)
		$father = $formData['father'];
		if ($father) {
			// First name
			($father['first_name']) ? $select->where("f.father_first_name LIKE ?", "%{$father['first_name']}%") : "" ;
			// Last name
			($father['last_name']) ? $select->where("f.father_last_name LIKE ?", "%{$father['last_name']}%") : "" ;
			// Ethnicity
			($father['ethnicity_id']) ? $select->where("f.father_ethnicity_id <=> ?", $father['ethnicity_id']) : "" ;
		}
		
		// Contact (city)
		if ($formData['city'])
			$select->where("f.city LIKE ?", "%{$formData['city']}%");
		// Contact (state)
		if ($formData['state'])
			$select->where("f.state LIKE ?", "%{$formData['state']}%");
		// Contact (zip)
		if ($formData['zip'])
			$select->where("f.zip = ?", $formData['zip']);
		// Contact (phone)
		if ($formData['phone_number'])
			$select->where("fp.phone_number = ?", $formData['phone_number']);
		// Contact (email)
		if ($formData['email'])
			$select->where("fe.email = ?", $formData['email']);
		
		// Baby (id)
		if ($formData['baby_id'])
			$select->where("b.id = ?", $formData['baby_id']);
		// Baby (first name)
		if ($formData['baby_first_name'])
			$select->where("b.first_name LIKE ?", "%{$formData['first_name']}%");
		// Baby (last name)
		if ($formData['baby_last_name'])
			$select->where("b.last_name LIKE ?", "%{$formData['last_name']}%");
		// Baby (sex)
		if ($formData['baby_sex'])
			$select->where("b.sex = ?", $formData['baby_sex']);
		// Baby (dob)
		if ($formData['baby_dob'])
			$select->where("b.dob = ?", $formData['baby_dob']);
			
		
		/** Deal with finished query **/
		
		// Save query
		$query = $select->__toString();
		
		// Save records per page option (for now at 10)
		$perPage = 10;
		
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
		$this->_forward("search-results", "family", null);
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
		$sort = ($this->_getParam("sort")) ? $this->_getParam("sort") : "family_id" ;
		
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
		    'path'      	=> $this->view->url(array("controller" => "family", "action" => "search-results"), null, true),
		    'fileName'  	=> "page/%d/sort/{$sort}/order/{$order}",
		    'totalItems' 	=> $rowCount,
		    'perPage'   	=> $perPage
		);
		// Get pager
		$pager =& Pager::factory($params);
		// Get links (preformatted)
		$links = $pager->links;
		
		
		/** 
		 * Want to display these COMMON columns:
		 * 	family_id, baby_ids, mother name (last first), father name (last first),
		 * 	address, city, state, zip, telephone (all), email (all)
		 **/
		
		/* Setup column header links to sort column */
		
		// common fields to setup links for
		$urlFields = array("family_id", "mother_name", "father_name", "address", "city", "state", "zip");
		
		// create the urls
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "family", "action" => 'search-results', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		
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
}