<?php

class BabyController extends Zend_Controller_Action 
{

	/**
	 * Wrapper for form or other data
	 * Filters and Validates data using Zend_Filter_Input
	 *
	 * @var Zarrar_Filter_Data
	 **/
	protected $filter;
	
	/**
	 * Holds session data for 'babyform'
	 *
	 * @var Zend_Session
	 **/
	protected $session;
	
	/**
	 * Error validation rules for form to archive babies
	 *
	 * @var array
	 **/
	protected $_archiveValidationRules = array(
		'myfields'	=> array(
			'ValidFields',
			'fields' 	=> array('id', 'caller', 'reason', 'comments', 'Archive')
		),
		'baby_id'	=> array(
			'NotEmpty',
			"messages"	=> "Baby id is missing!"
		),
		'caller_id'	=> array(
			'NotEmpty',
			"messages"	=> "Caller id is missing!"
		),
		'reason'	=> array(
			'NotEmpty',
			"messages"	=> "Please select the reason for archiving this baby."
		)
	);
	

	function init()
	{
		// Declare classes
		// Add to stylesheets
		//$this->view->headLink()->appendStylesheet("{$this->view->dir_styles}/cssform.css");
		
		// Declare form filter
		//$this->default_options = array('namespace' => 'Zarrar_Validate', 'escapeFilter' => 'StringTrim');
		//$this->filter = new Zarrar_Filter_Data($this->default_options);
	}
	
	function archiveAction()
	{
		// Form processing
		$form = $this->_helper->FormSearch;
		$result = $form->processForm(null, $this->_archiveValidationRules);
	
		// Get baby_id
		$babyId = $this->_getParam('id');
		if (empty($babyId))
			throw new Zend_Controller_Action_Exception("Baby id must be given");
			
		// If submitted correctly, process form
		if ($result == 0) {
			// Add reason + caller to baby comments
			$formData = $form->getData();
			$comments = $formData['comments'];
			$comments .= PHP_EOL . "Archive Reason: {$formData['reason']}";
			$comments .= PHP_EOL . "Archive Caller: {$formData['caller']}";
			// Archive
			$this->_archive($formData['id'], $comments);
			
			// End
			exit("Baby with serial no. $babyId has been archived.<br /><br />
			<a href=\"" . $this->view->url(array("action" => "index", "controller" => "index"), null, true) . "\">Go Home</a> | <a href=\"javascript:window.opener.location.reload(true); window.close();\">Close Window</a>");
		}
		
		// Retrieve baby info
		$babyTbl = new Baby();
		$where = $babyTbl->getAdapter()->quoteInto("id = ?", $babyId);
		$baby = $babyTbl->fetchRow($where);
		// Check baby entry exists
		if (empty($baby))
			throw new Zend_Controller_Action_Exception("Baby entry does not exist!");
		$this->view->baby = $baby->toArray();
		
		// Retrieve caller name
		$callerId = $_SESSION['caller_id'];
		$callerTbl = new Callers();
		$where = $callerTbl->getAdapter()->quoteInto("id = ?", $callerId);
		$caller = $callerTbl->fetchRow($where);
		$this->view->caller = $caller->name;
		
		// Set reasons for decline
		$this->view->reasonOptions = array(
			""	=> "Choose",
			"Busy" => "Busy",
			"Far" => "Far",
			"Inconvenient" => "Inconvenient",
			"No Car" => "No Car",
			"Not Interested" => "Not Interested",
			"Too many children" => "Too many children",
			"Unable to contact" => "Unable to contact",
			"Other" => "Other"
		);
	}
	
	function successAction()
	{
		$message = "Baby Information Updated!";
		if (isset($_SESSION["baby_message"])) {
			$message .= "<br /> " . $_SESSION["baby_message"];
			unset($_SESSION["baby_message"]);
		}
		
		$this->view->message = $message;
	}
	
	function indexAction()
	{		
		$languages = new Language();
		$options = $languages->getSelectOptions();
	}
	
	function commonAction()
	{
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
	
		/**
		 * New form or bad data submitted (non-zero exit)
		 * 	- setup form
		 **/

		/** Get + Check Params **/

		// Get baby_id
		$babyId = $this->_getParam("baby_id");

		// Get study id
		$studyId = $this->_getParam("study_id");
		
		// Get type
		$type = $this->_getParam("type");
		
		// Get start date
		$startDate = $this->_getParam("start_date");
		
		// Get end date
		$endDate = $this->_getParam("end_date");
		
		// Check that baby_id and study_id given, otherwise throw error
		if (empty($babyId))
			throw new Zend_Controller_Action_Exception("Baby id");
		// Assign vars to view
		else {
			$this->view->babyId = $babyId;
			$this->view->studyId = $studyId;
			$this->view->type = $type;
			$this->view->startDate = $startDate;
			$this->view->endDate = $endDate;
		}
	}
	
	function viewAction()
	{
		// Get id for baby table
		$id = (int) $this->_getParam('baby_id');
		if (empty($id))
			throw new Exception('No id to edit given.');
		
		// Fetch row in table
		$baby_table = new Baby();
		$rows = $baby_table->find($id);
		
		// Check row exists
		if (count($rows) != 1)
			throw new Exception('Either no rows found for id parameter or more than one found');
		else
			$baby = $rows->current();
		
		// Get column + convert to array
		$this->view->row = $baby->toArray();
	}
	
	function newAction()
	{
	
		$this->view->type = "new";
	
		/* Process Form */
		
		$sectionsToTables = array(
			"baby"		=> "Baby",
			"language"	=> "BabyLanguage",
			"family"	=> "Family",
			"phone"		=> "FamilyPhone",
			"email"		=> "FamilyEmail"
		);
		$dbFunction = array($this, "createRow");
		
		$formCreateRow = $this->_helper->FormCreate;

		// Get id for family if present
		$family_id = (int) $this->_getParam('family_id');
	
		if (empty($family_id))
			$result = $formCreateRow->processForm($sectionsToTables, $submitCheck = null, $dbFunction);
		else
			$result = $formCreateRow->processForm($sectionsToTables, $submitCheck = null, $dbFunction, array("family", "phone", "email"));
		
		// Successful form, redirect to some other page
		if($result == 0) {
			// @todo: add check for languages to make sure below 100
			$babyId	= $this->_getParam("baby_id");
			$this->_forward("success", "baby");
		}
		else {
			$languageData = $formCreateRow->getData("language");
			if (!(empty($languageData[4]))) {
				$customLang = $languageData[4];
				// Get db adapter
				$db = Zend_Registry::get('db');
				// Build sql query
				$query = "SELECT language FROM languages WHERE id = ?";
				// Execute query
				$stmt = $db->query($query, $customLang["language_id"]);
				$stmt->execute();
				$result = $stmt->fetchAll();
				$result = $result[0]["language"];
				// Set view param
				$this->view->specialLanguage = $result;
			}
			
			if ($result == 1) {
				// Set new vars into view
				$formCreateRow->setForm();
			}
		}
		
		// If not empty then, fetch data and add to the section
		if ($result == -1) {
			// Default date to today
			$d = date("Y-m-d");
			$formCreateRow->addData("baby", array("dob" => $d));
		
			if (empty($family_id) === false) {
				$family = new Family();
				// Sanitize family id
				$goodFamilyId = $family->getAdapter()->quote($family_id);
				
				// Get family info and put in table
				$familyRow = $family->fetchRow("id = {$goodFamilyId}");
				if (empty($familyRow))
					throw new Zend_Controller_Action_Exception("Could not find family id: $goodFamilyId");
				$formCreateRow->addData("family", $familyRow->toArray());
			
				// Get phone info and put in table
				$phones = $familyRow->findFamilyPhone();
				$temps = $phones->toArray();
				$result = array();
				foreach ($temps as $temp) {
					$num = $temp["family_setting_id"] - 1;
					$num = (int) $num;
					$result[$num] = $temp;
				}
				ksort($result);
				$result[] = array();
				$formCreateRow->addData("phone", $result);
			
				// Get email info and put in table
				$emails = $familyRow->findFamilyEmail();
				$temps = $emails->toArray();
				$result = array();
				foreach ($temps as $temp) {
					$num = $temp["order"] - 1;
					$num = (int) $num;
					$result[$num] = $temp;
				}
				ksort($result);
				$result[] = array();
				$formCreateRow->addData("email", $result);
			}
			
			// Set new vars into view
			$formCreateRow->setForm();
		}
			
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		// List options
		$lists = new RList();
		$this->view->listOptions = $lists->getSelectOptions(null, null, array("" => "AUTO"));
		
		
		// Language options
		$languages = new Language();
		$this->view->languageOptions = $languages->getSelectOptions(null, "language");
		
		// Mother+Father ethnicity options
		$ethnicities = new Ethnicity();
		$this->view->ethnicityOptions = $ethnicities->getSelectOptions();
		
		// Email+Phone owner options
		$familyOwners = new FamilyOwner();
		$this->view->ownerOptions = $familyOwners->getSelectOptions();
		
		// Contact source options
		$contactSource = new ContactSource();
		$this->view->sourceOptions = $contactSource->getSelectOptions();
		
		// State options
		$family = new Family();
		$this->view->stateOptions = $family->getStates();
		
	}
	
