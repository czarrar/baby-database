<?php

# These pages are view only to show studies and age ranges

class StudyAgeController extends Zend_Controller_Action 
{

	/**
	 * Table Class for Studies
	 *
	 * @var Zend_Db_Table
	 **/
	protected $_table;
	
	# Fields: id, date_of_entry, researcher_id, study, to_use, lower_age, upper_age, odd/even
	
	/**
	 * Initialize any action
	 **/
	function init()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
		
		// Disable header file
		$this->view->headerFile = '_empty.phtml';
		
		// Add stylesheet for table
		$this->view
			->headLink()
			->appendStylesheet("{$this->view->dir_styles}/oranges-in-the-sky.css");
			
		// Instantiate table for later usage
		$this->_table = new Study();
	}
	
	public function indexAction()
	{
		$this->_forward("list");
	}
	
	/**
	 * Lists all studies that are in the db
	 **/
	public function listAction()
	{
		# 1. SETUP HEADER STUFF
		$dirs = Zend_Registry::get('dirs');
		// Attach spreadsheet for table
		$this->view->headLink()
			->prependStylesheet("{$dirs->styles}/sortable_tables.css", "screen, projection");
		// Attach scripts for dynamic sorting of table
		$this->view->headScript()
			->prependFile("{$dirs->scripts}/sortable_tables.js")
			->prependFile("{$dirs->scripts}/MochiKit/MochiKit.js");
		
		# 2. GET ROWS
		// Get select field
		$db = Zend_Registry::get('db');
		$select = $db->select()
			->from(array('s' => "studies"),
				array('id', 'date_of_entry', 'study', 'to_use', 'lower_age', 'upper_age', 'odd_even', 'gcal_calendar_id'))
			->joinLeft(array('r' => "researchers"),
				"s.researcher_id = r.id", array("researcher"))
			->joinLeft(array('l' => "labs"),
				"r.lab_id = l.id", array("lab"));
		// Check if want to display archived
		$viewArchive = $this->_getParam("view_archive");
		switch ($viewArchive) {
			// View only archived (to_use = 0)
			case 1:
				$select->where("s.to_use = ?", 0);
				$this->view->viewArchived = TRUE;
				break;
			// View only active (to_use = 1)
			case 2:
				$select->where("s.to_use = ?", 1);
				$this->view->viewActive = TRUE;
				break;
			// View all
			default:
				$this->view->viewAll = TRUE;
				break;
		}
		// Fetch and Save rows
		$rows = $db->fetchAll($select);
		$this->view->results = $rows;
		
		# 3. ID PADDING FOR SORTING
		// Get the id of the last row (assuming that they are in order of id)
		$tmpRows = $rows;
		$lastRow = array_pop($tmpRows);
		$lastId = $lastRow["id"];
		// Get the length of id + padding length to have proper sorting
		$this->view->idPad = strlen($lastId);
	}

}
