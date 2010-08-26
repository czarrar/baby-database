<?php

class ControllerPlugin extends Zend_Controller_Plugin_Abstract
{
	public function dispatchLoopStartup(Zend_Controller_Request_Abstract $response) {
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$viewRenderer->initView();
		$this->view = $viewRenderer->view;
	}

	public function preDispatch($request)
	{	
		// Get response
		$response = $this->getResponse();
		
		// Save base url
		$baseUrl = $request->getBaseUrl();
		$this->view->baseUrl = $baseUrl;
		
		// Save base path to styles, scripts, and images directory
		$this->view->dir_styles = $baseUrl . '/public/styles';
		$this->view->dir_scripts = $baseUrl . '/public/scripts';
		$this->view->dir_images = $baseUrl . '/public/images';
		
		// Save path values into registry
		$dirClass = new stdclass();
		$dirClass->base = $baseUrl;
		$dirClass->styles = $this->view->dir_styles;
		$dirClass->scripts = $this->view->dir_scripts;
		$dirClass->images = $this->view->dir_images;
		Zend_Registry::set('dirs', $dirClass);
		
		// Set default stylesheet(s)
		$this->view->headLink() ->prependStylesheet("{$this->view->dir_styles}/print.css", "print, handheld")
							    ->prependStylesheet("{$this->view->dir_styles}/style.css", "screen, projection");
		//$this->view->headLink()->prependStylesheet("{$this->view->dir_styles}/common.css")
		//                 	   ->prependStylesheet("{$this->view->dir_styles}/color-scheme.css");
		
		// Set default javascript file(s)
		$this->view->headScript()->prependFile("{$this->view->dir_scripts}/projax/scriptaculous.js")
		                   		 ->prependFile("{$this->view->dir_scripts}/projax/prototype.js")
		                   		 ->appendFile("{$this->view->dir_scripts}/open_windows.js");
		
		// Save module name
		$module = $request->getModuleName();
		$this->view->module = $module;
		// Save controller name
		$controller = $request->getControllerName();
		$this->view->controller = $controller;
		// Save action name
		$action = $request->getActionName();
		$this->view->action = $action;
		
		/* Perform authentication */
		$auth = Zend_Auth::getInstance();
		if ($controller == 'login') {
		  // Move this login specific section into the login controller
			if ($auth->hasIdentity() && $action == 'index')
				$response->setRedirect($this->view->baseUrl);
			elseif (!($auth->hasIdentity()) && $action == 'logger')
				$response->setRedirect($this->view->url(array("controller" => "login", "action" => "index"), null, true));
			return;
		}
		// Stuff below is good
		elseif (!($auth->hasIdentity())) {
			$response->setRedirect($this->view->url(array('controller' => 'login'), null, true));
		}
		elseif (empty($_SESSION['logintime'])) {
			$response->setRedirect($this->view->url(array('controller' => 'login', 'action' => 'logger'), null, true));
		}
		
		// Make username available
		$this->view->myUsername = $auth->getIdentity();
		$this->view->myCallerId = $_SESSION['caller_id'];
		// Make labname available
		$this->view->labName = $_SESSION['lab_name'];
	}
}