	/**
	 * Creates a new entry for a baby, family, etc
	 *
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function createRow($tables)
	{
		# Check if family id, given
		# If so then just update family info
		# Also check if phone already part of family info, if so then update
		
		// Family first (must have something to submit)
		if (empty($tables['Family']))
			throw new Exception("No family information submitted!");
	
		// Check if family id submitted as parameter
		$familyId = (int) $this->_getParam('family_id');
		$family = $tables['Family'][0];
		if (empty($familyId)) {
			$familyId = $family->insert();
		} else {
			$where = $family->getAdapter()->quoteInto("id = ?", $familyId);
			$data = $family->getFilteredData();
			$family->update($data, $where);
			$familyRow = $family->fetchRow($where);
			
			// Get old phones
			$fpOld = $familyRow->findFamilyPhone();
			foreach ($fpOld as $fp)
				$fps[$fp->family_setting_id] = $fp;
			// Get old emails
			$feOld = $familyRow->findFamilyEmail();
			foreach ($feOld as $fe)
				$fes[$fe->order] = $fe;
		}
		
		// Update phones (some can be new and some can be gone)
		foreach ($tables['FamilyPhone'] as $table) {
			$fpData = $table->getFilteredData();
			if (empty($fps) == false and array_key_exists($fpData["family_setting_id"], $fps)) {
				$oldRow = $fps{$fpData["family_setting_id"]};
				$where = $table->getAdapter()->quoteInto("phone_number = ?", $oldRow->phone_number);
				$table->update(array('family_id' => $familyId), $where);
				unset($fps{$fpData["family_setting_id"]});
			} else {
				$table->insert(array('family_id' => $familyId));
			}
		}
		// If any old tables left in $fps, then delete
		if (!(empty($fps))) {
			foreach ($fps as $order => $oldRow)
				$oldRow->delete();
		}
		
		// Update emails
		foreach ($tables['FamilyEmail'] as $table) {
			$feData = $table->getFilteredData();
			if (empty($fes) == false and array_key_exists($feData["order"], $fes)) {
				$oldRow = $fes{$feData["order"]};
				$where = $table->getAdapter()->quoteInto("email = ?", $oldRow->email);
				$table->update(array('family_id' => $familyId), $where);
				unset($fes{$feData["order"]});
			} else {
				$table->insert(array('family_id' => $familyId));
			}
		}
		// If any old tables left in $fes, delete
		if (!(empty($fes))) {
			foreach ($fes as $order => $oldRow)
				$oldRow->delete();
		}
			
		// Add baby info
		if (empty($tables['Baby']))
			throw new Exception("No baby information submitted!");
		$baby = $tables['Baby'][0];
		$babyId = $baby->insert(array('family_id' => $familyId, 'status_id' => 1, 'entry_by_caller' => $_SESSION['caller_id']));
		
		// Add baby languages (if any)
		if (!(empty($tables["BabyLanguage"]))) {
			// Init vars
			$i = 0;
			$totalPercent = 0;
			// Set order field as order inserted
			foreach ($tables['BabyLanguage'] as $table) {
				// Add percent
				$blData = $table->getFilteredData();
				$totalPercent = (int) ($totalPercent + $blData["percent_per_week"]);
				// Insert data (with updated order)
				$i++;
				$table->insert(array('baby_id' => $babyId, "order" => $i));
			}
			// Check percent
			if ($totalPercent != 100 and $totalPercent != 0)
				throw new Zend_Controller_Action_Exception("Your language percentages add up to '{$totalPercent}' and not to 100");
		}
			
		// Save baby id
		$this->_setParam("baby_id", $babyId);
				
		return;
	}
	
	function editAction()
	{
		$this->view->type = "edit";
		
		// Get id for baby table
		$id = (int) $this->_getParam('baby_id');
		if (empty($id))
			throw new Exception('No id to edit given.');
			
		// Get if this is a common page
		$this->view->isCommon = (int) $this->_getParam('common');
		
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		
		/* Process Form */
		
		$formUpdateRow = $this->_helper->FormUpdate;
		
		$sectionsToTables = array(
			"baby"		=> "Baby",
			"language"	=> "BabyLanguage",
			"family"	=> "Family",
			"phone"		=> "FamilyPhone",
			"email"		=> "FamilyEmail"
		);
		
		$result = $formUpdateRow->processForm($sectionsToTables, $submitCheck = null, $dbFunction = array($this, "updateRow"), $manualNewForm = True);
				
		// Successful form, redirect to some other page
		if($result == 0) {
			$babyId	= $this->_getParam("baby_id");
			$this->_forward("success", "baby");
		}
		else if ($result == 1) {
			$languageData = $formUpdateRow->getData("language");
		}
		// New form, populate fields
		else if ($result == -1) {
			$newData = array();
					
			// Get/add baby table
			$babies = new Baby();
			$where = $babies->getAdapter()->quoteInto("id = ?", $id);
			$baby = $babies->fetchRow($where);
			if ($baby === null)
				throw new Zend_Controller_Action_Exception("Could not find baby with the id: $id");
			$newData["baby"] = $baby->toArray();
			
			// check birth weight
			$bweight = $newData["baby"]["birth_weight"];
			if (isset($bweight) and $bweight < 5.4) {
				// add warning
				$formUpdateRow->addWarnings("baby", array("birth_weight" => "Possible premie: birth weight is less than 5.4 pounds"));
			}
			// check term length
			$term = $newData["baby"]["term"];
			if (isset($term) and $term < 37) {
				// add warning
				$formUpdateRow->addWarnings("baby", array("term_weeks" => "Possible premie: birth term is less than 37 weeks."));				
			}
			
			// Get/add baby languages
			$languages = $baby->findBabyLanguage();
			$temps = $languages->toArray();
			foreach ($temps as $temp) {
				$num = $temp["order"] - 1;
				$num = (int) $num;
				$newData["language"][$num] = $temp;
			}
			ksort($newData["language"]);
			$languageData = $newData["language"];
			$newData["language"][] = array();
			
		    // Get baby age today
		    $calculator = new Zarrar_AgeCalculator();
    		$calculator->setDob($newData["baby"]["dob"])
    				   ->setDate(date('Y-m-d'));
		    $this->view->babyAge = $calculator->getAge("full");
		    
			
			// Get family table
			$family = $baby->findParentFamily();
			$newData["family"] = $family->toArray();
			$familyId = $family->id;
			
			// Get phone info
			$phones = $family->findFamilyPhone();
			$temps = $phones->toArray();
			foreach ($temps as $temps) {
				$num = $temps["family_setting_id"] - 1;
				$num = (int) $num;
				$newData["phone"][$num] = $temps;
			}
			ksort($newData["phone"]);
			$newData["phone"][] = array();
			
			// Get email info
			$emails = $family->findFamilyEmail();
			$temps = $emails->toArray();
			foreach ($temps as $temp) {
				$num = $temp["order"] - 1;
				$num = (int) $num;
				$newData["email"][$num] = $temp;
			}
			ksort($newData["email"]);
			$newData["email"][] = array();
					
			// Set new data
			$formUpdateRow->setNewForm($newData);
		}
		
