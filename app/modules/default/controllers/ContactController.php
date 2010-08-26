<?php

# New, Edit, Delete, List
# Given baby_id, select study + attempt, datetime,
# Given caller, type + means + outcome of contact, callback
# Comments

# In list, add researcher

class ContactController extends Zend_Controller_Action 
{
	function init()
	{
	}
	
	function indexAction()
	{
		var_dump($this->_request->getParams());
		exit();
	}
	
	/**
	 * Page for new contact history
	 *
	 **/
	public function newAction()
	{
		// Add to stylesheets
		$this->view->headLink()->appendStylesheet("{$this->view->dir_styles}/cssform.css");
		
		// Declare form filter
		$this->default_options = array('namespace' => 'Zarrar_Validate', 'escapeFilter' => 'StringTrim');
		$this->filter = new Zarrar_Filter_Data($this->default_options);
		
		// Get options for select form fields
		// options for study, contact type, contact outcome
		$contactHistory = new ContactHistory();
		$study = new Study();
		$this->view->studies = $contactHistory->getRefSelectOptions('Study', "Study");
		$this->view->contact_types = $contactHistory->getRefSelectOptions('ContactType', "Type");		
		$this->view->contact_outcomes = $contactHistory->getRefSelectOptions('ContactOutcome', "Outcome");
		$this->view->researchers = $study->getRefSelectOptions('Researcher', "Researcher");
		
		// Set other options for select form fields manually
		// options for contact methods and contact callback
		$this->view->contact_methods = array("" => "select", "Phone", "Email", "Mail");
		$this->view->contact_callbacks = array("N/A", "AM", "PM", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sept", "Oct", "Nov", "Dec");
		
		// Process form data
		// If good, then insert or update
		
		
	}
	
	protected function _processFormData()
	{
		// Extended options
		$options = $this->default_options;
		$options['multiple'] = true;
		
		/**********
		**	1. Process Baby Info 
		**********/
		
		// Field of interest
		$field_elements = array('baby_id', 'study_id', 'researcher_id', 'attempt', 'caller_id', 'datetime', 'contact_type_id', 'contact_method', 'contact_outcome_id', 'contact_callback', 'comments');
		
		// Get form data
		$data = $this->_request->getPost();
		
		// Declare tables (contact_histories)
		// for checking uniqueness
		$contactHistory = new ContactHistory();
		
		// Combine datetime parts into whole ('YYYY-MM-DD HH:MM:SS')
		$baby_dob = $baby_data['dob'];
		if (!(empty($baby_dob['year'])) && !(empty($baby_dob['month'])) && !(empty($baby_dob['day'])))
			$baby_data['dob'] = "{$baby_dob['year']}-{$baby_dob['month']}-{$baby_dob['day']}";
		else
			$baby_data['dob'] = '';
							
		// Declare filters
		$filters = array(
			'*'				=> 'StripTags'
		);
		
		// Declare validators
		$validators = array(
			'myfields'		=> array(
				'ValidFields',
				'fields' => $field_elements
			),
			'attempt'		=> array(
				'NotEmpty'
			),
			'contact_method'	=> array(
				'NotEmpty'
			),
			'caller_id'		=> array(
				'NotEmpty'
			),
			'contact_type_id' => array(
				'NotEmpty'
			),
			'contact_outcome_id'	=> array(
				'NotEmpty'
			),
			'unique'			=> array(
				array('Uniqueness', $contactHistory, array('baby_id', 'study_id', 'attempt')),
				'fields' => array('baby_id', 'study_id', 'attempt'),
				'messages' => "This combo (baby, study, and attempt) already exist in the database."	# Can the uniqueness thing give me a link to the baby's entry?
			)
		);
		
		// Process data (filter, validate, get errors, etc)
		$this->filter->processInstance('contact', $filters, array('errors'=>$validators), $data);
		
		if ($this->filter->hasErrors() or $this->filter->hasWarnings())
			return false;
		else
			return true;
	}
}