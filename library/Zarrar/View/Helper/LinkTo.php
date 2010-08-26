<?php

require_once 'Zend/View/Helper/Url.php';

/**
 * undocumented class
 *
 * @package default
 * @author Zarrar Shehzad
 **/
class Zend_View_Helper_LinkTo extends Zend_View_Helper_Url
{
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	public function linkTo($text, $linkOptions, $route = null, array $linkParams = null, array $htmlOptions = null)
	{
		// <a href="{$linkOptions}?{$linkParams}" {$htmlOptions[key]}="{$htmlOptions[value]}">{$name}</a>
		
		# 1. Deal with $linkOptions
		
		if(is_array($linkOptions))
			$url = $this->url($linkOptions, $route);
		elseif (is_string($linkOptions))
			$url = $linkOptions;
		else 
			throw new Zend_View_Exception("\$linkOptions was not valid, must be either a string or an array");
		
		# 2. Deal with $linkParams
		
		if(isset($linkParams)) {
			$params = "?";
			foreach ($linkParams as $field => $value) {
				$params .= $field . "=" . $value . "&";
			}
			$params = substr($params, 0, strlen($params) - 1);
		}
		else 
			$params = "";
			
		$url .= $params;
		
		# 3. Deal with $htmlOptions
		
		if(isset($htmlOptions)) {
			$addHTML = "";
			foreach ($htmlOptions as $attribute => $value) {
				$addHTML .= " " . $attribute . "=\"" . $value . "\"";
			}
		}
		else 
			$addHTML = "";
		
		# 4. Deal with name
		
		$link = "<a href=\"" . $url . "\"" . $addHTML . ">";
		$link .= $text;
		$link .= "</a>";
		
		# 5. return (string) $link
		
		return $link;
	}
} // END class Zend_View_Helper_linkTo