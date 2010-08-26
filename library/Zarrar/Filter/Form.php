<?php

/**
 * Processes form data if isPost
 *
 * This means that it will filter + validate data
 * and then either:
 *	a) spit data back to user if error
 *	b) insert/update db table(s)
 *	c) spit data back to user if clean,
 *	this may be desired if you want to something
 *	other than insert/update db tables
 *
 * @todo
 *	1) add option to process data if ajax request
 *
 * @author Zarrar Shehzad
 **/
abstract class Zarrar_Filter_Form_Abstract
{


	/**
	 * Will set the specified action
	 * and set formData if given
	 * 
	 * @param string $actionName Can be new, edit, search
	 *	(see class constants)
	 * @param array $formData Unprocessed data from form
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function __construct($actionName=null, $formData=null)
	{
		if($action)
			$this->setAction($actionName);
		
		if ($formData)
			$this->setFormData($formData);
	}
	
	/**
	 * Setup form data for display
	 *
     * @return Zend_Filter_Form Provides a fluent interface
	 * @author Zarrar Shehzad
	 **/
	protected function _setForm()
	{
	}
	
	/**
	 * Setup form data and errors for display in newAction/editAction
	 *
	 * @param array $formData
	 * @param array $errors Your own errors in lieu of Zend_Filter_Input's
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _setForm(array $formData, $errors = null)
	{
		// Split dob info in formData (YYYY-MM-DD)
		if(isset($formData['baby']['dob'])) {
			$formData['baby']['dob'] = explode('-', $formData['baby']['dob']);
		}
		// Deal with number of languages, emails, and phones (default 2)
		// ajax fields
		if (!(empty($formData['language'])))
			$this->view->total_languages = count($formData['language']);
		if (!(empty($formData['phone'])))
			$this->view->total_phones = count($formData['phone']);
		if (!(empty($formData['email'])))
			$this->view->total_emails = count($formData['email']);		
		
		// Allow redisplay of form data
		foreach ($formData as $key => $value)
			$this->view->{$key} = $value;
		
		// Setup session vars for ajax fields
		// contains form data for these fields
		$this->session->phone = $formData['phone'];
		$this->session->language = $formData['language'];
		$this->session->email = $formData['email'];		
		
		// Setup errors if any
		if ($this->filter->hasErrors())
			$this->view->errors = $this->filter->errors;
		elseif ($errors)
			$this->view->errors = $errors;
		
		// Setup warnings if any
		if ($this->filter->hasWarnings())
			$this->view->warnings = $this->filter->warnings;
			
		return;
	}
	
	

	/*
		- makes $actions into const
		- for search want to have db query to be in table class,
			will need give filterRules etc and will return data
		- consider having abstract class for new, edit, search and then splitting it
	
		Functions
		- constructor($action->(new, edit, search?), $formData, if search: $filterRules, $validationRules, $filterOptions)
		- processForm($formData)
		- for new+edit:insertorupdate,
		- for search need function similar to setupData (modified data etc),
			also if good then do search and return results
		- both functions share processForm name + setForm
		
		- do controller_plugin for this, will allow you to have direct access to post
			and access to setting the view object
	*/
	
