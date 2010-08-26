<?php

# ALTER TABLE `callers` ADD `email` varchar(255) DEFAULT NULL AFTER `name`;
# ALTER TABLE `babies` ADD `checkout_caller_id` int UNSIGNED DEFAULT NULL ;
# ALTER TABLE `babies` ADD FOREIGN KEY (checkout_caller_id) REFERENCES callers (id) ON UPDATE CASCADE ON DELETE RESTRICT;

/**
 * Checks baby records to see that things are not checked out for too long
 *
 * @author Zarrar Shehzad
 **/
class Zarrar_CheckRecords_CheckOut
{
	/**
	 * Database Adapter
	 *
	 * @var Zend_Db
	 **/
	protected $_db;
	
	/**
	 * Log stuff
	 *
	 * @var Zend_Log
	 **/
	protected $_logger;
	
	/**
	 * Base Select Query
	 *
	 * @var Zend_Db_Select
	 **/
	protected $_baseSelect;
	
	/**
	 * Mail Class
	 *
	 * @var Zend_Mail
	 **/
	protected $_mail;
	
	/**
	 * Baby Table
	 *
	 * @var Zend_Db_Table
	 **/
	protected $_babyTbl;
	
	/**
	 * Checkout History Table
	 *
	 * @var Zend_Db_Table
	 **/
	protected $_chTbl;
	
	/**
	 * Admin's email address
	 *
	 * @var string
	 **/
	protected $_adminEmail;
	
	/**
	 * Coordinator's email address
	 *
	 * @var string
	 **/
	protected $_coordinatorEmail;
	
	/**
	 * Constructor Function
	 *
	 * @return void
	 **/
	public function __construct()
	{
		# A. Setup needed classes
		// Mail
		$this->_mail = new Zend_Mail();
		// Baby Table
		$this->_babyTbl = new Baby();
		// Checkout History Table
		$this->_chTbl = new CheckoutHistory();
		
		# B. Get config based info
		$config = Zend_Registry::get("config");
		// Admin Email
		$this->_adminEmail = $config->admin->email;
		// Coordinator Email
		$this->_coordinatorEmail = $config->coordinator->email;
		// Log filename
		$logFile = $config->log->checkrecords->filename;
		
		# C. Setup base query
		$this->_db = Zend_Registry::get("db");
		$this->_setupBaseQuery();
		
		# D. Setup logging system
		$this->_setupLogging($logFile);
	}
	
	/**
	 * Does all kinds of checks
	 *
	 * @return boolean
	 **/
	public function checkAll()
	{
		$isGood = $this->checkSemiActive();
		$isGood = $isGood and $this->checkActive();
		$isGood = $isGood and $this->checkInactive();
		
		return $isGood;
	}
	
	/**
	 * Does a check on semi-active babies
	 *
	 * @return boolean
	 **/
	public function checkSemiActive()
	{
		$this->_logger->info("Performing check of semi-active records");
		
		// Get base query
		$select = $this->_baseQuery;
		
		// Want semi-active
		$select->where("stat.group LIKE ?", "semi-active");
		
		// Split into two
		$selectOne = $select;
		$selectTwo = $select;
		
		// ONE: warning email, last checkout update = 2 days old
		$checkoutDate = date("Y-m-d", strtotime("-2 days"));
		$selectOne->where("b.checkout_date = ?", $checkoutDate);
		$isGood = $this->_doCheck($selectOne, "semiactive", FALSE, TRUE);
		
		// TWO: decision email, checkin + email = 7+ days old
		$checkoutDate = date("Y-m-d", strtotime("-7 days"));
		$selectTwo->where("b.checkout_date = ?", $checkoutDate);
		$isGood = $isGood and $this->_doCheck($selectTwo, "semiactive", TRUE, TRUE, TRUE);
		
		return $isGood;
	}
	
	/**
	 * Does a check on active babies
	 *
	 * @return boolean
	 **/
	public function checkActive()
	{
		$this->_logger->info("Performing check of active records");
		
		// Get base query
		$select = $this->_baseQuery;
		
		// Want active
		$select->where("stat.group LIKE ?", "active");

		// Split into two parts
		$selectOne = $select;
		$selectTwo = $select;

		// A. WARNING EMAIL: last checkout update -> 2 days old
		$checkoutDate = date("Y-m-d", strtotime("-2 days"));
		$selectOne->where("b.checkout_date = ?", $checkoutDate);
		$isGood = $this->_doCheck($selectOne, "active", False, True);

		// B. WARNING EMAIL: last checkout update -> 7+ days old
		$checkoutDate = date("Y-m-d", strtotime("-7 days"));
		$selectTwo->where("b.checkout_date = ?", $checkoutDate);
		$isGood = $isGood and $this->_doCheck($selectTwo, "active", False, True, True);
		
		return $isGood;
	}
	
	/**
	 * Does a check on inactive babies
	 *
	 * @return boolean
	 **/
	public function checkInactive()
	{
		$this->_logger->info("Performing check of inactive records");
		
		// Get base query
		$select = $this->_baseQuery;
		
		// Want inactive
		$select->where("stat.group LIKE ?", "inactive");
		
		// Check
		$isGood = $this->_doCheck($select, "inactive", False, True, False, True);
		
		return $isGood;
	}
	
