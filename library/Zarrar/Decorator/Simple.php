<?php

/**
 * Decorator class
 * 	- will only render the form field and nothing else (SIMPLE)
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_Decorator_Simple extends Zend_Form_Decorator_Abstract
{
	/**
	 * Default placement: none
	 *
	 * @var string
	 **/
	protected $_placement = NULL;

	/**
	 * Renders form field
	 *
	 * @return string
	 * @author Zarrar Shehzad
	 **/
	public function render($content)
	{
		$element = $this->getElement();
		
		if (!$element instanceof Zend_Form_Element)
			return $content;
		if (null === $element->getView())
			return $content;
		
		$view = $element->getView();
		$helper = $element->helper;
		
		$separator	= $this->getSeparator();
		$placement	= $this->getPlacement();
		$output		= $view->$helper(
							$element->getName(),
							$element->getValue(),
							$element->getAttribs(),
							$element->options
						);
				
		switch ($placement) {
			case (self::PREPEND):
				return $output . $separator . $content;
			case (self::APPEND):
				return $content . $seperator . $output;
			default:
				return $output;
		}
	}
} // END class Zarrar_Decorator_Simple