	// Process form info for new and edit action
	// TODO:
	//	- make a confirmation page before submitting
	protected function _processForm($action, $OldFormData = array()) {		
		// Reset current language, phone, email fields
		$this->session->language_num = 0;
		$this->session->phone_num = 0;
		$this->session->email_num = 0;
		
		// Process form?
		if ($this->_request->isPost()) {
			/*
				x1. Replace fields with values
				2. For phone_number etc have a default value, load default is 2, reset numbering to match with ... find out what numbering is like
				3. have function that takes the different inputs and maps them onto values
				4. have function that only gives you back the values that are not empty from the filter
			*/
			$result = $this->_processFormData();
			$formData = $this->filter->data;
			
			if($result) {
				$db = Zend_Registry::get('db');
				$db->beginTransaction();
				
				try {
					$this->_db($action, $formData);
					$db->commit();
				} catch (Exception $e) {
					$db->rollback();
					$errors = array(
						'db_error' => array(
							'info' => array(
								'ERROR entering information into database',
								"Please contact administrator with 1) error message given in bold below, 2) url of the page you are using, and 3) any other details",
								 "Otherwise just push the back button and try entering the data again",
								"<strong>" . $e->getMessage() . "</strong>"
							)
						)
					);
					$this->_setForm($formData, $errors);
					return False;
				}
				return True;
			} else {
				$this->_setForm($formData);
				return False;
			}
		}
		else {
			if (!(empty($OldFormData)))
				$this->_setForm($OldFormData);
			return -1;
		}	
	}
	
	/**
	 * Will either insert or update baby database
	 * 
	 * @param string $action Action to take, can only be 'new' or 'edit'
	 * @param array $formData
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _db($action, array $formData)
	{
		switch ($action) {
			case 'new':
				$this->_insertDb($formData);
				break;
			case 'edit':
				$this->_updateDb($formData);
				break;
			default:
				throw new Exception("Action '{$action}' not recognized. Can only be 'new' or 'edit'.");
				break;
		}
	}
	
	/**
	 * Insert into baby database from newAction form data
	 * 
	 * @param array $formData
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _insertDb(array $formData)
	{
		// Insert family data
		$data = $formData['family'];
		$family_table = new Family();
		$family_id = $family_table->insert($data);
		// Insert phone data
		foreach ($formData['phone'] as $data) {
			$data['family_id'] = $family_id;
			$phone_table = new FamilyPhone();
			$phone_table->insert($data);
		}
		// Insert email data
		foreach ($formData['email'] as $data) {
			$data['family_id'] = $family_id;
			$email_table = new FamilyEmail();
			$email_table->insert($data);
		}
		// Add baby info
		$data = $formData['baby'];
		$baby_table = new Baby();
		$baby_id = $baby_table->insert($data);
		// Add baby language data
		foreach ($formData['language'] as $data) {
			$data['baby_id'] = $baby_id;
			$language_table = new BabyLanguage();
			$language_table->insert($data);
		}
		
		return;
	}
	
	/**
	 * Update baby database from editAction form data
	 * 
	 * @param array $formData
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _updateDb(array $formData)
	{
	}
	
	/**
	 * Setup form data and errors for display in newAction/editAction
	 *
	 * @param array $formData
	 * @param array $errors Your own errors in lieu of Zend_Filter_Input's
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _setForm(array $formData, $errors = null)
	{
		// Split dob info in formData (YYYY-MM-DD)
		if(isset($formData['baby']['dob'])) {
			$formData['baby']['dob'] = explode('-', $formData['baby']['dob']);
		}
		// Deal with number of languages, emails, and phones (default 2)
		// ajax fields
		if (!(empty($formData['language'])))
			$this->view->total_languages = count($formData['language']);
		if (!(empty($formData['phone'])))
			$this->view->total_phones = count($formData['phone']);
		if (!(empty($formData['email'])))
			$this->view->total_emails = count($formData['email']);		
		
		// Allow redisplay of form data
		foreach ($formData as $key => $value)
			$this->view->{$key} = $value;
		
		// Setup session vars for ajax fields
		// contains form data for these fields
		$this->session->phone = $formData['phone'];
		$this->session->language = $formData['language'];
		$this->session->email = $formData['email'];		
		
		// Setup errors if any
		if ($this->filter->hasErrors())
			$this->view->errors = $this->filter->errors;
		elseif ($errors)
			$this->view->errors = $errors;
		
		// Setup warnings if any
		if ($this->filter->hasWarnings())
			$this->view->warnings = $this->filter->warnings;
			
		return;
	}
	
} // END class Zarrar_Filter_Form