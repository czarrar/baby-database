<?php

/**
 * Sanitizes text turning table column names into something readable
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_View_Helper_Sanitize extends Zend_View_Helper_Url
{

	/**
	 * Replaces '_' with ' ' and capitizes words
	 *
	 * @param string $name
	 * @return string
	 **/
	public function sanitize($name)
	{
		$displayName = str_replace("_", " ", $name);
		$displayName = ucwords($displayName);
		
		return $this->view->escape($displayName);
	}
	
	/**
     * Set the view object
     *
     * @param Zend_View_Interface $view
     * @return void
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
} // END class Zarrar_View_Helper_Url extends Zend_View_Helper_Url
