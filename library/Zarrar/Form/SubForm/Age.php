<?php

/** Zend_Form */
require_once 'Zarrar/Form/SubForm/Date.php';

/**
 * Collection of three form fields that are combined:
 * 	- year, month, day as select fields
 * 	- combined into Year-Month-Day
 * Validation done on subfields and combined fields
 *
 * @package Zend_Form
 * @author Zarrar Shehzad
 **/
class Zarrar_Form_SubForm_Age extends Zarrar_Form_SubForm_Date
{
	// This is the internal fieldname (hidden field) for the date element
	protected $_dateField = "my_age";	
	
	/**
	 * Returns array of default config options
	 *
	 * @param array $settings
	 * @return array
	 **/
	protected function _getDefaultOptions($settings)
	{
		// years 1992-1999, represented as array("1992", "1999")
		$years = $settings["years"];
	
		// Select year options
		$yearOptions = array("" => "Year");
		for ($i=$years[0]; $i <= $years[1]; $i++) {
			$year = str_pad($i, 4, '0', STR_PAD_LEFT);
			$yearOptions[$year] = $year;
		}
		
		// Select month options
		$monthOptions = array("" => "Month");
		for ($i=0; $i < 13; $i++) {
			$month = str_pad($i, 2, '0', STR_PAD_LEFT);
			$monthOptions[$month] = $month;
		}
		
		// Select day options
		$dayOptions = array("" => "Day");
		for ($i=0; $i < 32; $i++) {
			$day = str_pad($i, 2, '0', STR_PAD_LEFT);
			$dayOptions[$day] = $day;
		}
		
		// The real settings!
		$options = array(
			"elementPrefixPaths" => array(
				"validate"	=> array(
					"prefix"	=> "Zarrar_Validate",
					"path"		=> "Zarrar/Validate"
				)
			),
			"elements" => array(
				# DATE
				$this->_dateField => array(
					"type" => "hidden",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# Decorators
						"decorators" => array(
							array(
								"element" => "ViewHelper"
							)
						),
						# Validators
						"validators" => array(
							"date" => array(
								"validator" => "Age"
							)
						)
					)
				),
				# YEAR
				"year" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $yearOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper",
									"options" => array(
										"separator" => " - ",
										"placement" => "PREPEND"
									)
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							)
						)
					)
				),
				# MONTH
				"month" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $monthOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper",
								"options" => array(
									"separator" => " - ",
									"placement" => "PREPEND"
								)
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							),
							"between" => array(
								"validator" => "Between",
								"options" => array(
									"min" => 0,
									"max" => 12
								)
							)
						)
					)
				),
				# DAY
				"day" => array(
					"type" => "select",
					"options" => array(
						"disableLoadDefaultDecorators" => true,
						# MultiOptions
						"MultiOptions" => $dayOptions,
						# Decorator
						"decorators" => array(
							array(
								"element" => "ViewHelper"
							)
						),
						# Validators
						"validators" => array(
							"int" => array(
								"validator" => "Int"
							),
							"between" => array(
								"validator" => "Between",
								"options" => array(
									"min" => 0,
									"max" => 31
								)
							)
						)
					)
				)
			)
		);
		
		return $options;
	}
	
} // END class Zarrar_Form_SubForm_Ages extends Zend_Form_SubForm

