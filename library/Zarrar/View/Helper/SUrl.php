<?php

/**
 * undocumented class
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_View_Helper_SUrl extends Zend_View_Helper_Url
{
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function sUrl($action, $controller=null, $name=null, $reset = false, $encode = true, $module='default')
	{
	
		$urlOptions = array(
			'action'	=> $action,
			'module'	=> $module
		);
			
		if ($controller)
			$urlOptions['controller'] = $controller;
		
		return parent::url($urlOptions, $name, $reset, $encode);
	}
} // END class Zarrar_View_Helper_Url extends Zend_View_Helper_Url
