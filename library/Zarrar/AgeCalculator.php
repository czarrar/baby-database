<?php

require_once 'Zend/Date.php';

/**
 * Calculates a babies age
 * 	a) Given a parameter of dob & date -> age of baby
 * 	b) Given a parameter of dob & age -> date that baby will be that age
 * 	c) Given a parameter of date & age -> dob of baby
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_AgeCalculator
{
	// Days in year
	const DAYS_IN_YEAR = 365.242;
	
	// Days in month
	const DAYS_IN_MONTH = 30.437;
	
	// Seconds in day
	const SECONDS_IN_DAY = 86400;
	
	// 

	/**
	 * Baby's date of birth
	 *
	 * @var Zend_Date
	 **/
	protected $_dob;
	
	/**
	 * Baby's age
	 *
	 * @var array
	 **/
	protected $_age;
	
	/**
	 * Date that baby is at $this->_age
	 *
	 * @var Zend_Date
	 **/
	protected $_date;
	
	/**
	 * For calculations such as add/subtract use this format
	 *
	 * @var string
	 **/
	protected $_conversionFormat = "Y-M-d";
	
	/**
	 * Constructor Function
	 *
	 * @param string $dob
	 * @param string $date
	 * @param mixed $age
	 * @return void
	 **/
	public function __contruct($dob=null, $date=null, $age=null)
	{
		if (!empty($dob))
			$this->setDob($dob);
		if (!empty($date))
			$this->setDate($date);
		if (!empty($age))
			$this->setAge($age);
			
		return;
	}
	
	/**
	 * Calculates the age of the baby
	 *
	 * @param string $format Format to return age; default: "full"
	 * @return string Dependent on $format
	 **/
	public function calculateAge($format="full")
	{
		// Check vars exist
		if (empty($this->_date) or empty($this->_dob))
			throw new Exception("Error returning age, date and/or dob class members are not set.");
		
		// Get date in seconds (since 1970...)
		$dateSeconds = $this->_date->get(Zend_Date::TIMESTAMP);
		
		// Get dob in seconds (since 1970...)
		$dobSeconds = $this->_dob->get(Zend_Date::TIMESTAMP);
		
		// Get age in seconds
		$age = $dateSeconds - $dobSeconds;
		
		// Set age
		$this->_age = $this->_floatAge($age, "seconds");
		
		// Return age
		return $this->getAge($format);
	}
	
	/**
	 * Calculates the date at which baby is given age
	 *
	 * @param string $format Default is "MMMM d',' YYYY"
	 * @return string Dependent on $format
	 **/
	public function calculateDate($format="MMMM d',' YYYY")
	{
		// Check vars exist
		if (empty($this->_age) or empty($this->_dob))
			throw new Exception("Error returning age, age and/or dob class members are not set.");
	
		// Load Zend Date for calculations
		$dateClass = new Zend_Date();
		
		// Set start date as dob
		$dateClass->set($this->_dob);
		
		// Add age
		$dateClass->add(implode("-", array_values($this->_age)), $this->_conversionFormat);
		// ghetto fix for accurate dates
		$dateClass->add(1, "d");
		
		// Set date
		$this->setDate($dateClass);
		
		// Return date
		return $this->getDate($format);
	}
	
	/**
	 * Calculates the dob for baby given his/her age and current date
	 *
	 * @param string $format Default is "YYYY-MM-dd"
	 * @return string Dependent on $format
	 **/
	public function calculateDob($format="YYYY-MM-dd")
	{
		// Check vars exist
		if (empty($this->_date) or empty($this->_age))
			throw new Exception("Error returning dob, age and/or date class members are not set.");
					
		// Load Zend Date for calculations
		$dateClass = new Zend_Date();
		
		// Set start date as date
		$dateClass->set($this->_date);
		
		// Subtract age
		$dateClass->sub(implode("-", array_values($this->_age)), $this->_conversionFormat);
		// ghetto fix for accurate dates
		$dateClass->sub(0, "d");
		
		// Set dob
		$this->setDob($dateClass);
		
		// Return dob
		return $this->getDob($format);
	}


	/**
	 * Sets baby's date of birth
	 *
	 * @param mixed $dob YYYY-MM-DD format
	 * @param string|integer $format Default is "YYYY-MM-dd"
	 * @return Zarrar_AgeCalculator Fluent interface
	 **/
	public function setDob($dob, $format="YYYY-MM-dd")
	{
		if ($dob instanceof Zend_Date)
			$this->_dob = $dob;
		else
			$this->_dob = new Zend_Date($dob, $format);
		
		return $this;
	}
	
	/**
	 * Returns baby's date of birth
	 *
	 * @param string|integer $format Default is "YYYY-MM-dd"
	 * @return string YYYY-MM-DD format
	 **/
	public function getDob($format = "YYYY-MM-dd")
	{
		if (isset($this->_dob)) {
			return $this->_dob->get($format);
		} else {
			if (isset($this->_date) and isset($this->_age))
				return $this->calculateDob($format);
			return NULL;
		}
	}
	
	/**
	 * Sets baby's age
	 *
	 * @param mixed $age
	 * 	if string, then shoud be Y-M-d format
	 * @param string $separator
	 * 	Only used if $age is a string variable
	 * 	What separates the date parts, default '-'
	 * @param string $format
	 * 	Only used if $age is integer or float
	 * 	What is the format of $age
	 * 	Options: 'seconds', 'days', 'years'
	 * 	Default: 'years'
	 * 
	 * @return Zarrar_AgeCalculator Fluent interface
	 **/
	public function setAge($age, $separator='-', $format="years")
	{
		$this->_age = $this->_formatInAge($age, $separator, $format);
		
		return $this;
	}
	
	/**
	 * Formats given age
	 *
	 * @return array
	 **/
	protected function _formatInAge($age, $separator, $format)
	{
		if (is_int($age))
			return $this->_intAge($age, $format);
		elseif (is_float($age))
			return $this->_floatAge($age, $format);
		elseif (is_string($age))
			return $this->_stringAge($age, $separator);
		else
			throw new Exception("Given value for age is not of type integer, float, or string!");
	}
	
	/**
	 * Processes baby's age
	 * 	allowing it to be set to $this->_age
	 * 	$age assumed to be a string
	 *
	 * @param mixed $age
	 * 	shoud be Y-M-d or equivalent format
	 * @param string $separator
	 * 	What separates the date parts
	 * 	Default '-'
	 * 
	 * @return void
	 **/
	protected function _stringAge($age, $separator)
	{
		// Get age parts into an array
		$ageParts = explode($separator, $age);
				
		// Get years
		$years = (int) $ageParts[0];
		
		// Get months
		$months = (int) $ageParts[1];
		
		// Get days
		$days = (int) $ageParts[2];
		
		// Combine parts into array
		$newAge = compact("years", "months", "days");		
		
		return $newAge;
	}
	
	/**
	 * Processes baby's age 
	 * 	allowing it to be set to $this->_age
	 * 	$age assumed to be float
	 *
	 * @param int $age
	 * @param string $format 
	 * 	Options: 'seconds', 'days', 'years'
	 * 
	 * @return void
	 **/
	protected function _floatAge($age, $format)
	{
		switch ($format) {
			case 'years':
				// convert to days
				$age = $age * self::DAYS_IN_YEAR;
				break;
			case 'days':
				# skip
				break;
			case 'seconds':
				// convert to days
				$age = $age / self::SECONDS_IN_DAY;
				break;
			default:
				throw new Exception("\$format must be 'days' or 'years', instead '$format' given");
				break;
		}
		
		# AGE SHOULD BE IN DAYS AT THIS POINT
		
		
		// get Years
		$years = (int) ($age / self::DAYS_IN_YEAR);
		// get remaining days
		$days = $age - ($years * self::DAYS_IN_YEAR);
		// get Months
		$months = (int) ($days / self::DAYS_IN_MONTH);
		
		// get Days
		$days = (int) ($days - ($months * self::DAYS_IN_MONTH));
		
		// Combine parts into array
		$newAge = compact("years", "months", "days");
		
		return $newAge;
	}
	
	/**
	 * Processes baby's age
	 * 	allowing it to be set to $this->_age
	 * 	$age assumed to be an integer
	 *
	 * @param int $age
	 * @param string $format 
	 * 	Options: 'seconds', 'days', 'years'
	 * 
	 * @return array
	 **/
	protected function _intAge($age, $format)
	{
		switch ($format) {
			// if in Years
			case 'years':
				// Set parts (mainly years)
				$years = $age;
				$months = 0;
				$days = 0;
				// Combine parts into array
				$newAge = compact("years", "months", "days");
				break;
			// otherwise send to _floatAge()
			default:
				$newAge = $this->_floatAge($age, $format);
				break;
		}
		
		return $newAge;
	}
	
	/**
	 * Returns baby's age
	 *
	 * @param string|integer $format
	 * 	Options:
	 * 		'full' (Y year(s), M month(s), d day(s) old)
	 * 		'medium' (YYYY-MM-dd)
	 * 		'short' (Y-M-d)
	 * @return string Based on $format
	 **/
	public function getAge($format, $delim="-")
	{
		// Whoops age is not already set
		if (!isset($this->_age)) {
			if (isset($this->_dob) and isset($this->_date))
				return $this->calculateAge($format);
			else
				return NULL;
		}
		
		$age = $this->_formatOutAge($this->_age, $format, $delim);

		return $age;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Zarrar Shehzad
	 **/
	protected function _formatOutAge($inAge, $format, $delim="-")
	{
		// extract age parts as $years, $months, $days
		$years = $inAge["years"];
		$months = $inAge["months"];
		$days = $inAge["days"];
		
		switch ($format) {
			case 'full':
				$age = "{$years} year(s), {$months} month(s), {$days} day(s)";
				break;
			case 'medium':
				// Pad stuff to look like YYYY-MM-dd
				$years = str_pad($years, 4, "0", STR_PAD_LEFT);
				$months = str_pad($months, 2, "0", STR_PAD_LEFT);
				$days = str_pad($days, 2, "0", STR_PAD_LEFT);
			case 'short':
				$age = "{$years}{$delim}{$months}{$delim}{$days}";
				break;
			case 'months':
				$months = ($years * 12) + $months;
				$months = str_pad($months, 2, "0", STR_PAD_LEFT);
				$days = str_pad($days, 2, "0", STR_PAD_LEFT);
				$age = "{$months}{$delim}{$days}";
				break;
			case 'days':
				$age = $years * self::DAYS_IN_YEAR + $months * self::DAYS_IN_MONTH + $days;
				$age = (int) $age;
				break;
			default:
				throw new Exception("\$format can only be 'full', 'medium', 'short', 'days', instead '$format' given!");
				break;
		}
		
		return $age;
	}
	
	/**
	 * Formats age (mainly for compareAge function)
	 *
	 * @return int
	 **/
	public function formatAge($age, $separator='-', $formatIn='years', $formatOut='full')
	{
		$age = $this->_formatInAge($age, $separator, $formatIn);
		$newAge = $this->_formatOutAge($age, $formatOut);
		
		return $newAge;
	}
	
	/**
	 * Compares given age with age in calculator
	 *	For params see setAge()
	 * @return int
	 * 	0 = same, -1 = age given bigger, 1 = age in calculator bigger
	 **/
	public function compareAge($age, $separator='-', $format="years")
	{
		if (!isset($this->_age))
			throw new Exception("Age in calculator not set...nothing to compare!");
		
		// First age (in calculator)
		$firstAge = $this->getAge("days");
		// Second age (given to compare)
		$secondAge = $this->formatAge($age, $separator, $format, "days");
		
		// Compare
		if ($firstAge > $secondAge)
			return 1;
		else if ($firstAge < $secondAge)
			return -1;
		else
			return 0;
	}
	
	/**
	 * Sets current date
	 *
	 * @param mixed $date
	 * 	- YYYY-MM-DD format
	 * 	- if null, then $date is set to today's date
	 * @param string|integer $format Default is "Y-M-d"
	 * @return Zarrar_AgeCalculator Fluent interface
	 **/
	public function setDate($date = NULL, $format = "Y-M-d")
	{
		if (is_null($date))
			$this->_date = new Zend_Date();
		elseif ($date instanceof Zend_Date)
			$this->_date = $date;
		else
			$this->_date = new Zend_Date($date, $format);
		
		return $this;
	}
	
	/**
	 * Returns date set in class
	 *
	 * @param string|integer $format Default is "YYYY-MM-dd"
	 * @return string YYYY-MM-DD
	 **/
	public function getDate($format = "YYYY-MM-dd")
	{
		if (isset($this->_date))
			return $this->_date->get($format);
		elseif (isset($this->_dob) and isset($this->_age))
			return $this->calculateDate($format);
		else
			return NULL;
	}

} // END class Zarrar_AgeCalculator
