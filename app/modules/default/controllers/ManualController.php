<?php

class ManualController extends Zend_Controller_Action 
{
	function init()
	{
		Zend_Loader::loadClass('Manual');
		Zend_Loader::loadClass('ManualForm');
	}
	
	function indexAction()
	{
		$manual = new Manual();
		$this->view->manual = $manual;
		
		$info = new ManualForm();
		$this->view->info = $info->fetchAll();
		
		$this->view->yaml = Spyc::YAMLLoad('./config/manual.yaml');
	}
}