		// Set languages
		if ($languageData) {
			$specialLanguages = array();
			foreach ($languageData as $key => $blanguage) {
				if(!(empty($blanguage))) {
					// Get db adapter
					$db = Zend_Registry::get('db');
					// Build sql query
					$query = "SELECT l.language FROM languages AS l WHERE l.id = ?";
					// Execute query
					$stmt = $db->query($query, $blanguage["language_id"]);
					$stmt->execute();
					$result = $stmt->fetchAll();
					$result = $result[0]["language"];
					// Set view param
					$specialLanguages[] = $result;
				}
			}
			if (!(empty($specialLanguages))) {
				$this->view->specialLanguages = $specialLanguages;
				if ($specialLanguages[4])
					$this->view->specialLanguage = $specialLanguages[4];
			}			
		}
					
		// Set new vars into view
		$formUpdateRow->setForm();
		
		
		/* Display Info */
		
		// Ability to adjust record and scheduling status
		if ($_SESSION["user_privelages"] == "admin" or $_SESSION["user_privelages"] == "coordinator") {
			$this->view->showStatusFields = True;
			
			// Status options
			$statuses = new Status();
			$statusOptions = $statuses->getSelectOptions();
			foreach ($statusOptions as $k => $v)
				$statusOptions[$k]=strtoupper($v);
			$this->view->statusOptions = $statusOptions;
		}
		
		// List options
		$lists = new RList();
		$listOptions = $lists->getSelectOptions();
		$this->view->listOptions = $listOptions;
		
		// Language options
		$languages = new Language();
		$this->view->languageOptions = $languages->getSelectOptions();
		
		// Mother+Father ethnicity options
		$ethnicities = new Ethnicity();
		$this->view->ethnicityOptions = $ethnicities->getSelectOptions();
		
		// Email+Phone owner options
		$familyOwners = new FamilyOwner();
		$this->view->ownerOptions = $familyOwners->getSelectOptions();
		
		// Contact source options
		$contactSource = new ContactSource();
		$this->view->sourceOptions = $contactSource->getSelectOptions();
		
		// State options
		$familyTbl = new Family();
		$this->view->stateOptions = $familyTbl->getStates();
		
		// Get data
		$data = $formUpdateRow->getData();
		$babyData = $data["baby"];
		
		// Serial Number
		$this->view->baby_id = $babyData["id"];
		
		// Family id
		$this->view->family_id = $babyData["family_id"];
		
		// Record Status
		$this->view->record_status = ($babyData["checked_out"] == 1) ? "ACTIVE" : "INACTIVE" ;
		
		// Status
		$statusTbl = new Status();
		$this->view->status = strtoupper($statusTbl->getStatus($babyData["status_id"]));
		
		// List
		$listTbl = new RList();
		$this->view->rlist = $listTbl->getList($babyData["list_id"]);
		
		// Date of first entry
		$this->view->date_of_entry = $babyData["date_of_entry"];
		
		// Last updated
		$this->view->last_update = $babyData["last_update"];
		
		// Baby name
		$this->view->name = $babyData["first_name"] . " " . $babyData["last_name"];
		
		// Caller who entered baby
		$callerId = $babyData["entry_by_caller"];
		if (!empty($callerId)) {
			$callerTbl = new Callers();
			$callerRowset = $callerTbl->find($callerId);
			$callerRow = $callerRowset->current();
			$this->view->caller = $callerRow->name;
		}
		
		// Siblings
		$familyRowset = $familyTbl->find($babyData["family_id"]);
		$familyRow = $familyRowset->current();
		$familySelect = $familyTbl->select();
		$familySelect->from(new Baby(), array('id'));
		$familyBabies = $familyRow->findDependentRowset('Baby', 'Family', $familySelect);
		$this->view->siblings = $familyBabies;
		$this->view->numSiblings = count($familyBabies) - 1;
		