	/**
	 * Does check on baby records (retrieved via $select search criteria)
	 *
	 * @param obj Zend_Db_Select $select
	 * @param string $type (e.g. semiactive, active, inactive)
	 * @param boolean $checkIn
	 * @param boolean $emailCaller default False
	 * @param boolean $emailCoordinator default False
	 * @param boolean $emailAdmin default False
	 * @return boolean
	 **/
	protected function _doCheck($select, $type, $checkIn, $emailCaller=FALSE, $emailCoordinator = False, $emailAdmin = False)
	{
		# Fetch records
		$records = $this->_fetchAll($select);
		
		# All good if nothing returned
		if (empty($records)) {
			$this->_logger->info("Nothing is bad for type '{$type}'");
			return True;
		} else {
			$this->_logger->info("Stuff is bad for type '{$type}'");
		}
		
		# Loop through records
		foreach ($records as $record) {
			# 1. CHECK IN
			if ($checkIn)
				$this->_checkIn($record["baby_id"]);
			
			# 2. EMAIL
			$this->_logger->info("Sending out an email for baby {$record['baby_id']}");
			
			// 2a. Set To (who mailing)
			if ($emailCaller)
				$this->_mail->addTo($record["caller_email"]);
			if ($emailCoordinator)
				$this->_mail->addTo($this->_coordinatorEmail);
			if ($emailAdmin)
				$this->_mail->addTo($this->_adminEmail);
			
			// 2b. Subject
			$this->_mail->setSubject($this->_getSubject($type, $checkIn));
			
			// 2c. Contents
			$this->_mail->setBodyText($this->_getBodyText($type, $checkIn, $record));
			
			// wrap up
			$this->_sendEmail();
		}
		
		return False;
	}
	
	/**
	 * Checks in baby
	 * 	- update baby record
	 * 	- new checkout history entry
	 *
	 * @param int $babyId
	 * @return void
	 **/
	protected function _checkIn($babyId)
	{	
		try {
			$this->_logger->info("Checking baby (id '{$babyId}') back in...");
			
			// Start a transaction
			$this->_db->beginTransaction();
			
			// Update baby table
			$where = $this->_babyTbl->select()->where("id = ?", $babyId);
			$babyData = array(
				"checked_out"	=> 0
			);
			$this->_babyTbl->update($babyData, $where);

			// Update checkout history
			$chData = array(
				"baby_id"		=> $babyId,
				"checked_out"	=> 0
			);
			$this->_chTbl->insert($chData);
			
			// Commit record changes
			$this->_db->commit();
		} catch (Exception $e) {
			// Cancel record changes
			$this->_db->rollback();
			
			// Record problem
			$this->_logger->err("Attempted to check in baby (id '{$babyId}') but failed because {$e->getMessage()}");
		}
	}
	
	/**
	 * Gets the subject text for email
	 * 
	 * @param string $type
	 * @param boolean $checkIn
	 * @return string
	 **/
	protected function _getSubject($type, $checkIn)
	{
		$subject = "";
		$subject .= ($checkIn) ? "ATTENTION - record checked in - " : "WARNING - ";
		$subject .= "baby still " . $type;
		
		return $subject;
	}
	
	/**
	 * Gets the body text for email
	 *
	 * @param string $type
	 * @param boolean $checkIn
	 * @param array $record
	 * @return string
	 **/
	protected function _getBodyText($type, $checkIn, $record)
	{
		$body = "";
		
		$body .= "Dear {$record['caller']}" . PHP_EOL;
		$body .= "	";
		
		if (!$checkIn)
			$body .= "This is a warning message. ";
		
		$body .= "A baby (id '{$record['baby_id']}') is currently checked-out with scheduling status '{$record['scheduling_status']}' and record status '{$record['record_status']}'." . PHP_EOL;
		
		if ($checkIn)
			$body .= "	The baby has automatically been checked back in. If this is an error, please contact your administrator at {$this->_adminEmail}" . PHP_EOL;
			
		$body .= PHP_EOL . "	Thank You";
		
		return $body;
	}
	
	/**
	 * Sends out email
	 * 	first adds on who from (ie administrator)
	 *
	 * @return void
	 **/
	protected function _sendEmail()
	{
		// Set from
		$this->_mail->setFrom($this->_adminEmail, "NYU B&C Administrator");
		
		// Send
		$this->_mail->send();
		
		$this->_logger->info("Sent email");
	}
	
	/**
	 * Gets rows of your query
	 *
	 * @param obj Zend_Db_Select $select
	 * @return array
	 **/
	private function _fetchAll(Zend_Db_Select $select)
	{
		$stmt = $this->_db->query($query);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		
		return $rows;
	}
	
	/**
	 * Sets up logging capabilities
	 *
	 * @return void
	 **/
	protected function _setupLogging($logFile)
	{
		// Create class
		$logger = new Zend_Log();
		
		// Setup writer to output
		$writer1 = new Zend_Log_Writer_Stream('php://output');
		$logger->addWriter($writer1);
		
		// Setup writer to file
		$writer2 = new Zend_Log_Writer_Stream($logFile);
		$filter = new Zend_Log_Filter_Priority(Zend_Log::INFO);
		$writer2->addFilter($filter);
		$logger->addWriter($writer2);
		
		// Everything good
		$logger->info("Starting logging of " . get_class($this) . " class");
		
		// Save logger
		$this->_logger = $logger;
	}
	
	/**
	 * Setup base select query and save into $this->_baseQuery
	 *
	 * @return void
	 **/
	protected function _setupBaseQuery()
	{
		$select = $this->_db->select()

			->distinct()

			// Baby table
			->from(
				array("b" => "babies"),
				array("baby_id" => "id", "checkout_date")
			)

			// Status table (to refine search)
			->joinLeft(
				array("stat" => "statuses"),
				"b.status_id = stat.id",
				array("status", "group")
			)

			// Caller table (with email address)
			->joinLeft(
				array("c" => "callers"),
				"b.checkout_caller_id = c.id",
				array("caller" => "name", "caller_email" => "email")
			)

			// Want search only babies that are checked out
			->where("b.checked_out = ?", 1);
			
		// Save
		$this->_baseQuery = $select;		
	}
	
} // END class Zarrar_CheckRecords_CheckOut