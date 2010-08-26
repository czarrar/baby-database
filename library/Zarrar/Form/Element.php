<?php

/**
 * Extends zend element class
 * 	- allows for displaying of labels separate from form fields
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Form_Element extends Zend_Form_Element
{
	/**
	 * Will return a rendered form label element
	 *
	 * @return string
	 **/
	public function label()
	{
		// Fetches decorator 'MyLabel' or just 'Label'
		$decorator = (isset($this->_decorators['MyLabel'])) ? $this->_decorators['MyLabel'] : $this->getDecorator('Label');

		// Render label (if specified)
		$content = '';
		if ($decorator) {
			$decorator->setElement($this);
			$content = $decorator->render($content);
		}

		return $content;
	}

	/**
	 * Gets formatted field element
	 *
	 * @param string $content
	 * @return string
	 **/
	public function field($content = '')
	{
		// Fetches decorator 'MyField' or just 'ViewHelper'
		$decorator = (isset($this->_decorators['MyField'])) ? $this->_decorators['MyField'] : $this->getDecorator('ViewHelper');
			
		// Render field (if specified)
		if ($decorator) {
			$decorator->setElement($this);
			$content = $decorator->render($content);
		}
	
		return $content;
	}
} // END class Zarrar_Form_Element