		return;
	}
	
	protected function _archive($babyId, $setComments=NULL)
	{
		// Get baby row
		$babyTbl = new Baby();
		$where = $babyTbl->getAdapter()->quoteInto("id = ?", $babyId);
		$baby = $babyTbl->fetchRow($where);
		
		// Add comments
		if ($setComments)
			$baby->comments = $setComments;	
		
		// Remove involvement in any current studies
		$studyOwners = new BabyStudy();
		$where = $babyTbl->getAdapter()->quoteInto("baby_id = ?", $babyId);
		$results = $studyOwners->fetchAll($where);
		if (count($results) > 0) {
			foreach ($results as $result) {
				// Add to study histories
				$info = array(
					"baby_id"			=> $result->baby_id,
					"study_id"			=> $result->study_id,
					"appointment"		=> $result->appoinment,
					"study_outcome_id"	=> 3,
					"allow_further"		=> 0,
					"comments"			=> "{$result->comments}." . PHP_EOL . "  Baby was archived as parent was no longer interested (this sentence is an automatically generated comment)."
				);
				$studyHistory = new StudyHistory();
				$studyHistory->filterInsert($info);
				// Delete study owner
				$result->delete();
			}
		}
		
		// Set baby status as inactive and scheduling as 2 and save
		$baby->checked_out = 0;
		$baby->status_id = 2;
		$baby->save();
	}
	
	function updateRow($tables)
	{
		// Get id for baby table
		$babyId = (int) $this->_getParam('baby_id');	
		
		// Update Baby
		$babyTbl = $tables["Baby"][0];
		$babyTbl->update(array(), array(), true);
		
		// Check if status_id = 2 (or ARCHIVED)
		$babyData = $babyTbl->getFilteredData();
		$statusId = $babyData["status_id"];
		if ($statusId == 2) {
			$this->_helper->redirector("archive", "baby", null, array("id" => $babyId));
		}
		
		// Update family
		$familyTbl = $tables["Family"][0];
		$familyData = $familyTbl->getFilteredData();
		$familyId = $familyData["id"];
		$familyTbl->update(array(), array(), true);
		
		// Get baby row
		$where = $babyTbl->getAdapter()->quoteInto("id = ?", $babyId);
		$baby = $babyTbl->fetchRow($where);
				
		// Get family row
		$where = $familyTbl->getAdapter()->quoteInto("id = ?", $familyId);
		$family = $familyTbl->fetchRow($where);
		
		# have order for language
		# 
		
		// Get old languages		
		$blOld = $baby->findBabyLanguage();
		foreach ($blOld as $bl)
			$bls[$bl->order] = $bl;
		// Get old phones
		$fpOld = $family->findFamilyPhone();
		foreach ($fpOld as $fp)
			$fps[$fp->family_setting_id] = $fp;
		// Get old emails
		$feOld = $family->findFamilyEmail();
		foreach ($feOld as $fe)
			$fes[$fe->order] = $fe;

		// Add/Update baby languages (if any)
		if (!(empty($tables["BabyLanguage"]))) {		
			// Init vars
			$i = 0;
			$totalPercent = 0;
			// Update languages (some can be new and some can be gone/deleted!)
			foreach ($tables['BabyLanguage'] as $table) {
				// Get table data
				$blData = $table->getFilteredData();
				// Add percent
				$totalPercent = (int) ($totalPercent + $blData["percent_per_week"]);
				// Update or insert (with revised order)
				$i++;
				if (empty($bls) == false and array_key_exists($blData["order"], $bls)) {
					$oldRow = $bls{$blData["order"]};
					$where = array(
						$table->getAdapter()->quoteInto("baby_id = ?", $babyId),
						$table->getAdapter()->quoteInto("language_id = ?", $oldRow->language_id)
					);
					$table->update(array('baby_id' => $babyId, "order" => $i), $where);
					unset($bls{$blData["order"]});
				} else {
					$table->insert(array('baby_id' => $babyId, "order" => $i));
				}
			}
			// Check percent
			if ($totalPercent != 100 and $totalPercent != 0)
				throw new Zend_Controller_Action_Exception("Your language usage percentages add up to '{$totalPercent}' and not to 100");
		}
		// Delete old baby language rows that not seen in form data
		if(!(empty($bls))) {
			foreach ($bls as $order => $oldRow)
				$oldRow->delete();
		}

		// Update phones (some can be new)
		foreach ($tables['FamilyPhone'] as $table) {
			$fpData = $table->getFilteredData();
			if (empty($fps) == false and array_key_exists($fpData["family_setting_id"], $fps)) {
				$oldRow = $fps{$fpData["family_setting_id"]};
				$where = $table->getAdapter()->quoteInto("phone_number = ?", $oldRow->phone_number);
				$table->update(array('family_id' => $familyId), $where);
				unset($fps{$fpData["family_setting_id"]});
			} else {
				$table->insert(array('family_id' => $familyId));
			}
		}
		// Delete old rows that not seen in form data
		if(!(empty($fps))) {
			foreach ($fps as $order => $oldRow)
				$oldRow->delete();
		}
			
		// Update emails
		foreach ($tables['FamilyEmail'] as $table) {
			$feData = $table->getFilteredData();
			if (empty($fes) == false and array_key_exists($feData["order"], $fes)) {
				$oldRow = $fes{$feData["order"]};
				$where = $table->getAdapter()->quoteInto("email = ?", $oldRow->email);
				$table->update(array('family_id' => $familyId), $where);
				unset($fes{$feData["order"]});
			} else {
				$table->insert(array('family_id' => $familyId));
			}
		}
		// Delete old rows that not seen in form data
		if(!(empty($fes))) {
			foreach ($fes as $order => $oldRow)
				$oldRow->delete();
		}
		
		return;
	}
	
	function deleteAction()
	{
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->distinct()
			->from(array('b' => 'babies'),
		        array('id'))
		    ->join(array('l' => 'baby_languages'),
		        'l.baby_id = b.id', array())
			->join(array('f' => 'families'),
				'b.family_id = f.id', array('family_id' => 'id'))
			->join(array('e' => 'family_emails'),
				'f.id = e.family_id', array())
			->join(array('p' => 'family_phones'),
				'f.id = p.family_id', array())
			->where(array('statement = ?' => "O'Reily"));
		echo $select;
		echo "<br><br>";
		$baby_table = new Baby();
		$info = $baby_table->info();
		var_dump($info['cols']);
		#$date_diff = new Zend_Db_Expr("DATEDIFF(NOW(), dob)");
		#$baby_table = new Baby();
		#$where = array(
		#            "birth_weight BETWEEN 1 AND 4"
		#			);
		#$rows = $baby_table->fetchAll($where);
		#var_dump($rows->current()->toArray());
		exit();
	}
	
	function listAction()
	{
		require_once 'Pager/Pager.php';
		$db = Zend_Registry::get('db');
		
		// Setup classes
		$this->session = new Zend_Session_Namespace('list');
		$this->session->perPage = 1;
		
		$page = $this->_getParam('page');
		$page = (empty($page)) ? 1 : $page ;
		$data = array();
		
		$select = $db->select()
			->distinct()
			->from(array('b' => 'babies'),
		        array('id', 'dob', 'sex'))
		    ->joinLeft(array('l' => 'baby_languages'),
		        'l.baby_id = b.id', array())
			->joinLeft(array('f' => 'families'),
				'b.family_id = f.id', array('family_id' => 'id', 'mother_first_name', 'mother_last_name'));

		$filters = $this->session->filters;
		foreach ($filters as $key => $filter) {
			switch ($key) {
				// Find kids with dates of birth BETWEEN two dates
				case 'dob':
					$select->where("dob BETWEEN " . $db->quote($filter[0]) . " AND " . $db->quote($filter[1]));
					break;
				// Find kids who were entered into the db BETWEEN two dates
				case 'date_of_entry':
					$select->where("date_of_entry BETWEEN " . $db->quote($filter[0]) . " AND " . $db->quote($filter[1]));
					break;
				case 'language':
					$select->where("language_id IN(" . implode(',', $filter) . ")");
					break;
				default:
					break;
			}
		}
		
		if (!($this->session->sortby))
			$select->order($this->select->sortby);
		
		$stmt = $select->query();
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Check row exists
		if (count($result) < 1)
			throw new Exception('Either no rows found for id parameter or more than one found');
		
		$params = array(
		    'mode'      => 'Sliding',
			'delta'		=> 2,
		    'append'    => false,
			'currentPage' => $page,
		    'path'      => $this->view->url(array('controller' => $this->view->controller, 'action' => $this->view->action), null, true),
		    'fileName'  => 'page/%d',
		    'itemData'  => $result,
		    'perPage'   => $this->session->perPage
		);
		$pager =& Pager::factory($params);
		
		// Columns to display
		$this->view->columns = array_keys($result[0]);
		$this->view->results = $pager->getPageData();
		$this->view->links = $pager->links;
	}
	
	function searchAction()
	{
		/**
		 * Process Form
		 **/
		
		$params = array();
		
		// Process form
		$this->_form = $this->_helper->FormSearch;
		$result = $this->_form->processForm();
		$formData = $this->_form->getData();
				
		if ($result == 0 or $result == 1) {
			// Set caller call date range
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateFrom = $arrayToDate->filter($formData["caller"]['date_from']);
			if ($dateFrom != "") {
				$formData["caller"]["date_from"] = $dateFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateFrom))) {
					$this->_form->pushData("caller", "date_from", $dateFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["date_from"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateTo = $arrayToDate->filter($formData["caller"]['date_to']);
			if ($dateTo != "") {
				$formData["caller"]["date_to"] = $dateTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateTo))) {
					$this->_form->pushData("caller", "date_to", $dateTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["date_to"]);
			}
			
			// Set callback date range
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateFrom = $arrayToDate->filter($formData["caller"]['callback_date_from']);
			if ($dateFrom != "") {
				$formData["caller"]["callback_date_from"] = $dateFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateFrom))) {
					$this->_form->pushData("caller", "callback_date_from", $dateFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["callback_date_from"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateTo = $arrayToDate->filter($formData["caller"]['callback_date_to']);
			if ($dateTo != "") {
				$formData["caller"]["callback_date_to"] = $dateTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateTo))) {
					$this->_form->pushData("caller", "callback_date_to", $dateTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["callback_date_to"]);
			}
			
			// Set callback TIME range
			$arrayToTime = new Zarrar_Filter_ArrayToTime();
			$timeBegin = $arrayToTime->filter($formData["caller"]['callback_time_begin']);
			if ($timeBegin != "") {
				$formData["caller"]["callback_time_begin"] = $timeBegin;
				// Validate
				if (strlen($timeBegin)!=8) {
					$this->_form->pushData("caller", "callback_time_begin", $timeBegin);
					$this->_form->addError("Callback time given is invalid '${timeBegin}'");
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["callback_time_begin"]);
			}
			$arrayToTime = new Zarrar_Filter_ArrayToTime();
			$timeEnd = $arrayToTime->filter($formData["caller"]['callback_time_end']);
			if ($timeEnd != "") {
				$formData["caller"]["callback_time_end"] = $timeEnd;
				// Validate
				if (strlen($timeEnd)!=8) {
					$this->_form->pushData("caller", "callback_time_end", $timeEnd);
					$this->_form->addError("Callback time given is invalid '${timeEnd}'");
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["caller"]["callback_time_end"]);
			}
			if ( ( $timeBegin == "" && $timeEnd != "" ) || ( $timeBegin != "" && $timeEnd == "" ) ) {
				$this->_form->pushData("caller", "callback_time_begin", $timeBegin);
				$this->_form->pushData("caller", "callback_time_end", $timeEnd);
				$this->_form->addError("Must specify both callback time field or none at all.");
				$this->_form->setForm();
				$result = 1;
			}
			
			// Set study date range
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateFrom = $arrayToDate->filter($formData["study"]['date_from']);
			if ($dateFrom != "") {
				$formData["study"]["date_from"] = $dateFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateFrom))) {
					$this->_form->pushData("study", "date_from", $dateFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["study"]["date_from"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$dateTo = $arrayToDate->filter($formData["study"]['date_to']);
			if ($dateTo != "") {
				$formData["study"]["date_to"] = $dateTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($dateTo))) {
					$this->_form->pushData("study", "date_to", $dateTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["study"]["date_to"]);
			}
		
			// Set dob
			// Filter array to string
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$babyDobTo = $arrayToDate->filter($formData["baby"]['dob_to']);
			if ($babyDobTo != "") {
				$formData["baby"]["dob_to"] = $babyDobTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($babyDobTo))) {
					$this->_form->pushData("baby", "dob_to", $babyDobTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["dob_to"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$babyDobFrom = $arrayToDate->filter($formData["baby"]['dob_from']);
			if ($babyDobFrom != "") {
				$formData["baby"]["dob_from"] = $babyDobFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($babyDobFrom))) {
					$this->_form->pushData("baby", "dob_from", $babyDobFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["dob_from"]);
			}
			
			// Set record date range
			// created
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$createdFrom = $arrayToDate->filter($formData["baby"]['created_from']);
			if ($createdFrom != "") {
				$formData["baby"]["created_from"] = $createdFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($createdFrom))) {
					$this->_form->pushData("baby", "created_from", $createdFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["created_from"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$createdTo = $arrayToDate->filter($formData["baby"]['created_to']);
			if ($createdTo != "") {
				$formData["baby"]["created_to"] = $createdTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($createdTo))) {
					$this->_form->pushData("baby", "created_to", $createdTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["created_to"]);
			}
			// updated
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$updatedFrom = $arrayToDate->filter($formData["baby"]['updated_from']);
			if ($updatedFrom != "") {
				$formData["baby"]["updated_from"] = $updatedFrom;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($updatedFrom))) {
					$this->_form->pushData("baby", "updated_from", $updatedFrom);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["updated_from"]);
			}
			$arrayToDate = new Zarrar_Filter_ArrayToDate();
			$updatedTo = $arrayToDate->filter($formData["baby"]['updated_to']);
			if ($updatedTo != "") {
				$formData["baby"]["updated_to"] = $updatedTo;
				// Validate
				$validDate = new Zend_Validate_Date();
				if (!($validDate->isValid($updatedTo))) {
					$this->_form->pushData("baby", "updated_to", $updatedTo);
					$this->_form->addError(array_values($validDate->getMessages()));
					$this->_form->setForm();
					$result = 1;
				}
			} else {
				unset($formData["baby"]["updated_to"]);
			}
			
			// Get weight + weight type
			$weight = $formData["baby"]['birth_weight'];
			$weightType = $formData["baby"]['birth_weight_type'];

			// Create measurement
			if (!(empty($weight))) {
				switch ($weightType) {
					case 'gram':
						$unit = new Zend_Measure_Weight($weight, Zend_Measure_Weight::GRAM);
						break;
					case 'pound':
						$unit = new Zend_Measure_Weight($weight, Zend_Measure_Weight::POUND);
						break;
					default:
						throw new Zend_Db_Table_Exception("Weight type '$weightType' not recognized!");
						break;
				}

				// Set weight (in grams)
				$unit->setType(Zend_Measure_Weight::GRAM);
				$formData["baby"]['birth_weight'] = $unit->getValue();
			}

			// Don't want to look at birth_weight_type
			unset($formData["baby"]['birth_weight_type']);
			
			
			/* Term length */

			$term = '';
			$term_period = $formData["baby"]['term_period'];
			$term_weeks = $formData["baby"]['term_weeks'];

			// Set term (can't have both term_period and term_weekds defined)
			if (!(empty($term_period)) and !(empty($term_weeks)))
				$this->_filter->addErrorMessage('term', "Cannot put values in for both term period '{$term_period}' or term weeks '{$term_weeks}'");
			elseif (!(empty($term_period)))
				$term = 40 + $term_period;
			elseif (!(empty($term_weeks)))
				$term = $term_weeks;

			// Set 'term' and take out term_period and term_weeks
			if ($term != "")
				$formData["baby"]['term'] = $term;
			unset($formData["baby"]['term_period']);
			unset($formData["baby"]['term_weeks']);
			
			// Change record_status
			if (!(empty($formData["baby"]["record_status"])))
				$formData["baby"]["record_status"] = $formData["baby"]["record_status"] - 1;
		}	
				
		// Data submitted successfully
		if ($result == 0 and $this->getRequest()->getPost("baby_search")) {
			unset($formData["baby_search"]);
			$return = $this->_prepareSearch($formData);
			// Oh no, not that successful
			if ($return === false)
				$this->_form->addError("Search returned 0 rows. Please try something else.", "count");
			// Set form for redisplay
			$this->_form->setForm();
		}
		
		if ($result == -1) {
			//$curdate = date("Y-m-d");
			//$this->_form->pushData("study", "checkout", $curdate);
			//$this->_form->pushData("baby", "dob", $curdate);
			//$this->_form->setForm();
		}
		
		# If Admin or Coordinator, show study options
		
		// Options
		
		// List Options
		$lists = new RList(); 
		$this->view->listOptions = $lists->getSelectOptions();
		
		// Status options
		$statuses = new Status();
		$this->view->statusOptions = $statuses->getSelectOptions();
		
		// Language options
		$languages = new Language();
		$this->view->languageOptions = $languages->getSelectOptions();
		
		// Mother+Father ethnicity options
		$ethnicities = new Ethnicity();
		$this->view->ethnicityOptions = $ethnicities->getSelectOptions();
		
		// Get form select options for record owners
		// based on user lab affliation and/or access level
		$study = new Study();
		$ownerOptions = $study->getRecordOwners();
		$this->view->ownerOptions = $ownerOptions;
		
		// Get outcome options
		$outcome = new StudyOutcome();
		$this->view->outcomeOptions = $outcome->getSelectOptions();
		
		// Get callers
		$callers = new Callers();
		if ($_SESSION["user_privelages"] == "admin" or $_SESSION["user_privelages"] == "coordinator") {
			$this->view->callerOptions = $callers->getSelectOptions(null, null, array("" => "All"));
		} else {
			$callersRow = $callers->fetchRow($callers->select()->where("id = ?", $_SESSION['caller_id']));
			$callersSelect = array(
				'columns'	=> array('id', 'name'),
				'where'		=> array("to_use = 1", "lab_id = {$callersRow->lab_id}")
			);
			$this->view->callerOptions = $callers->getSelectOptions($callersSelect, null, array("" => "All"));
		}
		$this->view->notCallerOptions = $this->view->callerOptions;
		$this->view->notCallerOptions[""] = "None";
		
		/****
		 * CONTACT INFO
		 ****/
		
		$contactHistory = new ContactHistory();
		
		// Contact Type
		$this->view->contactTypeOptions = $contactHistory->getRefSelectOptions("ContactType", "Type");
		
		// Contact Method
		$this->view->contactMethodOptions = array("" => "Choose", "Phone" => "Phone", "Email" => "Email", "Mail" => "Mail");
		
		// Contact Outcome
		$this->view->contactOutcomeOptions = $contactHistory->getRefSelectOptions('ContactOutcome', "Outcome");
		
		// State options
		$family = new Family();
		$this->view->stateOptions = $family->getStates();
		
		// Set Study Options
		// Setup query for researcher + study options
		$db = Zend_Registry::get('db');
		$selectOptions = $db->select()
			->distinct()
		 	// Get researcher_id from 'researchers' table
			->from(array('r' => 'researchers'),
		        array("researcher_id" => "id", "researcher"))
			// Get associated lab
		    ->joinLeft(array('l' => 'labs'),
		        'r.lab_id = l.id', array("lab_id" => "id", "lab"))
			// Get the lab associated with callers
			->joinLeft(array("c" => "callers"),
				"l.id = c.lab_id", array())
			// Get the studies associated with a given researcher
			->joinLeft(array("s" => "studies"),
				"r.id = s.researcher_id", array("study_id" => "id", "study"));

		if (!($_SESSION['user_privelages'] == "admin" or $_SESSION['user_privelages'] == "coordinator")) {
			$selectOptions->where("c.id = ?", $_SESSION['caller_id']);
		}

		// Want only rows with active (to_use=1) researcher
		$selectOptions->where("r.to_use = ?", 1)
			// Also only want active (to_use=1) studies
			->where("s.to_use <=> ?", 1)
			// Don't want researcher of None
			// (This also prevents study of 'None' because it is linked to this researcher)
			->where("r.researcher != ?", "None")
			// Order by 'record_owner'
			->order("r.researcher");

		/* Execute Query */
		$stmt = $db->query($selectOptions);
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		// Create form select, researcher + study options
		$researcherOptions = array("" => "All");
		$studyOptions = array("" => "All");
		$labOptions = array("" => "All");
		foreach ($result as $key => $row) {
			$researcherOptions[$row['researcher_id']] = $row['researcher'];
			$studyOptions[$row['study_id']] = $row['study'];
			$labOptions[$row["lab_id"]] = $row["lab"];
		}
		$this->view->researcherOptions = $researcherOptions;
		$this->view->studyOptions = $studyOptions;
		$this->view->labOptions = $labOptions;
		
		// Second set for nots
		$researcherOptions = array("" => "None");
		$studyOptions = array("" => "None");
		$labOptions = array("" => "None");
		foreach ($result as $key => $row) {
			$researcherOptions[$row['researcher_id']] = $row['researcher'];
			$studyOptions[$row['study_id']] = $row['study'];
			$labOptions[$row["lab_id"]] = $row["lab"];
		}
		$this->view->notResearcherOptions = $researcherOptions;
		$this->view->notStudyOptions = $studyOptions;
		$this->view->notLabOptions = $labOptions;
		
		// Set level of enthusiasm options
		$enthusiasmOptions = array("" => "Choose") + array_combine(range(1,5), range(1,5));
		$this->view->enthusiasmOptions = $enthusiasmOptions;
	}
	
	protected function _prepareSearch(array $data)
	{	
		// Not post, whoops forward to index
		if (!($this->getRequest()->isPost()))
			$this->forward('search', 'baby', null, null);
		
		$params = array();
		
		/** 
		 * Want to display these COMMON columns:
		 * 	#, #, serial no, baby last, baby first, #, dob,
		 * 	sex, mother last, mother first, language
		 *	Status, Record Status, Record Owner
		 **/
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Build select query (using fluent interface)
		$select = $db->select()
		
		// Want distinct rows
			->distinct()
			
		// Group by baby's id
			->group('b.id')
		
		// Start from baby table + get baby information
			->from(array('b' => 'babies'),
	        	array('baby_id' => 'id', 'last_name', 'first_name', 'dob', 'sex', "record_status" => 'checked_out'))
	
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
				
		// Get record owners (study : researcher)
			->joinLeft(array('bs' => 'baby_studies'),
				'b.id <=> bs.baby_id', array())
			->joinLeft(array('s' => 'studies'),
				'bs.study_id = s.id', array())
			->joinLeft(array('r' => 'researchers'),
				's.researcher_id = r.id', array("record_owner" => new Zend_Db_Expr('CONCAT(r.researcher, " : ", s.study)')))
		// Get lab
			->joinLeft(array('labs' => 'labs'),
				"r.lab_id = labs.id", array())
		// Get list id
			->joinLeft(array('lists' => 'lists'),
				"b.list_id = lists.id", array("list"));
	
		/*
			BABY - POSSIBLE SEARCH PARAMATERS
			- id
			- Record Status
			- Status id
			- Date of entry (Range)
			- Last updated (Range)
			- first name
			- last name
			- sex
			- dob
			- bweight
			- term
			- ear infection
			- daycare
			- comments
		*/
				
		if (!(empty($data["baby"]))) {
			$baby = $data["baby"];
			
			if ($baby["id"])
				$select->where("b.id = ?", $baby["id"]);

            if ($baby["list_id"])
                $select->where("b.list_id = ?", $baby["list_id"]);

			if ($baby["record_status"] === 0 || $baby["record_status"] === 1)
				$select->where("b.checked_out = ?", $baby["record_status"]);
				
			if ($baby["status_id"])
				$select->where("b.status_id = ?", $baby["status_id"]);
				
			if ($baby["created_from"] and $baby["created_to"]) {
				$select->where(
					"b.date_of_entry" .
					" BETWEEN " . $db->quote($baby["created_from"]) . 
					" AND " . $db->quote($baby["created_to"])
				);
			} elseif ($baby["created_from"]) {
				$select->where("b.date_of_entry = ?", $baby["created_from"]);
			}
			
			if ($baby["updated_from"] and $baby["updated_to"]) {
				$select->where(
					"b.last_update" .
					" BETWEEN " . new Zend_Db_Expr("CAST(" . $db->quote($baby["updated_from"]) . " AS DATETIME)") . 
					" AND " . new Zend_Db_Expr("CAST(" . $db->quote($baby["updated_to"]) . " AS DATETIME)") .
					" OR " . $db->quoteInto("b.last_update LIKE ?", "{$baby["updated_to"]}%")
				);
			} elseif ($baby["updated_from"]) {
				$select->where("b.last_update LIKE ?", "{$baby["updated_from"]}%");
			}
				
			if ($baby["first_name"])
				$select->where("b.first_name LIKE ?", "%{$baby['first_name']}%");
			
			if ($baby["last_name"])
				$select->where("b.last_name LIKE ?", "%{$baby['last_name']}%");
				
			if ($baby["sex"])
				$select->where("b.sex = ?", $baby['sex']);
				
			if ($baby["dob_from"] and $baby["dob_to"]) {
				$select->where(
					"b.dob" .
					" BETWEEN " . $db->quote($baby["dob_from"]) . 
					" AND " . $db->quote($baby["dob_to"])
				);
			} elseif ($baby["dob_from"]) {
				$select->where("b.dob LIKE ?", "{$baby["dob_from"]}%");
			}
				
			if ($baby["birth_weight_pounds"] or $baby["birth_weight_ounces"]) {
				$pounds = ($baby["birth_weight_pounds"]) ? $baby["birth_weight_pounds"] : 0 ;
				$ounces = ($baby["birth_weight_ounces"]) ? $baby["birth_weight_ounces"]/16 : 0 ;
				$ounces = round($ounces, 2);
				$weight = $pounds + $ounces;
				$select->where("b.birth_weight LIKE ?", "{$weight}%");
			}
						
			if ($baby["term"])
				$select->where("b.term = ?", $baby["term"]);
				
			if ($baby["ear_infection"])
				$select->where("b.ear_infection = ?", $baby["ear_infection"]);
				
			if ($baby["daycare"])
				$select->where("b.daycare = ?", $baby["daycare"]);
				
			if ($baby["comments"])
				$select->where("b.comments LIKE ?", "%{$baby["comments"]}%");
		}
		
		/*
			Language (search for languages currently an AND query)
		*/
		
		foreach ($data["language"] as $key => $languageSet) {
			if (empty($languageSet["language_id"]))
				continue;
				
			$select->where("bl.language_id = ?", $languageSet['language_id']);
			
			// Add percentage to query, if not empty
			if (!(empty($languageSet['rate']))) {
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
		
		/*
			Family - POSSIBLE SEARCH PARAMATERS
			- id
			- mother name + ethnicity
			- father name + ethnicity
			- address (city, state, zip)
			- how heard
			- income
			- comments
		*/
		
		if (!(empty($data["family"]))) {
			$family = $data["family"];
			
			if ($family["id"])
				$select->where("f.id = ?", $family["id"]);
			
			/*Mother*/
			// First name
			($family['mother_first_name']) ? $select->where("f.mother_first_name LIKE ?", "%{$family['mother_first_name']}%") : "" ;
			// Last name
			($family['mother_last_name']) ? $select->where("f.mother_last_name LIKE ?", "%{$family['mother_last_name']}%") : "" ;
			// Ethnicity
			($family['mother_ethnicity_id']) ? $select->where("f.mother_ethnicity_id <=> ?", $family['mother_ethnicity_id']) : "" ;
			
			/*Father*/
			// First name
			($family['father_first_name']) ? $select->where("f.father_first_name LIKE ?", "%{$family['father_first_name']}%") : "" ;
			// Last name
			($family['father_last_name']) ? $select->where("f.father_last_name LIKE ?", "%{$family['father_last_name']}%") : "" ;
			// Ethnicity
			($family['father_ethnicity_id']) ? $select->where("f.father_ethnicity_id <=> ?", $family['father_ethnicity_id']) : "" ;
			
			/*Contact*/
			if ($family["city"])
				$select->where("f.city LIKE ?", "%{$family['city']}%");
			if ($family["state"])
				$select->where("f.state LIKE ?", "%{$family['state']}%");
			if ($family["zip"])
				$select->where("f.zip = ?", $family["zip"]); 
			
			// How Heard (search both how_heard and contact_source_id fields)
			if ($family["how_heard"]) {
				$select->joinLeft(array("cs" => "contact_sources"),
					"f.contact_source_id = cs.id", array());
				$select->where(
					$db->quoteInto("cs.source LIKE ?", "%{$family["how_heard"]}%")
					. " OR " . 
					$db->quoteInto("f.how_heard LIKE ?", "%{$family["how_heard"]}%")
				);
			}
			
			// Income
			if ($family["income"])
				$select->where("f.income LIKE ?", $family["income"]);
				
			// Comments
			if ($family["comments"])
				$select->where("f.comments LIKE ?", "%{$family["comments"]}%");
		}
		
		/*
			Contact - POSSIBLE SEARCH PARAMATERS
			- phone_number
			- email
		*/
		
		if (!(empty($data["contact"]))) {
			$contact = $data["contact"];
			
			if ($contact["phone_number"]) {
				// 1. Join table
				$select->joinLeft(array('fp' => 'family_phones'),
					'f.id = fp.family_id', array('telephone' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT fp.phone_number SEPARATOR ', ')")))
				
				// 2. Specify criteria
					->where("fp.phone_number LIKE ?", "%{$contact['phone_number']}%");
					
				// Save param to display column
				$params["telephone"] = 1;
			}
			
			if ($contact["email"]) {
				// 1. Join table
				$select->joinLeft(array('fe' => 'family_emails'),
					'f.id = fe.family_id', array())
				
				// 2. Specify criteria
					->where("fe.email LIKE ?", "%{$contact['email']}%");
			}
		}
		
		/*
			Study - POSSIBLE SEARCH PARAMATERS
			- checkout (date)
			- study_id (name) -> nothing if ALL
			- caller_id
			- researcher_id (name) -> nothing if ALL
			- lab_id (name) -> nothing if ALL
			- date (study)
			- time (study)
			- level of enthusiasm
			- outcome_id
		*/
		
		if (!(empty($data["study"]))) {
			$study = $data["study"];
			$outcomeId = (empty($study["outcome_id"])) ? 1 : $study["outcome_id"];

			// Join study history
			$select
				->joinLeft(array('sh' => 'study_histories'),
					'b.id <=> sh.baby_id', array())
				->joinLeft(array('s_sh' => 'studies'),
					'sh.study_id <=> s_sh.id', array())
				->joinLeft(array('r_sh' => 'researchers'),
					's_sh.researcher_id <=> r_sh.id', array());
				
			if ($study["checkout"]) {
				$select->where("b.checkout_date LIKE ?", $study["checkout"]);
			}
			
			if (!empty($study["study_id"])) {
				$select->where(
					$db->quoteInto("bs.study_id <=> ?", $study["study_id"])
					. " OR " .
					"(" .
						$db->quoteInto("sh.study_id <=> ?", $study["study_id"])
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
			
			if (!empty($study["caller_id"])) {
				$select->where(
					$db->quoteInto("bs.caller_id <=> ?", $study["caller_id"])
					. " OR " .
					"(" .
						$db->quoteInto("sh.caller_id <=> ?", $study["caller_id"])
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
				
			if (!empty($study["researcher_id"])) {
				$select->where(
					$db->quoteInto("r.id = ?", $study["researcher_id"])
					. " OR " .
					"(" .
						$db->quoteInto("r_sh.id = ?", $study["researcher_id"])
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
			
			if (!empty($study["lab_id"])) {
				$select->where(
					$db->quoteInto("labs.id = ?", $study["lab_id"])
					. " OR " .
					"(" .
						$db->quoteInto("r_sh.lab_id = ?", $study["lab_id"])
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
			
			if (!empty($study["not_study_id"])) {
				$select->where(
					"(" .
						$db->quoteInto("bs.study_id != ?", $study["not_study_id"])
						. " OR " .
						"(" .
							$db->quoteInto("sh.study_id != ?", $study["not_study_id"])
							. " AND " .
							$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
						. ")"
						. " IS NOT TRUE"
					. ")"
				);
			}
			
			if (!empty($study["not_caller_id"])) {
				$select->where(
					$db->quoteInto("bs.caller_id != ?", $study["not_caller_id"])
					. " OR " .
					"(" .
						$db->quoteInto("sh.caller_id != ?", $study["not_caller_id"])
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
				
			if (!empty($study["not_researcher_id"])) {
				$select->where(
					"(" .
						$db->quoteInto("r.id != ", $study["not_researcher_id"])
						. " OR " .
						"(" .
							$db->quoteInto("r_sh.id != ?", $study["not_researcher_id"])
							. " AND " .
							$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
						. ")"
						. " IS NOT TRUE"
					. ")"
				);
			}
			
			if (!empty($study["not_lab_id"])) {
				$select->where(
					"(" .
						$db->quoteInto("labs.id != ?", $study["not_lab_id"])
						. " OR " .
						"(" .
							$db->quoteInto("r_sh.lab_id != ?", $study["not_lab_id"])
							. " AND " .
							$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
						. ")"
						. " IS NOT TRUE"
					. ")"
				);
			}
			
			$appointmentFrom = trim($study["date_from"] . " " . $study["time_from"]);
			$appointmentTo = trim($study["date_to"] . " " . $study["time_to"]);
			
			if (!empty($appointmentFrom) and !empty($appointmentTo)) {
				$select->where(
					// Range from current studies
					"bs.appointment"
						. " BETWEEN " .
							new Zend_Db_Expr("CAST(" . $db->quote($appointmentFrom) . " AS DATETIME)")
						. " AND " .
							new Zend_Db_Expr("CAST(" . $db->quote($appointmentTo) . " AS DATETIME)")
					. " OR " . $db->quoteInto("bs.appointment LIKE ?", "{$appointmentTo}%")
					// Range from old studies (that were completed!)
					. " OR ("
						. "(sh.appointment"
						. " BETWEEN " .
							new Zend_Db_Expr("CAST(" . $db->quote($appointmentFrom) . " AS DATETIME)")
						. " AND " .
							new Zend_Db_Expr("CAST(" . $db->quote($appointmentTo) . " AS DATETIME)")
						. ")"
						. " AND " . $db->quoteInto("sh.study_outcome_id = ?", 1)
					. ")"
					. " OR (" .
						$db->quoteInto("sh.appointment LIKE ?", "{$appointmentTo}%")
						. " AND " .
						$db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			} elseif (!empty($appointmentFrom)) {
				$select->where(
					// Current studies
					$db->quoteInto("bs.appointment LIKE ?", "{$appointmentFrom}%")
					// Or
					. " OR " .
					// Old studies (that were completed)
					"("
						. $db->quoteInto("sh.appointment LIKE ?", "{$appointmentFrom}%")
						. " AND "
						. $db->quoteInto("sh.study_outcome_id = ?", $outcomeId)
					. ")"
				);
			}
			
			if ($study["enthusiasm"]) {
				$select->where("sh.level_enthusiasm = ?", $study["enthusiasm"]);
			}
			
			if ($study["outcome_id"]) {
				$select->where("sh.study_outcome_id = ?", $study["outcome_id"]);
			}
			
			if ($study["not_outcome_id"]) {
				$select->where("sh.study_outcome_id != ?", $study["not_outcome_id"]);
			}
		}
		
		/*
			Caller - POSSIBLE SEARCH PARAMATERS
			- caller_id
			- date
			- contact_type_id
			- contact_method
			- contact_outcome_id
			- callback date
			- study_id -> nothing if ALL
			- researcher_id -> nothing if ALL
			- lab_id -> nothing if ALL
		*/
		
		if (!(empty($data["caller"]))) {
			$caller = $data["caller"];

			// Join contact history
			$select
				->joinLeft(array('ch' => 'contact_histories'),
					'b.id <=> ch.baby_id', array())
				->joinLeft(array('s_ch' => 'studies'),
					'ch.study_id <=> s_ch.id', array())
				->joinLeft(array('r_ch' => 'researchers'),
					's_ch.researcher_id <=> r_ch.id', array());
			
			// Caller
			if (!empty($caller["caller_id"])) {
				$select->where("ch.caller_id = ?", $caller["caller_id"]);
			}
			
			// Call date range
			$callFrom = trim($caller["date_from"] . " " . $caller["time_from"]);
			$callTo = trim($caller["date_to"] . " " . $caller["time_to"]);
			if (!empty($callFrom) and !empty($callTo)) {
				$select->where(
					"ch.DATETIME"
						. " BETWEEN " .
							new Zend_Db_Expr("CAST(" . $db->quote($callFrom) . " AS DATETIME)")
						. " AND " .
							new Zend_Db_Expr("CAST(" . $db->quote($callTo) . " AS DATETIME)")
				);
			} elseif (!empty($callFrom)) {
				$select->where("ch.DATETIME = ?", new Zend_Db_Expr("CAST(" . $db->quote($callFrom) . " AS DATETIME)"));
			}
			
			// Contact Type
			if(!empty($caller["contact_type_id"])) {
				$select->where("ch.contact_type_id = ?", $caller["contact_type_id"]);
			}
			
			// Contact Method
			if(!empty($caller["contact_method"])) {
				$select->where("ch.contact_method = ?", $caller["contact_method"]);
			}
			
			// Contact Outcome
			if(!empty($caller["contact_outcome_id"])) {
				$select->where("ch.contact_outcome_id = ?", $caller["contact_outcome_id"]);
			}
			
			// Callback date range
			if (!empty($caller["callback_date_from"]) and !empty($caller["callback_date_to"])) {
				$select->where(
					"ch.callback_date"
						. " BETWEEN " .
							$db->quote($caller["callback_date_from"])
						. " AND " .
							$db->quote($caller["callback_date_to"])
				);
			} elseif (!empty($caller["callback_date_from"])) {
				$select->where("ch.callback_date = ?", $caller["callback_date_from"]);
			}
			
			// Callback time range
			if (!empty($caller["callback_time_begin"]) and !empty($caller["callback_time_end"])) {
				$select->where(
					"ch.callback_time_begin"
						. " BETWEEN " .
							$db->quote($caller["callback_time_begin"])
						. " AND " .
							$db->quote($caller["callback_time_end"])
						. " OR " .
					"ch.callback_time_end"
						. " BETWEEN " .
							$db->quote($caller["callback_time_begin"])
						. " AND " .
							$db->quote($caller["callback_time_end"])
				);
			}
			
			// Study
			if (!empty($caller["study_id"])) {
				$select->where("ch.study_id = ?", $caller["study_id"]);
			}
			
			// Researcher
			if (!empty($caller["researcher_id"])) {
				$select->where("ch.researcher_id = ?", $caller["researcher_id"]);
			}
			
			// Lab
			if (!empty($caller["lab_id"])) {
				$select->where("r_ch.lab_id = ?", $caller["lab_id"]);
			}
		}
		
		
		
		// Save query
		$query = $select->__toString();
		// Save records per page option (10 for now)
		$perPage = 50;
		
		// Get count of rows
		$select->reset(Zend_Db_Select::GROUP);
		$select->reset(Zend_Db_Select::COLUMNS);
		$select->from(null, "COUNT(DISTINCT b.id) AS count");
		$stmt = $select->query();
		$stmt->execute();
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
		$_SESSION["baby_search"] = $params;
		$this->_forward("search-results", "baby", null);
	}
	
	function searchResultsAction()
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
		
		// Get params
		$params = $_SESSION["baby_search"];
		
		
		/* Get params */
		
		// Current page number (default 1)
		$pageNum = ($this->_getParam("page")) ? $this->_getParam("page") : 1 ;
		
		// Order table by field (default baby's id or serial no)
		$sort = ($this->_getParam("sort")) ? $this->_getParam("sort") : "baby_id" ;
		
		// Direction to order table (default ascending)
		$order = ($this->_getParam("order")) ? $this->_getParam("order") : "ASC" ;
		
		// Do I want to add anything to url fields
		$addUrlFields = array();
		
		if ($params["telephone"])
			$addUrlFields[] = "telephone";
		
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
		    'path'      	=> $this->view->url(array("controller" => "baby", "action" => "search-results"), null, true),
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
		$urlFields = array_merge(array("baby_id", "status", "record_status", "record_owner", "last_name", "first_name", "dob", "sex", "mother_first_name", "mother_last_name", "language", "list"), $addUrlFields);
				
		// create the urls
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "baby", "action" => 'search-results', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		
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
	
	function searchResultsExcelAction()
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
		
		// Get params
		$params = $_SESSION["baby_search"];
		
		
		/* Get params */
		
		// Current page number (default 1)
		$pageNum = ($this->_getParam("page")) ? $this->_getParam("page") : 1 ;
		
		// Order table by field (default baby's id or serial no)
		$sort = ($this->_getParam("sort")) ? $this->_getParam("sort") : "baby_id" ;
		
		// Direction to order table (default ascending)
		$order = ($this->_getParam("order")) ? $this->_getParam("order") : "ASC" ;
		
		// Do I want to add anything to url fields
		$addUrlFields = array();
		
		if ($params["telephone"])
			$addUrlFields[] = "telephone";
		
		/* Build/Execute Final Query */
		
		// Get db adapter
		$db = Zend_Registry::get('db');
		
		// Additional part of query only
		$addition = $db->select()
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
		
		$this->_helper->layout->setLayout('excel');
		
		
		/* Setup column header links to sort column */
		
		// common fields to setup links for
		$urlFields = array_merge(array("baby_id", "status", "record_status", "record_owner", "last_name", "first_name", "dob", "sex", "mother_first_name", "mother_last_name", "language"), $addUrlFields);
				
		// create the urls
		foreach ($urlFields as $field)
			$link[$field] = array("controller" => "baby", "action" => 'search-results', 'sort' => $field, 'order' => ($sort == $field and $order == 'ASC') ? 'DESC' : 'ASC');
		
		
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
