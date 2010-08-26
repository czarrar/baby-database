<?php

class Zarrar_View_Helper_FormBirthWeight extends Zend_View_Helper_FormElement
{
	public function formBirthWeight($useGrams=TRUE)
	{
		if (!(empty($this->view->baby['birth_weight']))) {
			// Get weight
			$unit = new Zend_Measure_Weight($this->view->baby['birth_weight'], Zend_Measure_Weight::POUND);
			$pounds = $unit->getValue();
		
			// Get grams for display if value set
			$inGrams = $unit->convertTo(Zend_Measure_Weight::GRAM);
			
			// Get pounds and ounces to put in input text
			$poundsDecimals = strstr($pounds, ".");
			$ounceUnit = new Zend_Measure_Weight($poundsDecimals, Zend_Measure_Weight::POUND);
			$ounceUnit->setType(Zend_Measure_Weight::OUNCE);
			$pounds = (int) $pounds;
			$ounces = (int) $ounceUnit->getValue();			
		}
	
		// Get grams input text
		if ($useGrams) {
			$gramsFormText = "&nbsp; <strong><em>or</em></strong> &nbsp";
			$gramsFormText .= $this->view->formText("baby[birth_weight_grams]", null, null, array("size" => 6, "maxlength" => 12));
			$gramsFormText .= " grams";
		} else {
			$gramsFormText = "";
		}
		
		// Get pounds input text
		$poundsFormText = $this->view->formText("baby[birth_weight_pounds]", null, $pounds, array("size" => 2, "maxlength" => 3));
		// Get ounces input text
		$ouncesFormText = $this->view->formText("baby[birth_weight_ounces]", null, $ounces, array("size" => 3, "maxlength" => 5));
				
		$xhtml = "{$poundsFormText} lbs &nbsp; {$ouncesFormText} ounces {$gramsFormText}";
		
		return $xhtml;
	}
}
