<?php

// Class for pagination
require_once 'Pager/Pager.php';

/**
 * Class to display links and other stuff related to pagination
 * 	1) Given base query will add limit and order by parameters
 * 	.... add more
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Controller_Action_Helper_Pager extends Zend_Controller_Action_Helper_Abstract
{	
	public function setupAll($namespace, array $urlFields=NULL, $userParams=array(), $db=NULL, $manualSetup=FALSE)
	{
		// Get db adapter
		$this->_db = ($db) ? $db : Zend_Registry::get('db');

		# GET SESSION VARIABLES
		$this->setupSessionVars($namespace);
	
		# GET PARAMS
		$this->setupParams();
		
		# SETUP QUERY
		$this->setupQuery();
		
		# SETUP PAGINATOR
		$this->setupPaginator($userParams);
		
		# SETUP HEADER LINKS
		$this->setupHeaderLinks($urlFields);
		
		# SETUP VIEW
		$this->setViewVars();
		
		return;
	}
	
	/**
	 * Set class variables from session
	 * 	- listType, rowCount, baseQuery, perPage
	 *
	 * @return void
	 **/
	public function setupSessionVars($namespace)
	{	
		// Declare session namespace
		$session = new Zend_Session_Namespace($namespace);
		
		// Type of list (e.g. schedule, confirm, outcome etc)
		$this->listType = $session->type;
		
		// Total number of rows from query (row count)
		$this->rowCount = $session->count;
		
		// Query
		$this->baseQuery = $session->query;
		
		// Rows to display per page (default is 25)
		$this->perPage = ($session->perPage) ? $session->perPage : "25";
		
		return;
	}
	
	/**
	 * Set class variables from passed params
	 *
	 * @return void
	 **/
	public function setupParams()
	{
		// Get request object
		$request = $this->getRequest();
		
		// Current page number (default 1)
		$this->pageNum = ($request->getParam("page")) ? $request->getParam("page") : "1" ;

		// Order table by field (default baby's id or serial no)
		$this->sort = ($request->getParam("sort")) ? $request->getParam("sort") : "id" ;
		
		// Direction to order table (default ascending)
		$this->order = ($request->getParam("order")) ? $request->getParam("order") : "ASC" ;
		
		return;
	}
	
	/**
	 * Setup db query (adding limits and order)
	 *
	 * @return array Query results
	 **/
	public function setupQuery()
	{	
		// Additional part of query only
		$addition = $this->_db->select()
		// Limit query based on page number and number of rows to display per page
			->limitPage($this->pageNum, $this->perPage)
		// Order by $sort $order
			->order("{$this->sort} {$this->order}")
		// Return string to add on
			->__toString();
		
		// Combine base query and addition
		$query = $this->baseQuery . " " . $addition;
		$this->query = $query;
		
		// Fetch Rows
		$stmt = $this->_db->query($query);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$this->rows = $rows;
		
		return($rows);
	}
	
	/**
	 * Uses HTML_PAGER and creates page links to display
	 *
	 * @return string
	 **/
	public function setupPaginator($userParams=array())
	{		
		// Get controller
		$controller = $this->getActionController();
		// Get request
		$request = $this->getRequest();
		
		// Default options for pagination
		$defaultParams = array(
		    'mode'      	=> 'Sliding',
			'delta'			=> 2,
		    'append'    	=> false,
			'currentPage' 	=> $this->pageNum,
		    'path'      	=> $controller->view->url(array("controller" => $request->getControllerName(), "action" => $request->getActionName(), "type" => $this->listType), null, true),
		    'fileName'  	=> "page/%d/sort/{$this->sort}/order/{$this->order}",
		    'totalItems' 	=> $this->rowCount,
		    'perPage'   	=> $this->perPage
		);
		// Combine user params (can overide defaults)
		$params = array_merge($defaultParams, $userParams);
		
		// Get pager
		$pager =& Pager::factory($params);
		// Get links (preformatted)
		$links = $pager->links;
		$this->links = $links;

		return($links);
	}
	
	/**
	 * Creates links for header titles allowing for resorting of that column
	 *
	 * @param array $urlFields Fields to create url links for sorting
	 * @return array
	 **/
	public function setupHeaderLinks(array $urlFields=NULL)
	{
		if ($urlFields == NULL) {
			$this->fieldLinks = NULL;
			return NULL;
		}
	
		$fieldLinks = array();
		$request = $this->getRequest();
		
		// create the urls
		foreach ($urlFields as $field)
			$fieldLinks[$field] = array("controller" => $request->getControllerName(), "action" => $request->getActionName(), 'sort' => $field, 'order' => ($this->sort == $field and $this->order == 'ASC') ? 'DESC' : 'ASC', 'type' => $this->listType);
			
		$this->fieldLinks = $fieldLinks;
		
		return($fieldLinks);
	}
	
	/**
	 * Sets pagination related variables into the view
	 *
	 * @return void
	 **/
	public function setViewVars()
	{
		$controller = $this->getActionController();
	
		// List page type (schedule, outcome, etc)
		$controller->view->listType = $this->listType;
		
		// Result (rows)
		$controller->view->results = $this->rows;
		
		// Page links
		$controller->view->links = ($this->links) ? $this->links : 1 ;
		
		// Total number of rows
		$controller->view->rowCount = $this->rowCount;
		
		// Column header links
		$controller->view->assign('fieldLinks', $this->fieldLinks);
	}
	
	/**
	 * Direct Function
	 *
	 * @return void
	 **/
	public function direct($namespace, array $urlFields=NULL, $userParams=array(), $db=NULL, $manualSetup=FALSE)
	{
		$this->setupAll($namespace, $urlFields, $userParams, $db, $manualSetup);
		
		return;
	}
	
} // END class Zarrar_Controller_Action_Helper_Pager