<?php


class TempController extends Zend_Controller_Action 
{

	const FIX_DUPLICATES = TRUE;
	
	const HOME_DIR = "/home/wpl/public_html/babydb/app/etc/";
	#const HOME_DIR = "/Users/czarrar/Sites/athena/app/etc/";
	
	function testAction()
	{
        // Get db adapter
        $db = Zend_Registry::get('db');
        $bTbl = new Baby();
	    $row = $bTbl->fetchRow('id = 2');
	    $day = explode('-', $row->dob);
	    $day = $day[2];
	    
	    echo $day . "<br \>";
	    
	    $query = "SELECT id FROM lists WHERE (is_permanent = 0) AND (to_use = 1) ORDER BY list";
	    $stmt = $db->query($query);
	    $results = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
	    $numIds = count($results);
	    
	    echo $numIds . "<br />";
	    
	    $which = $day % $numIds;
	    $listId = $results[$which];
	    
	    echo $which . ":" . $listId . "<br />";
	    
	    exit();
	}
	
	function checkStudiesAction()
	{
		set_time_limit(300);
	
		// Study List
		$studyList = $this->getStudyList();
		// Studies not found
		$notFound = array();

		# SEARCH CONTACT HISTORY
		$rowNum = 1;
		$handle = fopen(self::HOME_DIR . "z_contacthistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;

			// Study
			$study = strtolower(trim($data[4]));
			if (!empty($study)) {
				// Search through the keys
				$keyExist = array_key_exists($study, $studyList);

				if ($keyExist === false) {
					$newKey = array_key_exists($study, $notFound);
					
					if ($newKey)
						$notFound{$study} = $notFound{$study} + 1;
					else
						$notFound{$study} = 1;
				}
			}
		}
		
		# SEARCH STUDY HISTORY
		$rowNum = 1;
		$handle = fopen(self::HOME_DIR . "z_studyhistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;

			// Study
			$study = strtolower(trim($data[3]));
			if (!empty($study)) {
				// Search through the keys
				$keyExist = array_key_exists($study, $studyList);

				if ($keyExist === false) {
					$newKey = array_key_exists($study, $notFound);
					
					if ($newKey)
						$notFound{$study} = $notFound{$study} + 1;
					else
						$notFound{$study} = 1;
				}
			}
		}
		
		// Arrange names in order
		ksort($notFound);

		// Spit into output
		var_export($notFound);
		fclose($fp);
		
		// Save as csv
		$fp = fopen(self::HOME_DIR . "z_loststudies.csv", "w");
		foreach ($notFound as $key => $value) {
			$line = array($key, $value);
			fputcsv($fp, $line);
		}
		fclose($fp);
		
		exit();
	}
	
	function setStudiesAction()
	{
		$origStudies = array();
		$finalStudies = array();

		$handle = fopen(self::HOME_DIR . "z_studies_convlist.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
			$origStudies[] = trim($data[0]);
			$finalStudies[] = strtolower(trim($data[1]));
		}

		var_export($origStudies);
		echo "<br>";
		echo "<br>";
		var_export($finalStudies);
		
		exit();
	}

	function addStudyHistoryAction()
	{
		set_time_limit(300);

		# REQ: baby_id, study_id, datetime, comments
		# 0: baby_id, 1+2: appointment, 3: study, 5: comments
		# 4: Cancelled->6, Completed->5, No Show->7
	
		# new below
		# allow insertion of new study ids
		# look for cancel + no show in comments
		# study outcome: completed->1, cancel->3, no show->2
	
		$db = Zend_Registry::get('db');
		$babyTbl = new Baby();
		$studyTbl = new Study();
		$researcherTbl = new Researcher();
		$shTbl = new StudyHistory();
		
		// Get study list
		$studyList = $this->getStudyList();
		// Get forgotton list
		$studyForgot = $this->tempStudyList();
	
		$rowMin = trim($this->_getParam("s"));
		$rowMax = trim($this->_getParam("e"));
	
		if(empty($rowMin) or empty($rowMax)) {
			echo "Set 's' and 'e'!";
			exit();
		}

		$rowNum = 1;
		$handle = fopen(self::HOME_DIR . "z_studyhistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";

			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;

			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			// Blank row
			$shRow = array();
			$studyId = "";
			
			$babyId = (int) trim($data[0]);
			$date = trim($data[1]);
			$study = strtolower(trim($data[3]));
			$status = trim($data[4]);
			$comments = trim($data[5]);

			// Check id
			if(empty($babyId)) {
				echo "ERROR: baby_id not found, skipping entry #{$rowNum}";
				echo "<br>";
				continue;
			}
			
			// Check study date
			if(empty($date)) {
				echo "ERROR: study date not found, skipping entry #{$rowNum}";
				echo "<br>";
				continue;
			}
			
			// Check study field
			if (empty($study)) {
				echo "ERROR: study field empty, skipping entry #{$rowNum}";
				echo "<br>";
				continue;
			}

			// Status
			$bRow = array();
			if(!empty($status)) {
				if(stripos($status, "cancel") !== false) {
					$bRow["status_id"] = 7;
					$shRow["study_outcome_id"] = 3;
				}
				elseif(stripos($status, "completed") !== false) {
					$bRow["status_id"] = 6;
					$shRow["study_outcome_id"] = 1;
				}
				elseif(stripos($status, "no show") !== false) {
					$bRow["status_id"] = 8;
					$shRow["study_outcome_id"] = 2;
				}
			}
		
			// Get date
			$time = trim($data[2]);
			if (empty($time) === false) {
				if(strlen($time) == 2)
					$time = $time . ":00";

				$datetime = $date . " " . $time;
			}
			$date = date("Y-m-d", strtotime($date));
			$datetime = date("Y-m-d H:i", strtotime($datetime));
		
			// Get study_id
			if (!empty($study)) {
				// Search keys
				$keyExist = array_key_exists($study, $studyList);
				
				// Can't find it, try a partial search
				if (!$keyExist) {
					foreach ($studyList as $key => $value) {
						if(stripos($key, $study) !== false) {
							$study = $key;
							$keyExist = True;
						}
					}
				}
				
				// Set the study
				if ($keyExist) {
					$study = $studyList[$study];
				
					$where = $db->quoteInto("study LIKE ?", $study);
					$studyRow = $studyTbl->fetchRow($where);
					
					if (empty($studyRow)) {
						echo "could not find study '$study' in db";
						echo "<br>" . PHP_EOL;
					} else {
						$studyId = $studyRow->id;
						$researcherId = $studyRow->researcher_id;
					}
				} else {
					echo "could not find study '$study' in study list";
					echo "<br>" . PHP_EOL;
					$studyId = 301;
					$researcherId = 1;
					#if(!array_search($study, $studyForgot))
					#	$studyForgot[] = $study;
				}
			}
		
			# look for cancel + no show + archive (...) in comments
			# study outcome: completed->1, cancel->3, no show->2
			# status archive -> 2
			if(!empty($comments)) {
				$shRow["comments"] = $comments;
				// Study Outcome
				if(stripos($comments, "cancel") !== false) {
					$bRow["status_id"] = 7;
					$shRow["study_outcome_id"] = 3;
				} elseif(stripos($comments, "no show") !== false) {
					$bRow["status_id"] = 8;
					$shRow["study_outcome_id"] = 2;
				}
				// Archive
				if (strpos($comments, "DO NOT CALL") !== false or strpos($comments, "NOT INTERESTED") !== false) {
					echo " ARCHIVING ";
					$bRow["status_id"] = 2;
					$bRow["checked_out"] = 0;
				} elseif (stripos($comments, "do not call") !== false and stripos($comments, "not interested") !== false) {
					echo " ARCHIVING ";
					$bRow["status_id"] = 2;
					$bRow["checked_out"] = 0;
				}
			}
		
			$shRow["baby_id"] = $babyId;
			$shRow["appointment"] = $datetime;
			$shRow["study_id"] = $studyId;

			#var_dump($data);
			#echo "<br>";
			var_dump($shRow);
			echo "<br>";
			var_dump($bRow);
			echo "<br>";
			#continue;
		
			try {
				$db->beginTransaction();
				
				// Update baby table
				if(empty($bRow) === false) {
					$where = $babyTbl->getAdapter()->quoteInto("id = ?", $babyId);
					$babyTbl->update($bRow, $where);
				}
				// New study history
				$shTbl->insert($shRow);
				echo " inserting ";
				
				$db->commit();
			} catch (Exception $e) {
				echo $shRow["baby_id"] . ": " . $e . " <br />";
				$db->rollback();
			}
		}

		fclose($handle);
		#var_export($studyForgot);
		exit();

	
	}

	function addContactHistoryAction()
	{
		set_time_limit(600);
		
		$studyForgot = $this->tempStudyList();

		$db = Zend_Registry::get('db');
		$babyTbl = new Baby();
		$chTbl = new ContactHistory();
		$callerTbl = new Callers();
		$researcherTbl = new Researcher();
		$labTbl = new Lab();
		$studyTbl = new Study();
	
		$rowMin = trim($this->_getParam("s"));
		$rowMax = trim($this->_getParam("e"));
	
		if(empty($rowMin) or empty($rowMax)) {
			echo "Set 's' and 'e'!";
			exit();
		}
	
		$rowNum = 1;
		$handle = fopen(self::HOME_DIR . "z_contacthistory.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";
		
			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;
		
			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			// Blank row
			$chRow = array();
			
			// Set variables
			$babyId = trim($data[1]);
			$date = trim($data[2]);
			$caller = strtolower(trim($data[3]));
			$study = strtolower(trim($data[4]));
			$notes = trim($data[6]);
		
			// Set baby_id
			if (!empty($babyId)) {
				$chRow["baby_id"] = (int) $babyId;
			} else {
				echo "ERROR: baby_id field is blank, skipping this entry #{$rowNum}";
				continue;
			}

			// Fix/set date
			if (!empty($date)) {
				$date = date("Y-m-d", strtotime($date));
				$chRow["DATETIME"] = $date;
			}

			// Can ignore lab column and use study column based on study list info

			// Get caller_id
			if (!empty($caller)) {
				// Check if caller equals person in list
				$callerList = $this->getCallerList();
				// Search keys for actual callername and then search in db
				if (array_key_exists($caller, $callerList)) {
					$caller = $callerList[$caller];
					
					$where = $db->quoteInto("name LIKE ?", $caller);
					$callerRow = $callerTbl->fetchRow($where);

					if (empty($callerRow)) {
						echo "could not find caller '$caller' in db";
						echo "<br>" . PHP_EOL;
						$callerId = 1;
					} else {
						$callerId = $callerRow->id;
					}
				} else {
					echo "could not find caller '$caller' in caller list";
					echo "<br>" . PHP_EOL;
					$callerId = 1;
				}
			} else {
				$callerId = 1;
			}
			$chRow["caller_id"] = $callerId;

			// Get study_id
			if (!empty($study)) {
				// Get study list
				$studyList = $this->getStudyList();
				
				// Search keys
				$keyExist = array_key_exists($study, $studyList);
				
				// Can't find it, try a partial search
				if (!$keyExist) {
					foreach ($studyList as $key => $value) {
						if(stripos($key, $study) !== false) {
							$study = $key;
							$keyExist = True;
						}
					}
				}
				
				if ($keyExist) {
					$study = $studyList[$study];
				
					$where = $db->quoteInto("study LIKE ?", $study);
					$studyRow = $studyTbl->fetchRow($where);
					
					if (empty($studyRow)) {
						echo "could not find study '$study' in db";
						echo "<br>" . PHP_EOL;
					} else {
						$studyId = $studyRow->id;
						$researcherId = $studyRow->researcher_id;
						$chRow["study_id"] = $studyId;
						$chRow["researcher_id"] = $researcherId;
					}
				} else {
					echo "could not find study '$study' in study list";
					echo "<br>" . PHP_EOL;
					#if(!array_search($study, $studyForgot))
					#	$studyForgot[] = $study;
				}
			}

			// Set comments
			if (!empty($notes)) {
				$chRow["comments"] = $notes;
			}
						
			#var_dump($data);
			#echo "<br>";
			#var_dump($chRow);
			#echo "<br><br>";
			#continue;
			
			// Check if comments says entered in it (if so update date of entry)
			if (stripos($notes, "entered") !== false) {
				try {
					$db->beginTransaction();
					
					echo "updating baby date of entry <br>";
					$where = $db->quoteInto("id = ?", $babyId);
					$babyTbl->update(array("date_of_entry" => $date), $where);
					
					$db->commit();
				} catch (Exception $e) {
					echo $chRow["baby_id"] . ": " . $e->getMessage() . " <br />";
					$db->rollback();
				}
			}
			// Check if need to archive
			if (strpos($notes, "DO NOT CALL") !== false or strpos($notes, "NOT INTERESTED") !== false or (stripos($notes, "do not call") !== false and stripos($notes, "not interested") !== false)) {
				// Row info
				$bRow = array(
					"status_id"		=> 2,
					"checked_out"	=> 0
				);
				// Update
				try {
					$db->beginTransaction();
					
					echo " ARCHIVING <br/>";
					$where = $babyTbl->getAdapter()->quoteInto("id = ?", $babyId);
					$babyTbl->update($bRow, $where);
					
					$db->commit();
				} catch (Exception $e) {
					echo $chRow["baby_id"] . ": " . $e->getMessage() . " <br />";
					$db->rollback();
				}
			}
			
			// Enter contact history
			if(empty($chRow)) {
				echo "ERROR: row empty for entry #{$rowNum}";
				echo "<br>";
			} else {			
				try {
					// Begin
					$db->beginTransaction();

					// Set attempt number
					$query = "SELECT MAX(attempt) FROM contact_histories WHERE baby_id = ?";
					$stmt = $db->query($query, array($babyId));
					$stmt->execute();
					$result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
					$chRow["attempt"] = ($result[0]) ? $result[0]+1 : 1 ;

					// Insert chrow
					echo "inserting contact history <br>";
					var_dump($chRow);
					echo "<br>";
					$chTbl->insert($chRow);

					// Done
					$db->commit();
				} catch (Exception $e) {
					echo $chRow["baby_id"] . ": " . $e->getMessage() . " <br />";
					$db->rollback();
				}
			}
		}
	
		fclose($handle);
		#echo "<br>" . PHP_EOL;
		#sort($studyForgot);
		#var_export($studyForgot);
		exit();
	}
	
	function addCallerAction()
	{
		set_time_limit(300);
		
		$callerTbl = new Callers();
		$db = Zend_Registry::get('db');
		
		$callerList = array();
		
		$row = 1;
		$handle = fopen(self::HOME_DIR . "z_callers.csv", "r");
		while (($data = fgetcsv($handle)) != FALSE) {
			$num = count($data);
			echo "<p> $num fields in line $row: <br /></p>\n";
			
			// Skip header
			$row++;
			if ($row == 2)
				continue;
				
			// Trim second column (real caller name)
			$caller = trim($data[1]);
			$origCaller = trim($data[0]);
			
			// Skip if empty
			if (empty($caller))
				continue;
				
			// Add to array
			$callerList[$origCaller] = $caller;
			
			// Find caller
			$where = $callerTbl->getAdapter()->quoteInto("name LIKE ?", $caller);
			$callerRow = $callerTbl->fetchRow($where);
			
			if ($callerRow) {
				echo "{$caller} exists" . PHP_EOL;
				echo "<br>";
			} else {
				try {
					$db->beginTransaction();
					$dataTbl = array(
						"name" 		=> $caller,
						"lab_id"	=> 1
					);
					
					#var_dump($dataTbl);
					#echo "<br>";
					
					$cId = $callerTbl->insert($dataTbl);
					echo "adding new caller ({$caller})" . PHP_EOL;
					echo "<br>";
					
					// Done
					$db->commit();
				} catch (Exception $e) {
					echo $e->getMessage();
					$db->rollback();
				}
			}
		}
		
		fclose($handle);
		var_export($callerList);
		exit();
	}

	function addOldStudyAction()
	{
		set_time_limit(500);

		$studyTbl = new Study();
		$db = Zend_Registry::get('db');
		
		$studyList = array();
	
		$row = 1;
		$handle = fopen(self::HOME_DIR . "z_oldstudies.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $row: <br /></p>\n";

			// Skip header
			$row++;
			if ($row == 2)
				continue;
			
			// trim study
			$study = trim($data[2]);
			$origStudy = trim($data[0]);
		
			// check if nothing there
			if(empty($study))
				continue;
				
			// add to array
			$studyList[$origStudy] = $study;
				
			// Check if study exists
			$where = $db->quoteInto("study LIKE ?", $study);
			$studyRow = $studyTbl->fetchRow($where);
			
			if ($studyRow) {
				echo "study '$study' already exists" . PHP_EOL;
				echo "<br>";
				continue;
			}
		
			$sRow = array(
				"study"			=> $study,
				"researcher_id"	=> 1,
				"to_use"		=> 0
			);
		
			#var_dump($data);
			#echo "<br>";
			#var_dump($sRow);
			#echo "<br><br>";
			#continue;
		
			try {
				$db->beginTransaction();
				// Insert baby row
				$studyTbl->insert($sRow);
				// Done
				$db->commit();
			} catch (Exception $e) {
				echo $e->getMessage() . " <br />";
				$db->rollback();
			}
		}
	
		fclose($handle);
		
		// spit out study list
		echo "<br>" . PHP_EOL;
		var_export($studyList);
		
		exit();
	}
	
	// Requires csv to have columns lab, researcher, study...
	// will not set study age range, need to do this manually
	function addCurrentStudyAction()
	{
		set_time_limit(300);

		$studyTbl = new Study();
		$researcherTbl = new Researcher();
		$db = Zend_Registry::get('db');
	
		$row = 1;
		$handle = fopen(self::HOME_DIR . "z_currentstudies.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $row: <br /></p>" . PHP_EOL;

			// Skip header
			$row++;
			if ($row == 2)
				continue;
		
			// Get data (trim)
			$researcher = trim($data[1]);
			$study = trim($data[2]);
			
			// skip line as study empty
			if (empty($study))
				continue;
			
			// Find study_id
			$where = $db->quoteInto("study LIKE ?", $study);
			$studyId = $studyTbl->fetchRow($where);
			
			if ($studyId) {
				echo "study $study exists" . PHP_EOL;
				echo "<br>";
				continue;
			}
			
			// Find researcher_id
			$where = $db->quoteInto("researcher LIKE ?", "{$researcher}%");
			$researcherRow = $researcherTbl->fetchRow($where);
			
			if (empty($researcherRow)) {
				echo "could not find researcher {$researcher}, setting id as None" . PHP_EOL;
				echo "<br>";
				$researcherId = 1;
			} else {
				$researcherId = $researcherRow->id;
			}
			
			$sRow = array(
				"study"			=> $study,
				"researcher_id"	=> $researcherId,
				"to_use"		=> 1
			);
			
			#var_dump($data);
			#echo "<br>";
			#var_dump($sRow);
			#continue;
		
			try {
				$db->beginTransaction();
				// Insert baby row
				$studyTbl->insert($sRow);
				// Done
				$db->commit();
			} catch (Exception $e) {
				echo $e->getMessage() . " <br />";
				$db->rollback();
			}
		}
	
		fclose($handle);
		exit();
	}

	function addBabyAction()
	{
		set_time_limit(500);

		// Baby Table
		$baby = new Baby();
		// Family Table
		$family = new Family();
		// Family Email Table
		$femail = new FamilyEmail();
		// Family Owner Table
		$fphone = new FamilyPhone();
		// Db
		$db = Zend_Registry::get('db');
	
		$rowMin = trim($this->_getParam("s"));
		$rowMax = trim($this->_getParam("e"));
	
		if(empty($rowMin) or empty($rowMax)) {
			echo "Set 's' and 'e'!";
			exit();
		}
		
		// Get duplicate families
		$duplicateFamilies = $this->getDuplicateFamilies();
		
		$row = 1;
		$handle = fopen(self::HOME_DIR . "z_child.csv", "r");
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";

			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;
		
			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
			}

			$sex = strtolower($data[5]);
			$sex = substr($sex, 0, 1);

			$brow = array(
				"id" 			=> $data[1],
				"family_id"		=> $data[0],
				"last_name"		=> $data[2],
				"middle_name"	=> $data[4],
				"first_name"	=> $data[3],
				"sex"			=> ($sex == "m") ? 2 : 1 ,
				"dob"			=> date("Y-m-d", strtotime($data[11])),
				"status_id"		=> 1,
				"comments"		=> ""
			);

			// Add $data[18] to family how_heard (if not null)
			$frow = array();
			if (!empty($data[18])) {	
				if (stripos($data[18], "tisch") !== false)
					$frow["contact_source_id"] = 2;
				elseif (stripos($data[18], "bellevue") !== false)
					$frow["contact_source_id"] = 1;
				else
					$frow["how_heard"] = $data[18];
			}

			// term if yes or full term then add otherwise to comments
			if (empty($data[22]) === false) {
				$brow["comments"] .= "term info: " . $data[22] . PHP_EOL . PHP_EOL;
				$term = strtolower($data[22]);

				// If yes
				if (strpos($term, "yes") !== false)
					$brow["term"] = 40;
				elseif (strpos($term, "full") !== false)
					$brow["term"] = 40;
				else {
					$termLen = strlen($term);
					$termNum = (float) $term;

					if (strpos($term, "weeks") !== false) {
						$termLen2 = strlen($termNum) + 6;
						if ($termLen == $termLen2)
							$brow["term"] = $termNum;
					} elseif (strpos($term, "wks") !== false) {
						$termLen2 = strlen($termNum) + 4;
						if ($termLen == $termLen2)
							$brow["term"] = $termNum;
					}
				}
			}

			if (empty($data[28]) === false)
				$brow["comments"] .= "ethnicity: " . $data[28] . PHP_EOL . PHP_EOL;

			if(empty($brow["id"]) === false) {
				#var_dump($data);
				#echo "<br>";
				var_dump($brow);
				echo "<br>";
				var_dump($frow);
				echo "<br><br>";
				#continue;
		
				$db->beginTransaction();
				try {
					// Check if family exists
					$where = $family->getAdapter()->quoteInto('id = ?', $data[0]);
					$rowExist = $family->fetchRow($where);
					
					// If not, then search for proper family
					if ($rowExist) {
						$familyId = $data[0];
					} else {
						$familyId = "";
						if (array_key_exists($data[0], $duplicateFamilies)) {
							// Get info (phone, email)
							$familyInfo = $duplicateFamilies[$data[0]];
							# 1. Check email (1st)
							if (empty($familyId) and !empty($familyInfo["email"])) {
								$where = $femail->select()->where("email = ?", $familyInfo["email"]);
								$feRow = $femail->fetchRow($where);
								// We found it
								if ($feRow)
									$familyId = $feRow->family_id;
							}
							# 2. Check phone (2nd)
							if (empty($familyId) and !empty($familyInfo["phones"])) {
								foreach ($familyInfo["phones"] as $phone) {
									$where = $fphone->select()->where("phone_number = ?", $phone);
									$fpRow = $fphone->fetchRow($where);
									// We found it
									if ($fpRow) {
										$familyId = $fpRow->family_id;
									}
								}
							}
						}
						
						// Check if got family id, add old id into comments
						if (!$familyId)
							throw new Exception("<b>CRAP FAMILY ID ({$data[0]}) NOT FOUND FOR BABY {$brow['id']}</b>");
						else
							$brow["comments"] .= "OLD FAMILY ID: " . $data[0] . PHP_EOL . PHP_EOL;
					}
					
					// Reset family id
					$brow["family_id"] = $familyId;
					
					// Insert baby row
					$baby->insert($brow);					
					// Update family
					if(empty($frow) === false) {
						$where = $family->getAdapter()->quoteInto('id = ?', $familyId);
						$family->update($frow, $where);
					}
					
					// Done
					$db->commit();
					
				} catch (Exception $e) {
					echo "NEW FAMILY ID: $familyId";
					echo "<br>";
					echo "OLD FAMILY ID: $data[0]";
					echo "<br>";
					echo $brow["id"] . ": " . nl2br($e) . " <br />";
					$db->rollback();
				}
			}
		}
	
		fclose($handle);
	
		exit();
	}

	function addFamilyAction()
	{
		set_time_limit(300);

		// Family Table
		$family = new Family();
		// Family Email Table
		$femail = new FamilyEmail();
		// Family Owner Table
		$fphone = new FamilyPhone();
	
		$rowMin = trim($this->_getParam("s"));
		$rowMax = trim($this->_getParam("e"));
	
		if(empty($rowMin) or empty($rowMax)) {
			echo "Set 's' and 'e'!";
			exit();
		}

		$conv = array(
			0 => "id",
			1 => "father_last_name", # only if 3 blank
			2 => "",
			3 => "father_last_name",
			4 => "father_first_name",
			5 => "mother_first_name",
			6 => "mother_last_name",
			7 => 1,	# family_phones: phone_number => ..., family_id => [0], family_setting_id => 1
			8 => 3, # family_phones: phone_number => ..., family_id => [0], family_setting_id => 3
			9 => "address_1",
			10 => "city",
			11 => "state",
			12 => "zip",
			13 => "zip_plus",
			14 => "",
			15 => "date_of_entry", # only if not empty
			16 => "comments", # add 'calling status: ' and then line break at end
			17 => "",
			18 => "comments", # add 'languages: ' and then line break at end
			19 => "",
			20 => "",
			21 => "",
			22 => 2, # family_phones: phone_number => ..., family_id => [0], family_setting_id => 2
			23 => "email", # family_emails: email => ..., family_id => [0]
			24 => ""
		);
		$db = Zend_Registry::get('db');
		

		$rowNum = 1;
		$handle = fopen(self::HOME_DIR . "z_family.csv", "r");
		$duplicateStuff = array();
		$oldDuplicates = $this->getDuplicateFamilies();
		while (($data = fgetcsv($handle)) !== FALSE) {
		    $num = count($data);
		    echo "<p> $num fields in line $rowNum: <br /></p>\n";

			// Skip header
			$rowNum++;
			if ($rowNum == 2)
				continue;
			elseif ($rowNum < $rowMin)
				continue;
			elseif ($rowNum > $rowMax)
				break;

			// Trim each column
			for ($c=0; $c < $num; $c++) {
				$data[$c] = trim($data[$c]);
		    }

			# Create family row
			// Get basics
			$frow = array(
				"id" 				=> $data[0],
				"father_last_name"	=> ($data[3] == "" and $data[4] != "") ? $data[1] : $data[3] ,
				"father_first_name"	=> $data[4],
				"mother_first_name"	=> $data[5],
				"mother_last_name"	=> $data[6],
				"address_1"			=> $data[9],
				"city"				=> $data[10],
				"state"				=> $data[11],
				"zip"				=> $data[12],
				#"zip_plus"			=> (empty($data[13])) ? NULL : $data[13],
				"last_update"		=> new Zend_Db_Expr('NOW()'),
				"comments"			=> ""
			);
			// Get date of entry
			if(empty($data[15]) === false)
				$frow["date_of_entry"] = date("Y-m-d", strtotime($data[15]));
			// Get comments
			if(empty($data[16]) === false)
				$frow["comments"] .= "CALLING STATUS: " . $data[16] . PHP_EOL . PHP_EOL;
			if(empty($data[18]) === false)
				$frow["comments"] .= "LANGUAGES: " . $data[18] . PHP_EOL . PHP_EOL;

			# Create family email row
			$ferow = array();
			if(!empty($data[23])) {
				$len = strpos($data[23], "#");
				$email = substr($data[23], 0, $len);
				if ($email != "nobody@home.nyu.edu") {
					$ferow = array(
						"family_id"	=> $data[0],
						"email"		=> $email
					);
				}
			}

			# Create family phone rows			
			$fprow = array();
			if(!empty($data[7])) {
				$fprow[] = array(
					"family_id"			=> $data[0],
					"family_setting_id"	=> 1,
					"phone_number"		=> $data[7]
				);
			}
			if(!empty($data[8])) {
				// make sure not equal to before
				if ($data[7] != $data[8]) {
					$fprow[] = array(
						"family_id"			=> $data[0],
						"family_setting_id"	=> 3,
						"phone_number"		=> $data[8]
					);
				}
			}
			if(!empty($data[22])) {
				if ($data[7] != $data[22] and $data[8] != $data[22]) {
					$fprow[] = array(
						"family_id"			=> $data[0],
						"family_setting_id"	=> 2,
						"phone_number"		=> $data[22]
					);
				}
			}
			# If no numbers try earlier columns ($data[2])
			if (empty($fprow) and !empty($data[2])) {
				$fprow[] = array(
					"family_id"			=> $data[0],
					"family_setting_id"	=> 1,
					"phone_number"		=> $data[2]
				);
			}
			
			# Loop through phone_numbers
			// Check phone_number length
			$numPhones = count($fprow);
			for ($i=0; $i < $numPhones; $i++) {
				if ($fprow[$i]["phone_number"] == "9999999999" or $fprow[$i]["phone_number"] == "2122222222" or $fprow[$i]["phone_number"] == "2129999999") {
					$fprow[$i]["phone_number"] = NULL;
					continue;
				}
			
				$pLen = strlen($fprow[$i]["phone_number"]);
				if ($pLen == 7) {
					$fprow[$i]["phone_number"] = "212" . $fprow[$i]["phone_number"];
				} elseif ($pLen != 10) {
					echo "WARNING PHONE NUMBER INACCURATE ";
					echo $fprow[$i]["phone_number"];
					$fprow[$i]["phone_number"] = NULL;
				}
			}
			
			if (empty($frow) === false) {
				$db->beginTransaction();
				try {
					echo "id: {$frow['id']}";
					echo "<br>";
					echo "FAMILY: {$frow['mother_last_name']} / {$frow['father_last_name']}";
							
					// Insert family row
					$family->insert($frow);
					// Insert email
					if(empty($ferow) === false) {
						$femail->insert($ferow);
					}
					// Insert phone
					if (empty($fprow) === false) {
						foreach ($fprow as $row) {
							if(empty($row["phone_number"]))
								continue;
						
							$pLen = strlen($row["phone_number"]);
							if ($pLen != 10) {
								// Don't insert
								echo "WARNING PHONE NUMBER INACCURATE ";
								echo $row["phone_number"];
								echo " ";
								echo $pLen;
							} else {
								// Now insert
								$fphone->insert($row);
							}
						}
					}
					// Done
					$db->commit();
				} catch (Exception $e) {
					echo "<br>";
					echo $frow["id"] . ": " . $e->getMessage() . " <br />";
					// Check if message is given
					if (self::FIX_DUPLICATES and strpos($e->getMessage(), 'Duplicate entry') and !array_key_exists($frow['id'], $oldDuplicates)) {
						$temp = array();
						foreach ($fprow as $row) {
							$temp[] = "'" . $row['phone_number'] . "'";
						}
						$phones = implode(", ", $temp);
						$duplicateStuff[] = "
							{$frow['id']} => array(
								<br>
							&nbsp;&nbsp;&nbsp; 'email' &nbsp;&nbsp;&nbsp;=> '{$ferow['email']}',
								<br>
							&nbsp;&nbsp;&nbsp; 'phones'	=> array({$phones})
								<br>
							)";
					}
					
					$db->rollback();
				}
			}
		
			if ($row == 4)
				break;
		}
		fclose($handle);
	
		echo "<br>";
		echo "DUPLICATE STUFF: ";
		echo "<br>";
		echo implode(", <br> ", $duplicateStuff);
		
		exit();
	}
	
	function getOrigStudies()
	{
		$studyList = array(
		  0 => 'mom advie 12',
		  1 => 'mom advise 12',
		  2 => '12 ma',
		  3 => '12 Mom Advice',
		  4 => '12 Mom Advice C',
		  5 => 'MA 12',
		  6 => 'MA 12 C',
		  7 => 'MA 12 Crawl',
		  8 => 'MA 12 Crawl / Apertures',
		  9 => 'MA 12 crawling',
		  10 => 'MA 12 W/Aperture Crawl',
		  11 => 'MA 12 walking',
		  12 => 'MA 12 Walking',
		  13 => 'MA 12C',
		  14 => 'MA 12Crawl',
		  15 => 'MA 12W or Crawling Ap',
		  16 => 'MA Crawl',
		  17 => 'MA12 crawling',
		  18 => 'MA12 or Locomotor Apertu',
		  19 => 'MA12 Walk/Crawling Apert',
		  20 => 'MA12 Walking',
		  21 => 'MA12C',
		  22 => 'MA12C or Crawling Ap',
		  23 => 'mo advice 12W',
		  24 => 'Mom  Advice 12',
		  25 => 'Mom  Advice 12 Crawl',
		  26 => 'Mom Adivce 12 Crawl',
		  27 => 'Mom Advcie 12 W',
		  28 => 'mom advice 12',
		  29 => 'Mom advice 12',
		  30 => 'Mom aDvice 12',
		  31 => 'Mom Advice 12',
		  32 => 'Mom advice 12 C',
		  33 => 'Mom Advice 12 C',
		  34 => 'mom advice 12 c/w',
		  35 => 'mom advice 12 crawl',
		  36 => 'Mom advice 12 Crawl',
		  37 => 'Mom Advice 12 crawl',
		  38 => 'Mom Advice 12 Crawl',
		  39 => 'Mom Advice 12 Crawl/walk',
		  40 => 'mom advice 12 w',
		  41 => 'Mom Advice 12 W',
		  42 => 'mom advice 12 walk',
		  43 => 'Mom Advice 12 walk',
		  44 => 'Mom Advice 12 Walk',
		  45 => 'Mom Advice 12/Locomotor',
		  46 => 'Mom Advice 12/Wobbly Cru',
		  47 => 'mom advice 12c',
		  48 => 'mom advice 12C',
		  49 => 'Mom Advice 12C',
		  50 => 'Mom Advice 12C or Locomo',
		  51 => 'mom advice 12w',
		  52 => 'mom advice 12W',
		  53 => 'Mom Advice 12W',
		  54 => 'Mom Advice Crawl',
		  55 => 'Mom Advice crawl 12',
		  56 => 'mom advice12',
		  57 => 'mom Advice12',
		  58 => 'Mom advice12',
		  59 => 'Mom Advice12',
		  60 => 'Mom Advice12 crawl',
		  61 => 'Mom Advice12 Crawl',
		  62 => 'MomAdvice12',
		  63 => 'Mom Advice (13)',
		  64 => 'mom advice 13',
		  65 => 'Mom Advice 13',
		  66 => 'Mom Advice 13 C',
		  67 => 'Mom advice 13 Crawl',
		  68 => 'Mom Advice 13 Crawl',
		  69 => 'Mom Advice 13 W',
		  70 => 'Mom Advice 13 Walk',
		  71 => 'mom advice 13c',
		  72 => 'mom advice 13crawl',
		  73 => 'Mom Advice 13W',
		  74 => 'mom advice13',
		  75 => 'Mom Advice18 Walk',
		  76 => 'mom advice18',
		  77 => 'Mom Advice18',
		  78 => 'MomAdvice 18 Walk',
		  79 => '18 Mom Advice',
		  80 => '18 mom advice w',
		  81 => 'Mom  Advice 18',
		  82 => 'Mom Adivce 18 walk',
		  83 => 'mom adive 18',
		  84 => 'Mom Adive 18',
		  85 => 'Mom Adive 18 Walk',
		  86 => 'Mom Advcie 18',
		  87 => 'mom advcie18',
		  88 => 'Mom Advice  18',
		  89 => 'Mom Advice - 18',
		  90 => 'mom advice 18',
		  91 => 'mom Advice 18',
		  92 => 'Mom advice 18',
		  93 => 'Mom Advice 18',
		  94 => 'Mom Advice-18',
		  95 => 'Mom Advice-18w',
		  96 => 'Mom Advice-18W',
		  97 => 'mom advice 18 w',
		  98 => 'Mom Advice 18 W',
		  99 => 'Mom Advice 18 Wak',
		  100 => 'Mom Advice 18 walk',
		  101 => 'Mom Advice 18 Walk',
		  102 => 'Mom Advice 18 Walkk',
		  103 => 'Mom Advice 18/Mom Comm 1',
		  104 => 'mom advice 18w',
		  105 => 'mom advice 18W',
		  106 => 'Mom advice 18w',
		  107 => 'Mom advice 18W',
		  108 => 'Mom Advice 18w',
		  109 => 'Mom Advice 18W',
		  110 => 'Mom Advice 19',
		  111 => 'abc',
		  112 => 'ABC',
		  113 => 'ABC Filming',
		  114 => 'ABC taping',
		  115 => 'ABC Taping',
		  116 => 'BBC Crawling',
		  117 => 'BBC Crawling/Cruising',
		  118 => 'BBC Taping',
		  119 => 'BBC Walk',
		  120 => 'checklist',
		  121 => 'Checklist',
		  122 => 'Mom Cliff',
		  123 => '12 Cliff',
		  124 => 'Cliff',
		  125 => 'Cliff - C',
		  126 => 'Cliff Staircase',
		  127 => 'Cliff-Lat',
		  128 => 'MA Cliff',
		  129 => 'MACliff',
		  130 => 'MomAdvice/Cliff',
		  131 => 'MomCliff',
		  132 => 'Crusing',
		  133 => 'Danger Words',
		  134 => 'Danger words Add. Questi',
		  135 => 'Danger Words Add. Questi',
		  136 => 'Danger words Add.Questio',
		  137 => 'Danger Words Add.Questio',
		  138 => 'Danger Words Study',
		  139 => 'danger Words Survey',
		  140 => 'Danger Words Survey',
		  141 => 'Danger Words Suvey',
		  142 => 'Discovery',
		  143 => 'discovery health',
		  144 => 'Discovery Health',
		  145 => 'discovery helath',
		  146 => 'Discovey Health',
		  147 => 'Crawkl/Laterality',
		  148 => 'Crawl/lat',
		  149 => 'Crawl/Lat',
		  150 => 'Crawl/Lateral',
		  151 => 'Crawl/Lateral Reach',
		  152 => 'Crawl/Laterality',
		  153 => 'Crawling/Laterality',
		  154 => 'CrawlLaterality',
		  155 => 'Cruising/Lat-Crawl',
		  156 => 'DR CrawlSit1',
		  157 => 'Lat/Crawl',
		  158 => 'Latarality',
		  159 => 'Lateralitly/Crawl',
		  160 => 'Laterality',
		  161 => 'Laterality/Crawl',
		  162 => 'Laterality/Crawling',
		  163 => 'ProneProgress',
		  164 => 'ProneProgressiob',
		  165 => 'ProneProgression',
		  166 => '1st Steps',
		  167 => 'Firdt Steps',
		  168 => 'First Ssteps',
		  169 => 'First Step',
		  170 => 'First steps',
		  171 => 'First Steps',
		  172 => 'First Steps/VT 12',
		  173 => 'First Steps/Wobbly',
		  174 => 'First Stepts',
		  175 => 'First Stesp',
		  176 => 'FirstSteps',
		  177 => 'FS',
		  178 => 'Sc First Steps',
		  179 => 'Frcition 15',
		  180 => 'Frction 15',
		  181 => 'friction',
		  182 => 'Friction',
		  183 => 'friction 15',
		  184 => 'Friction 15',
		  185 => 'friction 15/step counter',
		  186 => 'Friction15',
		  187 => 'Grant pilot',
		  188 => 'Grant Pilot',
		  189 => 'Grant pilot studies',
		  190 => 'Grant Pilot Studies',
		  191 => 'Grant Pilot- Cliff',
		  192 => 'Grant Pilot-Aperture',
		  193 => 'Grant Piloting',
		  194 => 'Grant Pilots',
		  195 => 'Grant studies',
		  196 => 'Grant Studies',
		  197 => 'Grant Studies- Cliff',
		  198 => 'Grant Studies- Ladders',
		  199 => 'Pilot studies for grant',
		  200 => 'Pilot Studies for Grant',
		  201 => 'Pilot Studies For Grant',
		  202 => 'Pilot Studies for Grants',
		  203 => 'Pilot study for grant',
		  204 => 'Pilot Study for Grant',
		  205 => 'Studies for Grant',
		  206 => 'Ap Loc',
		  207 => 'Ap Locomotor',
		  208 => 'aperture',
		  209 => 'Aperture',
		  210 => 'Aperture Crawl',
		  211 => 'Aperture Reach',
		  212 => 'Aperture W/C',
		  213 => 'Aperture Walk',
		  214 => 'Aperture Walk/Crawl',
		  215 => 'Apertures',
		  216 => 'Apertures drop',
		  217 => 'ApLocs',
		  218 => 'Crawling Aper/Mom Advice',
		  219 => 'Crawling aperture',
		  220 => 'Crawling Aperture',
		  221 => 'Crawling Apertures',
		  222 => 'Crawling/reaching Apertu',
		  223 => 'Loc  Ap W',
		  224 => 'Loc Ap',
		  225 => 'Loc AP',
		  226 => 'Loc Ap Big Kids',
		  227 => 'Loc Ap C',
		  228 => 'Loc Ap W',
		  229 => 'Loc Ap Walk',
		  230 => 'Loc Aps',
		  231 => 'Loc Aps College',
		  232 => 'LocAo',
		  233 => 'LocAp',
		  234 => 'LocAP',
		  235 => 'LocAps',
		  236 => 'Locomotor Ap',
		  237 => 'Locomotor Ap/Mom Advice',
		  238 => 'Locomotor Aperture',
		  239 => 'Locomotor Aperture C',
		  240 => 'Locomotor apertures',
		  241 => 'Locomotor Apertures',
		  242 => 'Locomotor Apertures (Wal',
		  243 => 'Locomotor Apertures C',
		  244 => 'Locomotor Apertures Craw',
		  245 => 'Locomotor Apertures W',
		  246 => 'Locomotor Apertures Walk',
		  247 => 'Locomotor Apertures/Mom',
		  248 => 'Locomotor Apetures',
		  249 => 'Locomotor W/C',
		  250 => 'Walking Ap',
		  251 => 'Walking Aperture',
		  252 => 'Walking apertures',
		  253 => 'Walking Apertures',
		  254 => 'Walking/crawling apertur',
		  255 => 'Walking/Crawling Apertur',
		  256 => 'Wallking Apertures',
		  257 => 'Flat Shoe',
		  258 => 'Flatshoe',
		  259 => 'Flatshoe 18',
		  260 => 'Flatshoe Stair',
		  261 => 'Flatshoe Staircase',
		  262 => 'FlatShoe Staircase',
		  263 => 'Flatshow',
		  264 => 'MA Flatshoe',
		  265 => 'Mom Advice 18 Gonio',
		  266 => 'Mom Advice 18 goniometer',
		  267 => 'Mom Advice 18 Goniometer',
		  268 => 'Mom Advice 18 Goniomter',
		  269 => '18 MA Plat',
		  270 => 'Mom Advice 18 plat',
		  271 => 'Mom Advice 18 platform',
		  272 => 'Mom Advice 18 Platform',
		  273 => 'Mom Advice 18 Platform/G',
		  274 => 'Mom Advice plat',
		  275 => 'Mom Advice Plat/Goni',
		  276 => 'Mom Advice Platform/Goni',
		  277 => 'MA Staircase',
		  278 => 'Mom',
		  279 => 'Mom adiv',
		  280 => 'Mom Advice Walk',
		  281 => 'Mom advice 19 Tef',
		  282 => 'Mom advice Tef',
		  283 => 'Mom Advice Tef',
		  284 => 'mom advice teflon',
		  285 => 'Mom advice teflon',
		  286 => 'Mom Advice teflon',
		  287 => 'Mom Advice Teflon',
		  288 => 'Mom Advice Teflon 19',
		  289 => 'Mom Advice Teflon/Apertu',
		  290 => 'Mom Advice Teflone',
		  291 => 'Mom Advice18 Teflon',
		  292 => 'Mom Advoce Teflon',
		  293 => 'MA 18 Teflon',
		  294 => 'MA 19 teflon',
		  295 => 'MA Tef',
		  296 => 'MA Tefllon',
		  297 => 'Ma Teflon',
		  298 => 'MA teflon',
		  299 => 'MA Teflon',
		  300 => 'MA Teflon 19',
		  301 => 'MA Teflon 19 Tef',
		  302 => 'MA Teflon Staircase',
		  303 => 'ma teflon/platform 19',
		  304 => 'MA Teflon19',
		  305 => 'MA Teflorn',
		  306 => 'MA Telfon',
		  307 => 'MATeflon',
		  308 => 'Mom Advice 18 Tef',
		  309 => 'Mom Advice 18 Tef/Goni',
		  310 => 'Mom Advice 18 Teflon',
		  311 => 'Mom Advice 18/Teflon',
		  312 => 'Mom Advice 19 Teflon',
		  313 => 'Mom Teflon',
		  314 => 'Mom Telfon',
		  315 => 'MomAdvice 18 Teflon',
		  316 => 'Mon Advice Teflon',
		  317 => 'Teflon',
		  318 => 'maching',
		  319 => 'marching',
		  320 => 'Marching',
		  321 => 'Measure',
		  322 => 'Measurement',
		  323 => 'Measurements',
		  324 => 'Measuremments',
		  325 => 'Mom Advice',
		  326 => 'MA',
		  327 => 'mom advice',
		  328 => 'mom Advice',
		  329 => 'Mom advice',
		  330 => 'Mom Advice',
		  331 => 'Mom Advice or Locomotor',
		  332 => 'Mom Advice"',
		  333 => 'Mom advise',
		  334 => 'Mom Advise',
		  335 => 'Mom Avice',
		  336 => 'Mom Advice/Comm',
		  337 => 'Mom Advice/Grant Pilot',
		  338 => 'Mom Advice/Locomotor Ap',
		  339 => 'MomAdvice',
		  340 => 'Mom Caomm 36',
		  341 => 'mom  comm 20',
		  342 => 'Mom com',
		  343 => 'Mom Com',
		  344 => 'mom com 20',
		  345 => 'Mom Com 20',
		  346 => 'Mom Com 36',
		  347 => 'Mom Com"',
		  348 => 'mom comm',
		  349 => 'Mom comm',
		  350 => 'Mom Comm',
		  351 => 'MOM comm',
		  352 => 'Mom Comm 18',
		  353 => 'Mom Comm & Mom Advice',
		  354 => 'mom comm 18',
		  355 => 'Mom comm 18',
		  356 => 'Mom Comm 18',
		  357 => 'Mom Comm 18/Info',
		  358 => 'Mom Comm 18/Mom Advice T',
		  359 => 'Mom Comm 18m',
		  360 => 'mom comm 20',
		  361 => 'mom Comm 20',
		  362 => 'Mom comm 20',
		  363 => 'Mom Comm 20',
		  364 => 'mom comm 36',
		  365 => 'Mom comm 36',
		  366 => 'Mom Comm 36',
		  367 => 'Mom comm 38',
		  368 => 'Mom Comm.',
		  369 => 'mom comm18',
		  370 => 'Mom comm18',
		  371 => 'Mom Comm18',
		  372 => 'mom comm20',
		  373 => 'Mom Comm20',
		  374 => 'mom comm36',
		  375 => 'Mom comm36',
		  376 => 'Mom Comm36',
		  377 => 'mom communication',
		  378 => 'Mom communication',
		  379 => 'Mom Communication',
		  380 => 'Mom Communication 18',
		  381 => 'momcomm',
		  382 => 'MomComm',
		  383 => 'momcomm 18',
		  384 => 'MomComm 18',
		  385 => 'momcomm 20',
		  386 => 'MomComm 36',
		  387 => 'momcomm18',
		  388 => 'MomComm18',
		  389 => 'momcomm20',
		  390 => 'MomComm36',
		  391 => 'Momm Comm',
		  392 => 'Momm Comm 18',
		  393 => 'mom expect',
		  394 => 'mom Expect',
		  395 => 'Mom expect',
		  396 => 'Mom Expect',
		  397 => 'Mom Expectation',
		  398 => 'National Geo and VT 12 W',
		  399 => 'National Geographic',
		  400 => 'National Geography',
		  401 => 'Parent\'s Beliefs',
		  402 => 'parents belief',
		  403 => 'Parents Beliefs',
		  404 => 'Parents\' Belief',
		  405 => 'parents\' beliefs',
		  406 => 'Parents\' Beliefs',
		  407 => '15 Patch',
		  408 => 'Paatch 39',
		  409 => 'patch',
		  410 => 'Patch',
		  411 => 'Patch  39',
		  412 => 'Patch 15',
		  413 => 'Patch 15 control',
		  414 => 'Patch 15 Control',
		  415 => 'patch 21',
		  416 => 'Patch 21',
		  417 => 'patch 27',
		  418 => 'Patch 27',
		  419 => 'patch 33',
		  420 => 'Patch 33',
		  421 => 'patch 39',
		  422 => 'Patch 39',
		  423 => 'Patch15',
		  424 => 'patch21',
		  425 => 'Patch21',
		  426 => 'patch27',
		  427 => 'Patch27',
		  428 => 'Patch33',
		  429 => 'Patch39',
		  430 => 'Path 39',
		  431 => 'patch control',
		  432 => 'Patch control',
		  433 => 'Patch Control',
		  434 => 'patch friction',
		  435 => 'Patch friction',
		  436 => 'Patch Friction',
		  437 => 'Patch  Long',
		  438 => 'patch long',
		  439 => 'patch Long',
		  440 => 'Patch long',
		  441 => 'Patch Long',
		  442 => 'Patch Long Pilot',
		  443 => 'patch long/mom advice',
		  444 => 'PatchLong',
		  445 => 'Pedesatl',
		  446 => 'pedestal',
		  447 => 'Pedestal',
		  448 => 'Pedestal 13',
		  449 => 'Pedestals',
		  450 => 'pedestals (25months)',
		  451 => 'Pedestals 24',
		  452 => 'Pedstal',
		  453 => 'Perserv 13',
		  454 => 'Persev',
		  455 => 'persev 13 crawl',
		  456 => 'Persev 13 crawl',
		  457 => 'Persev crawl',
		  458 => 'Persev Crawl',
		  459 => 'Persev Crawl or Mom Advi',
		  460 => 'Persev Crawl/Walk',
		  461 => 'Persev or Mom Advice',
		  462 => 'Persev Walk',
		  463 => 'Persev-Crawl',
		  464 => 'Persev-Walk',
		  465 => 'Persev:walking',
		  466 => 'Perseve Crawler',
		  467 => 'Platform',
		  468 => 'platform shoe',
		  469 => 'Platform shoe',
		  470 => 'Platform Shoe',
		  471 => 'Platform Shoe/Step Count',
		  472 => 'Platform Shoe/Weighted V',
		  473 => 'PlatformShoe',
		  474 => 'Preg Ap',
		  475 => 'Pregnant Ap',
		  476 => 'Pregnant Apertures',
		  477 => '28 Reach Ap',
		  478 => 'Infant Reaching',
		  479 => 'Reach Ap',
		  480 => 'Reach AP',
		  481 => 'Reach Ap 34',
		  482 => 'Reach Aps',
		  483 => 'Reach Aps 5-7',
		  484 => 'ReachAp',
		  485 => 'Reachin Ap',
		  486 => 'ReachinAp',
		  487 => 'Reaching',
		  488 => 'Reaching 22',
		  489 => 'Reaching 28',
		  490 => 'Reaching Ap',
		  491 => 'Reaching AP',
		  492 => 'Reaching Ap 22',
		  493 => 'Reaching Ap 34',
		  494 => 'Reaching Ap.',
		  495 => 'Reaching Aperture',
		  496 => 'Reaching apertures',
		  497 => 'Reaching Apertures',
		  498 => 'Reaching Apertures 34',
		  499 => 'Reaching Aps',
		  500 => 'ReachingA',
		  501 => 'ReachingAp',
		  502 => 'ReachingAP',
		  503 => 'Reachng Ap',
		  504 => 'Reachs Aps',
		  505 => 'Reacing',
		  506 => 'Reaching doors',
		  507 => 'Reaching Doors',
		  508 => 'Reaching Doors/Wobbly Ha',
		  509 => 'Shape sorter',
		  510 => 'Shape Sorter',
		  511 => 'Shape Sorters',
		  512 => 'Shaper sorter',
		  513 => 'Shaper Sorter',
		  514 => 'siting',
		  515 => 'sitting',
		  516 => 'Sitting',
		  517 => 'Sitting (or Reaching)',
		  518 => 'Sitting long',
		  519 => 'Sitting Long',
		  520 => 'Sitting/ Sitting Long',
		  521 => 'Sitting/Grant Pilot',
		  522 => 'Sitting/Sitting Long',
		  523 => 'Spacial',
		  524 => 'Spat Handrails',
		  525 => 'Spatail Handrails',
		  526 => 'spatial',
		  527 => 'Spatial',
		  528 => 'Spatial Handrail',
		  529 => 'spatial handrails',
		  530 => 'Spatial Handrails',
		  531 => 'step',
		  532 => 'Step Adapt',
		  533 => 'Step Adapt, Weighted Ves',
		  534 => 'Step Adapt/ Weighted Ves',
		  535 => 'Step Adapt/Weight Vest',
		  536 => 'Step adapt/weighted vest',
		  537 => 'Step Adapt/Weighted Vest',
		  538 => 'Step Adapt/WeightedVest',
		  539 => 'Weighted Vest/ Step Adap',
		  540 => 'WV/SA',
		  541 => 'Step Counder',
		  542 => 'step count',
		  543 => 'step counter',
		  544 => 'step Counter',
		  545 => 'Step counter',
		  546 => 'Step Counter',
		  547 => 'Step Counter/Friction',
		  548 => 'Step Counter/Friction 15',
		  549 => 'step cpunter',
		  550 => 'Step Cpunter',
		  551 => 'step/counter',
		  552 => 'StepCounter',
		  553 => 'stpe counter',
		  554 => 'Step Counter /WV',
		  555 => 'Step Counter/ Weighted V',
		  556 => 'Step counter/ WV',
		  557 => 'Step Counter/ WV',
		  558 => 'Step Counter/WV',
		  559 => 'step/counter/WV',
		  560 => 'StepAdapt/WeightedVest',
		  561 => 'StepCounter/WV',
		  562 => 'weighted vest/step count',
		  563 => 'Weighted Vest/Step Count',
		  564 => 'weighted vest/step coutn',
		  565 => 'weighted vest/stepcounte',
		  566 => 'Weighted Vest/stepcounte',
		  567 => 'WV/ step counter',
		  568 => 'WV/ Step counter',
		  569 => 'WV/ Step Counter',
		  570 => 'Wv/Step Counter',
		  571 => 'WV/step counter',
		  572 => 'WV/Step Counter',
		  573 => 'Video tracking',
		  574 => 'Video Tracking',
		  575 => 'Videotracking',
		  576 => 'VT',
		  577 => 'VT  12',
		  578 => 'VT 12',
		  579 => 'VT 12 C',
		  580 => 'VT 12 C/W',
		  581 => 'VT 12 Crawl',
		  582 => 'VT 12 W',
		  583 => 'VT 12 Walk',
		  584 => 'VT 12/Cliff',
		  585 => 'VT-12',
		  586 => 'VT12',
		  587 => 'VT12 C',
		  588 => 'VT12 W',
		  589 => 'VT12 Walk',
		  590 => 'VT12W',
		  591 => 'VT212',
		  592 => 'Wobbly',
		  593 => 'Wobbly / FS',
		  594 => 'wobbly cruise',
		  595 => 'Wobbly cruise',
		  596 => 'Wobbly Cruise',
		  597 => 'Wobbly Cruise/ Mom Advic',
		  598 => 'Wobbly Cruisse',
		  599 => 'wobbly crusie',
		  600 => 'Wobbly curise',
		  601 => 'Wobbly Handrails',
		  602 => 'Wobbly Rail',
		  603 => 'Wobby Cruise',
		  604 => 'Weight Vest',
		  605 => 'Weightd Vest',
		  606 => 'Weighted',
		  607 => 'weighted vest',
		  608 => 'Weighted vest',
		  609 => 'Weighted Vest',
		  610 => 'weighted vest/ps',
		  611 => 'Weighted Vest/ps',
		  612 => 'WeightedVest',
		  613 => 'Weigthed Vest',
		  614 => 'wv',
		  615 => 'WV',
		  616 => 'wv/ps',
		);
		
		return $studyList;
	}
	
	function getFinalStudies()
	{
		$studyList = array(
		  0 => '12 ma',
		  1 => '12 ma',
		  2 => '12 ma',
		  3 => '12 ma',
		  4 => '12 ma',
		  5 => '12 ma',
		  6 => '12 ma',
		  7 => '12 ma',
		  8 => '12 ma',
		  9 => '12 ma',
		  10 => '12 ma',
		  11 => '12 ma',
		  12 => '12 ma',
		  13 => '12 ma',
		  14 => '12 ma',
		  15 => '12 ma',
		  16 => '12 ma',
		  17 => '12 ma',
		  18 => '12 ma',
		  19 => '12 ma',
		  20 => '12 ma',
		  21 => '12 ma',
		  22 => '12 ma',
		  23 => '12 ma',
		  24 => '12 ma',
		  25 => '12 ma',
		  26 => '12 ma',
		  27 => '12 ma',
		  28 => '12 ma',
		  29 => '12 ma',
		  30 => '12 ma',
		  31 => '12 ma',
		  32 => '12 ma',
		  33 => '12 ma',
		  34 => '12 ma',
		  35 => '12 ma',
		  36 => '12 ma',
		  37 => '12 ma',
		  38 => '12 ma',
		  39 => '12 ma',
		  40 => '12 ma',
		  41 => '12 ma',
		  42 => '12 ma',
		  43 => '12 ma',
		  44 => '12 ma',
		  45 => '12 ma',
		  46 => '12 ma',
		  47 => '12 ma',
		  48 => '12 ma',
		  49 => '12 ma',
		  50 => '12 ma',
		  51 => '12 ma',
		  52 => '12 ma',
		  53 => '12 ma',
		  54 => '12 ma',
		  55 => '12 ma',
		  56 => '12 ma',
		  57 => '12 ma',
		  58 => '12 ma',
		  59 => '12 ma',
		  60 => '12 ma',
		  61 => '12 ma',
		  62 => '12 ma',
		  63 => '13 ma',
		  64 => '13 ma',
		  65 => '13 ma',
		  66 => '13 ma',
		  67 => '13 ma',
		  68 => '13 ma',
		  69 => '13 ma',
		  70 => '13 ma',
		  71 => '13 ma',
		  72 => '13 ma',
		  73 => '13 ma',
		  74 => '13 ma',
		  75 => '18 ma',
		  76 => '18 ma',
		  77 => '18 ma',
		  78 => '18 ma',
		  79 => '18 ma',
		  80 => '18 ma',
		  81 => '18 ma',
		  82 => '18 ma',
		  83 => '18 ma',
		  84 => '18 ma',
		  85 => '18 ma',
		  86 => '18 ma',
		  87 => '18 ma',
		  88 => '18 ma',
		  89 => '18 ma',
		  90 => '18 ma',
		  91 => '18 ma',
		  92 => '18 ma',
		  93 => '18 ma',
		  94 => '18 ma',
		  95 => '18 ma',
		  96 => '18 ma',
		  97 => '18 ma',
		  98 => '18 ma',
		  99 => '18 ma',
		  100 => '18 ma',
		  101 => '18 ma',
		  102 => '18 ma',
		  103 => '18 ma',
		  104 => '18 ma',
		  105 => '18 ma',
		  106 => '18 ma',
		  107 => '18 ma',
		  108 => '18 ma',
		  109 => '18 ma',
		  110 => '18 ma',
		  111 => 'abc',
		  112 => 'abc',
		  113 => 'abc',
		  114 => 'abc',
		  115 => 'abc',
		  116 => 'bbc',
		  117 => 'bbc',
		  118 => 'bbc',
		  119 => 'bbc',
		  120 => 'checklist',
		  121 => 'checklist',
		  122 => 'cliff',
		  123 => 'cliff',
		  124 => 'cliff',
		  125 => 'cliff',
		  126 => 'cliff',
		  127 => 'cliff',
		  128 => 'cliff',
		  129 => 'cliff',
		  130 => 'cliff',
		  131 => 'cliff',
		  132 => 'cruising',
		  133 => 'danger words',
		  134 => 'danger words',
		  135 => 'danger words',
		  136 => 'danger words',
		  137 => 'danger words',
		  138 => 'danger words',
		  139 => 'danger words',
		  140 => 'danger words',
		  141 => 'danger words',
		  142 => 'discovery',
		  143 => 'discovery',
		  144 => 'discovery',
		  145 => 'discovery',
		  146 => 'discovery',
		  147 => 'DR CrawlSit1',
		  148 => 'DR CrawlSit1',
		  149 => 'DR CrawlSit1',
		  150 => 'DR CrawlSit1',
		  151 => 'DR CrawlSit1',
		  152 => 'DR CrawlSit1',
		  153 => 'DR CrawlSit1',
		  154 => 'DR CrawlSit1',
		  155 => 'DR CrawlSit1',
		  156 => 'DR CrawlSit1',
		  157 => 'DR CrawlSit1',
		  158 => 'DR CrawlSit1',
		  159 => 'DR CrawlSit1',
		  160 => 'DR CrawlSit1',
		  161 => 'DR CrawlSit1',
		  162 => 'DR CrawlSit1',
		  163 => 'DR CrawlSit1',
		  164 => 'DR CrawlSit1',
		  165 => 'DR CrawlSit1',
		  166 => 'first steps',
		  167 => 'first steps',
		  168 => 'first steps',
		  169 => 'first steps',
		  170 => 'first steps',
		  171 => 'first steps',
		  172 => 'first steps',
		  173 => 'first steps',
		  174 => 'first steps',
		  175 => 'first steps',
		  176 => 'first steps',
		  177 => 'first steps',
		  178 => 'first steps',
		  179 => 'friction 15',
		  180 => 'friction 15',
		  181 => 'friction 15',
		  182 => 'friction 15',
		  183 => 'friction 15',
		  184 => 'friction 15',
		  185 => 'friction 15',
		  186 => 'friction 15',
		  187 => 'grant pilot',
		  188 => 'grant pilot',
		  189 => 'grant pilot',
		  190 => 'grant pilot',
		  191 => 'grant pilot',
		  192 => 'grant pilot',
		  193 => 'grant pilot',
		  194 => 'grant pilot',
		  195 => 'grant pilot',
		  196 => 'grant pilot',
		  197 => 'grant pilot',
		  198 => 'grant pilot',
		  199 => 'grant pilot',
		  200 => 'grant pilot',
		  201 => 'grant pilot',
		  202 => 'grant pilot',
		  203 => 'grant pilot',
		  204 => 'grant pilot',
		  205 => 'grant pilot',
		  206 => 'loc ap',
		  207 => 'loc ap',
		  208 => 'loc ap',
		  209 => 'loc ap',
		  210 => 'loc ap',
		  211 => 'loc ap',
		  212 => 'loc ap',
		  213 => 'loc ap',
		  214 => 'loc ap',
		  215 => 'loc ap',
		  216 => 'loc ap',
		  217 => 'loc ap',
		  218 => 'loc ap',
		  219 => 'loc ap',
		  220 => 'loc ap',
		  221 => 'loc ap',
		  222 => 'loc ap',
		  223 => 'loc ap',
		  224 => 'loc ap',
		  225 => 'loc ap',
		  226 => 'loc ap',
		  227 => 'loc ap',
		  228 => 'loc ap',
		  229 => 'loc ap',
		  230 => 'loc ap',
		  231 => 'loc ap',
		  232 => 'loc ap',
		  233 => 'loc ap',
		  234 => 'loc ap',
		  235 => 'loc ap',
		  236 => 'loc ap',
		  237 => 'loc ap',
		  238 => 'loc ap',
		  239 => 'loc ap',
		  240 => 'loc ap',
		  241 => 'loc ap',
		  242 => 'loc ap',
		  243 => 'loc ap',
		  244 => 'loc ap',
		  245 => 'loc ap',
		  246 => 'loc ap',
		  247 => 'loc ap',
		  248 => 'loc ap',
		  249 => 'loc ap',
		  250 => 'loc ap',
		  251 => 'loc ap',
		  252 => 'loc ap',
		  253 => 'loc ap',
		  254 => 'loc ap',
		  255 => 'loc ap',
		  256 => 'loc ap',
		  257 => 'ma flatshoe',
		  258 => 'ma flatshoe',
		  259 => 'ma flatshoe',
		  260 => 'ma flatshoe',
		  261 => 'ma flatshoe',
		  262 => 'ma flatshoe',
		  263 => 'ma flatshoe',
		  264 => 'ma flatshoe',
		  265 => 'ma gonio',
		  266 => 'ma gonio',
		  267 => 'ma gonio',
		  268 => 'ma gonio',
		  269 => 'ma platform',
		  270 => 'ma platform',
		  271 => 'ma platform',
		  272 => 'ma platform',
		  273 => 'ma platform',
		  274 => 'ma platform',
		  275 => 'ma platform',
		  276 => 'ma platform',
		  277 => 'ma staircase',
		  278 => 'ma staircase',
		  279 => 'ma staircase',
		  280 => 'ma staircase',
		  281 => 'ma teflon',
		  282 => 'ma teflon',
		  283 => 'ma teflon',
		  284 => 'ma teflon',
		  285 => 'ma teflon',
		  286 => 'ma teflon',
		  287 => 'ma teflon',
		  288 => 'ma teflon',
		  289 => 'ma teflon',
		  290 => 'ma teflon',
		  291 => 'ma teflon',
		  292 => 'ma teflon',
		  293 => 'ma teflon',
		  294 => 'ma teflon',
		  295 => 'ma teflon',
		  296 => 'ma teflon',
		  297 => 'ma teflon',
		  298 => 'ma teflon',
		  299 => 'ma teflon',
		  300 => 'ma teflon',
		  301 => 'ma teflon',
		  302 => 'ma teflon',
		  303 => 'ma teflon',
		  304 => 'ma teflon',
		  305 => 'ma teflon',
		  306 => 'ma teflon',
		  307 => 'ma teflon',
		  308 => 'ma teflon',
		  309 => 'ma teflon',
		  310 => 'ma teflon',
		  311 => 'ma teflon',
		  312 => 'ma teflon',
		  313 => 'ma teflon',
		  314 => 'ma teflon',
		  315 => 'ma teflon',
		  316 => 'ma teflon',
		  317 => 'ma teflon',
		  318 => 'marching',
		  319 => 'marching',
		  320 => 'marching',
		  321 => 'measurement',
		  322 => 'measurement',
		  323 => 'measurement',
		  324 => 'measurement',
		  325 => 'mom advice',
		  326 => 'mom advice',
		  327 => 'mom advice',
		  328 => 'mom advice',
		  329 => 'mom advice',
		  330 => 'mom advice',
		  331 => 'mom advice',
		  332 => 'mom advice',
		  333 => 'mom advice',
		  334 => 'mom advice',
		  335 => 'mom advice',
		  336 => 'mom advice',
		  337 => 'mom advice',
		  338 => 'mom advice',
		  339 => 'mom advice',
		  340 => 'mom comm',
		  341 => 'mom comm',
		  342 => 'mom comm',
		  343 => 'mom comm',
		  344 => 'mom comm',
		  345 => 'mom comm',
		  346 => 'mom comm',
		  347 => 'mom comm',
		  348 => 'mom comm',
		  349 => 'mom comm',
		  350 => 'mom comm',
		  351 => 'mom comm',
		  352 => 'mom comm',
		  353 => 'mom comm',
		  354 => 'mom comm',
		  355 => 'mom comm',
		  356 => 'mom comm',
		  357 => 'mom comm',
		  358 => 'mom comm',
		  359 => 'mom comm',
		  360 => 'mom comm',
		  361 => 'mom comm',
		  362 => 'mom comm',
		  363 => 'mom comm',
		  364 => 'mom comm',
		  365 => 'mom comm',
		  366 => 'mom comm',
		  367 => 'mom comm',
		  368 => 'mom comm',
		  369 => 'mom comm',
		  370 => 'mom comm',
		  371 => 'mom comm',
		  372 => 'mom comm',
		  373 => 'mom comm',
		  374 => 'mom comm',
		  375 => 'mom comm',
		  376 => 'mom comm',
		  377 => 'mom comm',
		  378 => 'mom comm',
		  379 => 'mom comm',
		  380 => 'mom comm',
		  381 => 'mom comm',
		  382 => 'mom comm',
		  383 => 'mom comm',
		  384 => 'mom comm',
		  385 => 'mom comm',
		  386 => 'mom comm',
		  387 => 'mom comm',
		  388 => 'mom comm',
		  389 => 'mom comm',
		  390 => 'mom comm',
		  391 => 'mom comm',
		  392 => 'mom comm',
		  393 => 'mom expect',
		  394 => 'mom expect',
		  395 => 'mom expect',
		  396 => 'mom expect',
		  397 => 'mom expect',
		  398 => 'nat geo',
		  399 => 'nat geo',
		  400 => 'nat geo',
		  401 => 'parents\' beliefs',
		  402 => 'parents\' beliefs',
		  403 => 'parents\' beliefs',
		  404 => 'parents\' beliefs',
		  405 => 'parents\' beliefs',
		  406 => 'parents\' beliefs',
		  407 => 'patch',
		  408 => 'patch',
		  409 => 'patch',
		  410 => 'patch',
		  411 => 'patch',
		  412 => 'patch',
		  413 => 'patch',
		  414 => 'patch',
		  415 => 'patch',
		  416 => 'patch',
		  417 => 'patch',
		  418 => 'patch',
		  419 => 'patch',
		  420 => 'patch',
		  421 => 'patch',
		  422 => 'patch',
		  423 => 'patch',
		  424 => 'patch',
		  425 => 'patch',
		  426 => 'patch',
		  427 => 'patch',
		  428 => 'patch',
		  429 => 'patch',
		  430 => 'patch',
		  431 => 'patch control',
		  432 => 'patch control',
		  433 => 'patch control',
		  434 => 'patch friction',
		  435 => 'patch friction',
		  436 => 'patch friction',
		  437 => 'patch long',
		  438 => 'patch long',
		  439 => 'patch long',
		  440 => 'patch long',
		  441 => 'patch long',
		  442 => 'patch long',
		  443 => 'patch long',
		  444 => 'patch long',
		  445 => 'pedestal',
		  446 => 'pedestal',
		  447 => 'pedestal',
		  448 => 'pedestal',
		  449 => 'pedestal',
		  450 => 'pedestal',
		  451 => 'pedestal',
		  452 => 'pedestal',
		  453 => 'persev',
		  454 => 'persev',
		  455 => 'persev',
		  456 => 'persev',
		  457 => 'persev',
		  458 => 'persev',
		  459 => 'persev',
		  460 => 'persev',
		  461 => 'persev',
		  462 => 'persev',
		  463 => 'persev',
		  464 => 'persev',
		  465 => 'persev',
		  466 => 'persev',
		  467 => 'platform shoe',
		  468 => 'platform shoe',
		  469 => 'platform shoe',
		  470 => 'platform shoe',
		  471 => 'platform shoe',
		  472 => 'platform shoe',
		  473 => 'platform shoe',
		  474 => 'preg ap',
		  475 => 'preg ap',
		  476 => 'preg ap',
		  477 => 'reach ap',
		  478 => 'reach ap',
		  479 => 'reach ap',
		  480 => 'reach ap',
		  481 => 'reach ap',
		  482 => 'reach ap',
		  483 => 'reach ap',
		  484 => 'reach ap',
		  485 => 'reach ap',
		  486 => 'reach ap',
		  487 => 'reach ap',
		  488 => 'reach ap',
		  489 => 'reach ap',
		  490 => 'reach ap',
		  491 => 'reach ap',
		  492 => 'reach ap',
		  493 => 'reach ap',
		  494 => 'reach ap',
		  495 => 'reach ap',
		  496 => 'reach ap',
		  497 => 'reach ap',
		  498 => 'reach ap',
		  499 => 'reach ap',
		  500 => 'reach ap',
		  501 => 'reach ap',
		  502 => 'reach ap',
		  503 => 'reach ap',
		  504 => 'reach ap',
		  505 => 'reach ap',
		  506 => 'reaching doors',
		  507 => 'reaching doors',
		  508 => 'reaching doors',
		  509 => 'shape sorter',
		  510 => 'shape sorter',
		  511 => 'shape sorter',
		  512 => 'shape sorter',
		  513 => 'shape sorter',
		  514 => 'sitting',
		  515 => 'sitting',
		  516 => 'sitting',
		  517 => 'sitting',
		  518 => 'sitting',
		  519 => 'sitting',
		  520 => 'sitting',
		  521 => 'sitting',
		  522 => 'sitting',
		  523 => 'spatial handrails',
		  524 => 'spatial handrails',
		  525 => 'spatial handrails',
		  526 => 'spatial handrails',
		  527 => 'spatial handrails',
		  528 => 'spatial handrails',
		  529 => 'spatial handrails',
		  530 => 'spatial handrails',
		  531 => 'step adapt',
		  532 => 'step adapt',
		  533 => 'step adapt / wv',
		  534 => 'step adapt / wv',
		  535 => 'step adapt / wv',
		  536 => 'step adapt / wv',
		  537 => 'step adapt / wv',
		  538 => 'step adapt / wv',
		  539 => 'step adapt / wv',
		  540 => 'step adapt / wv',
		  541 => 'step counter',
		  542 => 'step counter',
		  543 => 'step counter',
		  544 => 'step counter',
		  545 => 'step counter',
		  546 => 'step counter',
		  547 => 'step counter',
		  548 => 'step counter',
		  549 => 'step counter',
		  550 => 'step counter',
		  551 => 'step counter',
		  552 => 'step counter',
		  553 => 'step counter',
		  554 => 'step counter / wv',
		  555 => 'step counter / wv',
		  556 => 'step counter / wv',
		  557 => 'step counter / wv',
		  558 => 'step counter / wv',
		  559 => 'step counter / wv',
		  560 => 'step counter / wv',
		  561 => 'step counter / wv',
		  562 => 'step counter / wv',
		  563 => 'step counter / wv',
		  564 => 'step counter / wv',
		  565 => 'step counter / wv',
		  566 => 'step counter / wv',
		  567 => 'step counter / wv',
		  568 => 'step counter / wv',
		  569 => 'step counter / wv',
		  570 => 'step counter / wv',
		  571 => 'step counter / wv',
		  572 => 'step counter / wv',
		  573 => 'vt 12',
		  574 => 'vt 12',
		  575 => 'vt 12',
		  576 => 'vt 12',
		  577 => 'vt 12',
		  578 => 'vt 12',
		  579 => 'vt 12',
		  580 => 'vt 12',
		  581 => 'vt 12',
		  582 => 'vt 12',
		  583 => 'vt 12',
		  584 => 'vt 12',
		  585 => 'vt 12',
		  586 => 'vt 12',
		  587 => 'vt 12',
		  588 => 'vt 12',
		  589 => 'vt 12',
		  590 => 'vt 12',
		  591 => 'vt 12',
		  592 => 'wobbly cruise',
		  593 => 'wobbly cruise',
		  594 => 'wobbly cruise',
		  595 => 'wobbly cruise',
		  596 => 'wobbly cruise',
		  597 => 'wobbly cruise',
		  598 => 'wobbly cruise',
		  599 => 'wobbly cruise',
		  600 => 'wobbly cruise',
		  601 => 'wobbly cruise',
		  602 => 'wobbly cruise',
		  603 => 'wobbly cruise',
		  604 => 'wv',
		  605 => 'wv',
		  606 => 'wv',
		  607 => 'wv',
		  608 => 'wv',
		  609 => 'wv',
		  610 => 'wv',
		  611 => 'wv',
		  612 => 'wv',
		  613 => 'wv',
		  614 => 'wv',
		  615 => 'wv',
		  616 => 'wv',
		);
		
		return $studyList;
	}
	
	function getCallerList()
	{
		$callerList = array (
		  'ali' => 'Ali',
		  'alia' => 'AliaMartin',
		  'alice' => 'Alice',
		  'alice/daryaneh' => 'Alice',
		  'alicia' => 'Alicia',
		  'alison' => 'Allison',
		  'aliza' => 'Aliza',
		  'allison' => 'Allison',
		  'amanda' => 'Amanda',
		  'amy' => 'Amy',
		  'amy r' => 'Amy',
		  'amy r/dad' => 'Amy',
		  'amy r/j' => 'Amy',
		  'amy r/mike' => 'Amy',
		  'amy r/mom' => 'Amy',
		  'amy/jessie' => 'Amy',
		  'amyr' => 'Amy',
		  'amyr/mom/amy j' => 'Amy',
		  'angela' => 'Angela',
		  'ann' => 'Anna',
		  'anna' => 'Anna',
		  'anna (entered)' => 'Anna',
		  'anna/michael' => 'Anna',
		  'antonia' => 'Antonia',
		  'aravinda' => 'Aravinda',
		  'ariela' => 'Ariela',
		  'arieta' => 'Arieta',
		  'bceky' => 'Becky',
		  'becky' => 'Becky',
		  'beclu' => 'Becky',
		  'bekcy' => 'Becky',
		  'beth' => 'Beth',
		  'betina' => 'Betina',
		  'carolyn' => 'Carolyn',
		  'casey' => 'Casey',
		  'catharine' => 'CatharineLennon',
		  'dad/amy r' => 'Amy',
		  'dad/juliet' => 'Juliet',
		  'dad/melissa' => 'Melissa',
		  'dad/priya' => 'Priya',
		  'daielle' => 'Danielle',
		  'dainelle' => 'Danielle',
		  'danielel' => 'Danielle',
		  'danielle' => 'Danielle',
		  'danielle np-9mo' => 'Danielle',
		  'danya/beth' => 'Bath',
		  'daryaheh' => 'DaryanehBadaly',
		  'daryaneh' => 'DaryanehBadaly',
		  'daryaneh/alice' => 'DaryanehBadaly',
		  'daryaneh/antonia' => 'DaryanehBadaly',
		  'daryaneh/jessie' => 'DaryanehBadaly',
		  'daryaneh`' => 'DaryanehBadaly',
		  'dima' => 'Dina',
		  'dina' => 'Dina',
		  'dina/shaz' => 'Dina',
		  'eleni' => 'EleniMathioudakis',
		  'elika' => 'Elika',
		  'ellen' => 'Ellen',
		  'ellie' => 'Ellie',
		  'grace' => 'Grace',
		  'grace/jessie' => 'Grace',
		  'grace/patrice' => 'Grace',
		  'grace/priya' => 'Grace',
		  'grace/sharon' => 'Grace',
		  'grace/shaz' => 'Grace',
		  'hanna' => 'HannaGelfand',
		  'heidi' => 'Heidi',
		  'hugh' => 'HughRabagliati',
		  'ilan' => 'Ilana',
		  'ilana' => 'Ilana',
		  'ilana`' => 'Ilana',
		  'ingrid' => 'Ingrid',
		  'jamie' => 'Jamie',
		  'jen' => 'Jennifer',
		  'jenifer' => 'Jennifer',
		  'jenn' => 'Jennifer',
		  'jennfer' => 'Jennifer',
		  'jennifer' => 'Jennifer',
		  'jennifre' => 'Jennifer',
		  'jessi' => 'Jessie',
		  'jessie' => 'Jessie',
		  'jessie `' => 'Jessie',
		  'jessie/daryaneh' => 'Jessie',
		  'john' => 'JohnFranchak',
		  'john/michael' => 'JohnFranchak',
		  'josette' => 'JosiePlumey',
		  'josie' => 'JosiePlumey',
		  'josie
		osie' => 'JosiePlumey',
		  'josie`' => 'JosiePlumey',
		  'judy' => 'Judy',
		  'julia' => 'Julia',
		  'julia / michael' => 'Julia',
		  'julia fisher' => 'Julia',
		  'julia/dary' => 'Julia',
		  'julia/lana' => 'Julia',
		  'julia/michael' => 'Julia',
		  'julia/shaz/dary' => 'Julia',
		  'juliet' => 'Juliet',
		  'kara' => 'Kara',
		  'karen' => 'Karen',
		  'kasey' => 'KaseySoska',
		  'kasey/mike' => 'KaseySoska',
		  'kath' => 'Katherine',
		  'katherine' => 'Katherine',
		  'katherine/judy' => 'Katherine',
		  'katie' => 'Katie',
		  'kaveri' => 'Kaveri',
		  'keith' => 'Keith',
		  'kelsey' => 'Kelsey',
		  'kevin' => 'Kevin',
		  'kri' => 'Kristin',
		  'kris' => 'Kristin',
		  'krisitin' => 'Kristin',
		  'krisitn' => 'Kristin',
		  'kristih' => 'Kristin',
		  'kristin' => 'Kristin',
		  'lan' => 'LanaKarasik',
		  'lana' => 'LanaKarasik',
		  'lana (judy)' => 'LanaKarasik',
		  'lance' => 'LanceRappaport',
		  'larissa' => 'LarissaGabelman',
		  'laruen' => 'Lauren',
		  'laura' => 'Lauren',
		  'laura`' => 'Lauren',
		  'lauren' => 'Lauren',
		  'lauren/talia' => 'Lauren',
		  'leichen' => 'Leischen',
		  'leila' => 'Leila',
		  'leischen' => 'Leischen',
		  'leishcen' => 'Leischen',
		  'leishen' => 'Leischen',
		  'lindsey' => 'Lindsey',
		  'lindsey  (lc)' => 'Lindsey',
		  'lindsey (lc)' => 'Lindsey',
		  'liz' => 'Liz',
		  'liz/judy' => 'Liz',
		  'liz/simone' => 'Liz',
		  'madeline' => 'Madeline',
		  'madline' => 'Madeline',
		  'mae' => 'Mae',
		  'malynn' => 'Malynn',
		  'margot' => 'Margot',
		  'megan' => 'Megan',
		  'meital' => 'Meital',
		  'melisa' => 'Melissa',
		  'meliss' => 'Melissa',
		  'melissa' => 'Melissa',
		  'melissa/mom' => 'Melissa',
		  'mellissa' => 'Melissa',
		  'melssa' => 'Melissa',
		  'meryl' => 'Meryl',
		  'michael' => 'Michael',
		  'michael & jessie' => 'Michael',
		  'michael (jessie)' => 'Michael',
		  'michael (mom)' => 'Michael',
		  'michael/jessie' => 'Michael',
		  'michael/julia' => 'Michael',
		  'michael/sarah' => 'Michael',
		  'mie' => 'Michael',
		  'mike' => 'Michael',
		  'mike & jessie' => 'Michael',
		  'mike/jessie' => 'Michael',
		  'mike/mom' => 'Michael',
		  'mikw' => 'Michael',
		  'mk' => 'Michael',
		  'mke' => 'Michael',
		  'mom (amyr)' => 'Amy',
		  'mom (michael)' => 'Michael',
		  'mom (mike)' => 'Michael',
		  'mom called margot' => 'Margot',
		  'mom/ melissa' => 'Melissa',
		  'mom/amy r' => 'Amy',
		  'mom/amyr' => 'Amy',
		  'mom/angela' => 'Angela',
		  'mom/beth' => 'Beth',
		  'mom/hugh' => 'Hugh',
		  'mom/juliet' => 'Juliet',
		  'mom/kelsey' => 'Kelsey',
		  'mom/lana' => 'LanaKarasik',
		  'mom/melissa' => 'Melissa',
		  'mom/mike' => 'Michael',
		  'mom/rebecca' => 'Rebecca',
		  'mom/shelby' => 'Shelby',
		  'moniqe' => 'Monique',
		  'monique' => 'Monique',
		  'moniuqe' => 'Monique',
		  'moniwue' => 'Monique',
		  'morayo' => 'Morayo',
		  'morayo

		morayo' => 'Morayo',
		  'mya' => 'Mya',
		  'neeru' => 'Neeru',
		  'neeur' => 'Neeru',
		  'nick' => 'Nikki',
		  'nikki' => 'Nikki',
		  'nkki' => 'Nikki',
		  'noelle' => 'Noelle',
		  'patrica' => 'Patricia',
		  'patrice' => 'Patricia',
		  'patricia' => 'Patricia',
		  'preeya' => 'Priya',
		  'priya' => 'Priya',
		  'priya/grace' => 'Priya',
		  'priya/liz' => 'Priya',
		  'rach' => 'Rachel',
		  'rachel' => 'Rachel',
		  'rbecca' => 'Rebecca',
		  'rebec a' => 'Rebecca',
		  'rebeca' => 'Rebecca',
		  'rebecac' => 'Rebecca',
		  'rebecc' => 'Rebecca',
		  'rebecca' => 'Rebecca',
		  'rebecca/mom' => 'Rebecca',
		  'rebeccap' => 'Rebecca',
		  'rebeecca' => 'Rebecca',
		  'rula' => 'Rula',
		  'sahnnon' => 'Shannon',
		  'sarah' => 'Sarah',
		  'sarah/michael' => 'Sarah',
		  'sarah?michael' => 'Sarah',
		  'sha ron' => 'Sharon',
		  'shaanon' => 'Shannon',
		  'shannin' => 'Shannon',
		  'shannn' => 'Shannon',
		  'shannnon' => 'Shannon',
		  'shannon' => 'Shannon',
		  'shanon' => 'Shannon',
		  'sharon' => 'Sharon',
		  'sharon/lana' => 'Sharon',
		  'shaz' => 'ShazielaIshak',
		  'shaziea' => 'ShazielaIshak',
		  'shaziel' => 'ShazielaIshak',
		  'shaziela' => 'ShazielaIshak',
		  'shaziels' => 'ShazielaIshak',
		  'shelby' => 'Shelby',
		  'shnnon' => 'Shannon',
		  'simon' => 'SimoneGill',
		  'simone' => 'SimoneGill',
		  'sneh' => 'SnehKadakia',
		  'sneh kadakia' => 'SnehKadakia',
		  'steph/kath' => 'Stephanie',
		  'stephanie/jessie' => 'Stephanie',
		  'stephanie/kath' => 'Stephanie',
		  'stephanie/katherine' => 'Stephanie',
		  'talia' => 'Talia',
		  'tracie' => 'Tracie',
		  'victoria' => 'Victoria',
		  'vivi' => 'Vivianna',
		  'viviann' => 'Vivianna',
		  'vivianna' => 'Vivianna',
		  'wendy' => 'Wendy',
		  'whitney' => 'Whitney',
		  'wwendy' => 'Wendy',
		  'ximena' => 'Ximena',
		  'yuia' => 'YuliaVeras',
		  'yulia' => 'YuliaVeras',
		  'yulia veras' => 'YuliaVeras',
		);
		
		return($callerList);
	}
	
	# HEY
	
	function getStudyList()
	{
		$studyList = array (
		  '12 cliff' => 'Cliff',
		  '12 mom advice' => '12 MA',
		  '12 mom advice c' => '12 MA',
		  '12. momadvice' => '12 MA',
		  '12.ma crawl' => '12 MA',
		  '12.mom advice' => '12 MA',
		  '12.momadvice.crawl' => '12 MA',
		  '12b' => 'Exp 12B',
		  '12b*' => 'Exp 12B',
		  '12b, update' => 'Exp 12B',
		  '12b/nad & wa' => 'Exp 12B',
		  '12mom advice crawl' => '12 MA',
		  '15 patch' => 'Patch',
		  '15.patch.lights' => 'Patch',
		  '18 ma goniometer' => 'MA Gonio',
		  '18 ma plat' => 'MA Platform',
		  '18 ma platform' => 'MA Platform',
		  '18 mom advice' => '18 MA',
		  '18 momadvice' => '18 MA',
		  '18.momadvice w' => '18 MA',
		  '19.ma teflon' => 'MA Teflon',
		  '19.ma.teflon' => 'MA Teflon',
		  '19mateflon' => 'MA Teflon',
		  '1st steps' => 'First Steps',
		  'patch 21' => "Patch",
		  'patch 27' => "Patch",
		  '21patch' => 'Patch',
		  '28 reach ap' => 'Reach Ap',
		  '34 reaching ap' => 'Reach Ap',
		  '4 mo pi test' => '4 mopi',
		  '4mo pi' => '4 mopi',
		  '4mopi' => '4 mopi',
		  '4mopi johnson' => '4 mopi',
		  '4mopi test' => '4 mopi',
		  '4mopi/4 mostudy' => '4 mopi',
		  '4mostudy' => '4 mopi',
		  '4mpi' => '4 mopi',
		  'abc filming' => 'ABC',
		  'abc taping' => 'ABC',
		  'ap loc' => 'Loc Ap',
		  'ap locomotor' => 'Loc Ap',
		  'aperture  walk' => 'Loc Ap',
		  'aperture crawl' => 'Loc Ap',
		  'aperture drop' => 'Loc Ap',
		  'aperture reach' => 'Loc Ap',
		  'aperture w/c' => 'Loc Ap',
		  'aperture walk' => 'Loc Ap',
		  'aperture walk/crawl' => 'Loc Ap',
		  'apertures' => 'Loc Ap',
		  'apertures drop' => 'Loc Ap',
		  'aplocs' => 'Loc Ap',
		  'arl pitch' => 'arl pitch',
		  'arl-pitch' => 'arl pitch',
		  'arl-ptich' => 'arl pitch',
		  'axaby' => 'axbaby',
		  'axaby
		axaby' => 'axbaby',
		  'axaby (after 7/7)' => 'axbaby',
		  'axaby (for 7/7 or later)' => 'axbaby',
		  'axaby (head turn?)' => 'axbaby',
		  'axaby (instead of lps)' => 'axbaby',
		  'axaby (practice)' => 'axbaby',
		  'axaby practice' => 'axbaby',
		  'axaby/headturn' => 'axbaby',
		  'axyb' => 'axyb',
		  'axyby' => 'axyb',
		  'ball seach' => 'ball search',
		  'ball search' => 'ball search',
		  'ballseach' => 'ball search',
		  'ballsearch' => 'ball search',
		  'bbc crawling' => 'BBC',
		  'bbc crawling/cruising' => 'BBC',
		  'bbc filming' => 'BBC',
		  'bbc taping' => 'BBC',
		  'bbc walk' => 'BBC',
		  'bounce stream' => 'bounce stream',
		  'bounce stream, vm8' => 'bounce stream',
		  'bounce strean' => 'bounce stream',
		  'bounce strream' => 'bounce stream',
		  'bounce-sream' => 'bounce stream',
		  'bounce-stram' => 'bounce stream',
		  'bounce-stream' => 'bounce stream',
		  'bounce-stream, visual marcus-8 mo' => 'bounce stream',
		  'bounce-stream, vm- 8mo' => 'bounce stream',
		  'bounce-stream, vm-8' => 'bounce stream',
		  'bounce-stream, vm-8 mo' => 'bounce stream',
		  'bounce-stream; identity' => 'bounce stream',
		  'bounce-stream; visual marc-8 mo' => 'bounce stream',
		  'bounce-stream; visual marcus-11' => 'bounce stream',
		  'bounce-stream; vm-abb vs. aab-8mo' => 'bounce stream',
		  'bounce-stream; vmabbvsaab-8 mo' => 'bounce stream',
		  'box' => 'Box',
		  'box & s#' => 'Box',
		  'box &s#' => 'Box',
		  'box id' => 'Box',
		  'box io' => 'Box',
		  'box label' => 'Box',
		  'box label/modr' => 'Box',
		  'box vol' => 'Box',
		  'box&s#' => 'Box',
		  'box, s#' => 'Box',
		  'box,s#' => 'Box',
		  'box10' => 'Box',
		  'box12' => 'Box',
		  'box12 &s#' => 'Box',
		  'boxdo' => 'Box',
		  'boxid' => 'Box',
		  'boxid & s#' => 'Box',
		  'boxid & snack#' => 'Box',
		  'boxid &s#' => 'Box',
		  'boxid, s#' => 'Box',
		  'boxid,s#' => 'Box',
		  'boxio' => 'Boxio',
		  'boxlab' => 'Boxlabel',
		  'boxlabel' => 'Boxlabel',
		  'boxp10' => 'Box',
		  'boxpilot' => 'Box',
		  'boxv' => 'Boxvolume',
		  'boxvol' => 'Boxvolume',
		  'boxvolume' => 'Boxvolume',
		  'bs' => 'bs',
		  'bs & s#' => 'bs & s#',
		  'bs &s#' => 'bs & s#',
		  'bs,s#' => 'bs & s#',
		  'bs/er3' => 'bs',
		  'bs/vm' => 'bs/vm',
		  'category' => 'Category',
		  'category 9mo' => 'Category',
		  'categroy' => 'Category',
		  'chinese tones' => 'Chines Tones',
		  'cliff' => 'Cliff',
		  'cliff - c' => 'Cliff',
		  'cliff 12' => 'Cliff',
		  'cliff 18 stair' => 'Cliff',
		  'cliff staircase' => 'Cliff',
		  'cliff-lat' => 'Cliff',
		  'cliff/staircase' => 'Cliff',
		  'crawkl/laterality' => 'DR CrawlSit1',
		  'crawl' => 'DR CrawlSit1',
		  'crawl laterality' => 'DR CrawlSit1',
		  'crawl only' => 'DR CrawlSit1',
		  'crawl progress/lateral' => 'DR CrawlSit1',
		  'crawl progression' => 'DR CrawlSit1',
		  'crawl progression/laterality' => 'DR CrawlSit1',
		  'crawl/cruising' => 'DR CrawlSit1',
		  'crawl/lat' => 'DR CrawlSit1',
		  'crawl/lateral' => 'DR CrawlSit1',
		  'crawl/lateral reach' => 'DR CrawlSit1',
		  'crawl/laterality' => 'DR CrawlSit1',
		  'crawl/lateraliy' => 'DR CrawlSit1',
		  'crawl/reach' => 'DR CrawlSit1',
		  'crawling' => 'DR CrawlSit1',
		  'dr crawlsit' => 'DR CrawlSit1',
		  'crawling aper/mom advice 12' => 'Loc Ap',
		  'crawling aperture' => 'Loc Ap',
		  'crawling apertures' => 'Loc Ap',
		  'crawling reaching apertures' => 'Loc Ap',
		  'crawling study' => 'DR CrawlSit1',
		  'crawling/laterality' => 'DR CrawlSit1',
		  'crawling/reaching apertures' => 'Loc Ap',
		  'crawllaterality' => 'DR CrawlSit1',
		  'crowding' => 'Crowding',
		  'crowding pilot' => 'Crowding',
		  'cruise' => 'Cruising',
		  'cruisers' => 'Cruising',
		  'cruising' => 'Cruising',
		  'cruising/lat-crawl' => 'DR CrawlSit1',
		  'crusing' => 'Cruising',
		  'danger words' => 'Danger Words',
		  'danger words add. questions' => 'Danger Words',
		  'danger words add.questions' => 'Danger Words',
		  'danger words study' => 'Danger Words',
		  'danger words survey' => 'Danger Words',
		  'danger words suvey' => 'Danger Words',
		  'dax' => 'dax',
		  'dax, s#' => 'dax &s#',
		  'dax,s#' => 'dax &s#',
		  'daxcontrol' => 'dax',
		  'detour' => 'detours',
		  'detours' => 'detours',
		  'discov' => 'Discovery',
		  'discovery' => 'Discovery',
		  'discovery health (pedestal)' => 'Discovery',
		  'discovey health' => 'Discovery',
		  'dogs' => 'Dogs',
		  'dogs & saff rep' => 'Dogs & Saff Rep',
		  'dogs & wa' => 'Dog & WA',
		  'dogs and shapes' => 'Dogs & Shapes',
		  'dogs re-do' => 'Dogs',
		  'dogs replication' => 'Dogs',
		  'dogs/of200' => 'Dogs & OF200',
		  'dogs/shape' => 'Dogs & Shapes',
		  'dogs/shapes' => 'Dogs & Shapes',
		  'dogs/signs' => 'Dogs & Signs',
		  'dogs/timbre med' => 'Dogs & Timbre',
		  'em' => 'EM',
		  'em tracking' => 'EM',
		  'em tracking, update' => 'EM',
		  'em tracking,update' => 'EM',
		  'em/of' => 'EM & OF',
		  'em/of300' => 'EM  & OF300',
		  'emotion match' => 'EM',
		  'emotion matching' => 'EM',
		  'er' => 'ER',
		  'er-3' => 'ER',
		  'er2' => 'ER',
		  'er3' => 'ER',
		  'et rules' => 'ET Rules',
		  'et rules, update' => 'ET Rules',
		  'et rules,update' => 'ET Rules',
		  'et rules/voiced aba' => 'ET Rules',
		  'et_rules' => 'ET Rules',
		  'ex3' => 'Exp 3',
		  'exp 12 b' => 'Exp 12B',
		  'exp 12b' => 'Exp 12B',
		  'exp 12b*' => 'Exp 12B',
		  'exp 12b, update' => 'Exp 12B',
		  'exp 12b/' => 'Exp 12B',
		  'exp 12b/ nad&wa' => 'Exp 12B',
		  'exp 3' => 'Exp 3',
		  'exp 5' => 'Exp 5',
		  'exp 5, update' => 'Exp 5',
		  'exp 5/ sentences' => 'Exp 5',
		  'exp 5/ signs 11' => 'Exp 5',
		  'exp 5/ voiced aba' => 'Exp 5',
		  'exp 5/sentences' => 'Exp 5',
		  'exp 5/signs' => 'Exp 5',
		  'exp 5/signs 11' => 'Exp 5',
		  'exp 5/signs-11' => 'Exp 5',
		  'exp12' => 'Exp 12',
		  'exp12a' => 'Exp 12A',
		  'exp12b' => 'Exp 12B',
		  'exp12b*' => 'Exp 12B',
		  'exp15' => 'Exp 15',
		  'exp3' => 'Exp 3',
		  'exp3-p' => 'Exp 3',
		  'exp5' => 'Exp 5',
		  'exp5 + analogy' => 'Exp 5',
		  'exp5 / signs-11' => 'Exp 5',
		  'exp5 / signs11' => 'Exp 5',
		  'exp5/ signs-11' => 'Exp 5',
		  'exp5/signs' => 'Exp 5',
		  'eye tracking' => 'EM',
		  'face percep' => 'Face Perception',
		  'face percept' => 'Face Perception',
		  'face perception' => 'Face Perception',
		  'face perception & mc5' => 'Face Perception',
		  'face perception (5 mo)' => 'Face Perception',
		  'face perception, tobii pilot' => 'Face Perception',
		  'face perception-5 mo' => 'Face Perception',
		  'face perception-7mo' => 'Face Perception',
		  'face perceptionj' => 'Face Perception',
		  'face perceptions' => 'Face Perception',
		  'face percpetion' => 'Face Perception',
		  'face-perception' => 'Face Perception',
		  'faces' => 'Faces',
		  'first ssteps' => 'First Steps',
		  'first step' => 'First Steps',
		  'first steps' => 'First Steps',
		  'first steps (1)' => 'First Steps',
		  'first steps (1st visit)' => 'First Steps',
		  'first steps (2)' => 'First Steps',
		  'first steps (2nd visit)' => 'First Steps',
		  'first steps (3rd visit)' => 'First Steps',
		  'first steps (sess 1)' => 'First Steps',
		  'first steps (sess 2)' => 'First Steps',
		  'first steps (sess1)' => 'First Steps',
		  'first steps (sess2)' => 'First Steps',
		  'first steps 1st visit' => 'First Steps',
		  'first steps 2nd visit' => 'First Steps',
		  'first steps sess 2' => 'First Steps',
		  'first steps session 2' => 'First Steps',
		  'first steps session1' => 'First Steps',
		  'first steps(sess1)' => 'First Steps',
		  'first steps/vt 12' => 'First Steps',
		  'first steps/wobbly' => 'First Steps',
		  'first stepts' => 'First Steps',
		  'first stesp' => 'First Steps',
		  'firststeps' => 'First Steps',
		  'firststeps (sess1)' => 'First Steps',
		  'flat shoe' => 'MA Flatshoe',
		  'flatshoe' => 'MA Flatshoe',
		  'flatshoe 18' => 'MA Flatshoe',
		  'flatshoe stair' => 'MA Flatshoe',
		  'flatshoe staircase' => 'MA Flatshoe',
		  'flatshow' => 'MA Flatshoe',
		  'fp' => 'Face Perception',
		  'fp & mc5' => 'Face Perception & MC5',
		  'fp&  mc5' => 'Face Perception & MC5',
		  'fp2' => 'Face Perception 2',
		  'fp2 & mc 5' => 'Face Perception 2 & MC5',
		  'fp2 & mc5' => 'Face Perception 2 & MC5',
		  'fp2 & mh' => 'Face Perception 2 & MH5',
		  'fp2 & mh5' => 'Face Perception 2 & MH5',
		  'fp2 & vmm' => 'Face Perception 2 & VMM5',
		  'fp2 & vmm5' => 'Face Perception 2 & VMM5',
		  'fp2 &mc5' => 'Face Perception & MC5',
		  'fp2 &mh' => 'Face Perception 2 & MH5',
		  'fp2 &mh5' => 'Face Perception 2 & MH5',
		  'fp2& mc5' => 'Face Perception 2 & MH5',
		  'friction' => 'Friction 15',
		  'friction 15' => 'Friction 15',
		  'frcition 15' => 'Friction 15',
		  'frction 15' => 'Friction 16',
		  'free v' => 'Free V',
		  'free v & emotion match' => 'Free V & EM',
		  'free v / emotion match' => 'Free V & EM',
		  'free v 12m' => 'Free V',
		  'free v 12mo' => 'Free V',
		  'free v 2mo' => 'Free V',
		  'free v 3mo' => 'Free V & Pi Eyetracking',
		  'free v 3mo/pi eyetracking' => 'Free V & Pi Eyetracking',
		  'free v 9 mo' => 'Free V',
		  'free v 9m & picture grasp' => 'Free V & Picture Grasping',
		  'free v 9mo' => 'Free V & EM',
		  'free v 9mo& pict grasp' => 'Free V & Picture Grasping',
		  'free v and picture grasping' => 'Free V & Picture Grasping',
		  'free v/pi picture grasp' => 'Free V & Picture Grasping',
		  'free v/pi3' => 'Free V & pi3',
		  'free viewing' => 'Free V',
		  'free viewing 9mo' => 'Free V',
		  'free viewing/picture grasping' => 'Free V & Picture Grasping',
		  'free-v' => 'Free V',
		  'free-v & emotion match' => 'Free V & EM',
		  'free-v 12mo' => 'Free V',
		  'free-v 3mo' => 'Free V',
		  'free-v 9mo' => 'Free V',
		  'free-v or pi 4mo' => 'Free V & pi4',
		  'free-v, picture grasping' => 'Free V & Picture Grasping',
		  'free-v-3mo' => 'Free V',
		  'free-v/pi3' => 'Free V & pi3',
		  'freev' => 'Free V',
		  'freev & object man' => 'Free V & Object Manipulation',
		  'freev & object manipulation' => 'Free V & Object Manipulation',
		  'freev & pi picture grasp' => 'Free V & Picture Grasping',
		  'freev 9mo & pict grasp' => 'Free V & Picture Grasping',
		  'freev and pict grasp' => 'Free V & Picture Grasping',
		  'freev, pi picture grasping' => 'Free V & Picture Grasping',
		  'freev/pi picture grasp' => 'Free V & Picture Grasping',
		  'freev/pi picture grasping' => 'Free V & Picture Grasping',
		  'freev/pipicture grasp' => 'Free V & Picture Grasping',
		  'freeview' => 'Free V',
		  'freeviewing' => 'Free V',
		  'funny faces' => 'Funny Faces',
		  'funny faces & wa-symb' => 'Funny Faces & WA Symb',
		  'funny faces/ stats tones' => 'Funny Faces & Stats Tones',
		  'fv' => 'Free V',
		  'fv 12' => 'Free V',
		  'fv 12m' => 'Free V',
		  'fv 12mo' => 'Free V',
		  'fv 3mo' => 'Free V',
		  'fv 6mo' => 'Free V',
		  'fv 6mo (kasey sick)' => 'Free V',
		  'fv 9mo' => 'Free V',
		  'fv 9mo & picture grasp' => 'Free V & Picture Grasping',
		  'fv or mental rotation' => 'Free V',
		  'fv-12' => 'Free V',
		  'fv-3' => 'Free V',
		  'fv-3mo' => 'Free V',
		  'fv-3mo or pi3' => 'Free V',
		  'fv-6mo & of200' => 'Free V & OF',
		  'fv-9' => 'Free V',
		  'fv-9mo' => 'Free V',
		  'fv/of300' => 'Free V & OF',
		  'fv12' => 'Free V',
		  'fv12mo' => 'Free V',
		  'fv3m' => 'Free V',
		  'gak12' => 'gak',
		  'gak8' => 'gak',
		  'gakobj' => 'gak',
		  'gakobj.' => 'gak',
		  'gakobj12' => 'gak',
		  'gaps' => 'Gaps',
		  'gaps replication' => 'Gaps',
		  'gapsreplication' => 'Gaps',
		  'gauge' => 'Gauge',
		  'gomez' => 'Gomez',
		  'gomez 12 mo' => 'Gomez',
		  'gomez ii' => 'Gomez ii',
		  'gomezii' => 'Gomez ii',
		  'grant pilot' => 'Grant Pilot',
		  'grant pilot studies' => 'Grant Pilot',
		  'grant pilot- cliff' => 'Grant Pilot',
		  'grant pilot- crawling aperture' => 'Grant Pilot',
		  'grant pilot-aperture' => 'Grant Pilot',
		  'grant pilot-walking aperture' => 'Grant Pilot',
		  'grant pilot: walking aperture' => 'Grant Pilot',
		  'grant piloting' => 'Grant Pilot',
		  'grant pilots' => 'Grant Pilot',
		  'grant studies' => 'Grant Pilot',
		  'grant studies- cliff' => 'Grant Pilot',
		  'grant studies- ladders' => 'Grant Pilot',
		  'grant studies-aperture' => 'Grant Pilot',
		  'head turn' => 'Head Turn',
		  'head turn pilot' => 'Head Turn',
		  'head turn/lps' => 'Head Turn &LPS',
		  'head turn/visual' => 'Head Turn',
		  'headt' => 'Head Turn',
		  'headtuirn' => 'Head Turn',
		  'headturn' => 'Head Turn',
		  'headturn/axaby' => 'Head Turn',
		  'headturn/visual' => 'Head Turn',
		  'hindi' => 'Hindi',
		  'hindi head turn' => 'Hindi Head Turn',
		  'hindi?' => 'Hindi',
		  'hugh\'s practice' => 'Hugh\'s Practice',
		  'hv' => 'HV',
		  'id56' => 'ID56',
		  'identity pilot' => 'ID pilot',
		  'identity pilot, visual marcus' => 'ID pilot',
		  'identity pilot, visual marcus-11 mo' => 'ID pilot',
		  'identity-pilot' => 'ID pilot',
		  'identity-pilot, visual marcus' => 'ID pilot',
		  'identity-pilot, visual marcus-11' => 'ID pilot',
		  'ids' => 'IDS',
		  'ids6' => 'IDS6',
		  'il' => 'IL',
		  'il & s#' => 'IL & SFF',
		  'il &s#' => 'IL & SFF',
		  'il&s' => 'IL & SFF',
		  'il&s#' => 'IL & SFF',
		  'il, s#' => 'IL & SFF',
		  'il,s#' => 'IL & SFF',
		  'infant laterality' => 'DR CrawlSit1',
		  'infant reaching' => 'Reach Ap',
		  'info/timbre' => 'Timbre',
		  'irbot' => 'iRobot',
		  'irobo' => 'iRobot',
		  'irobor' => 'iRobot',
		  'irobot' => 'iRobot',
		  'irobot pilot' => 'iRobot',
		  'irobot practice' => 'iRobot',
		  'irobot`' => 'iRobot',
		  'irobotm' => 'iRobot',
		  'iroboy' => 'iRobot',
		  'irobt' => 'iRobot',
		  'j robot' => 'jRobot',
		  'j-robot' => 'jRobot',
		  'jrbot' => 'jRobot',
		  'jrobo' => 'jRobot',
		  'jrobot' => 'jRobot',
		  'jrobot
		jrobot' => 'jRobot',
		  'jroobot' => 'jRobot',
		  'kc' => 'KC',
		  'kc & s#' => 'KC & SFF',
		  'kc ii' => 'KC ii',
		  'kc, s#' => 'KC & SFF',
		  'kc/s#' => 'KC & SFF',
		  'kcii' => 'KC ii',
		  'kerr\'s study' => 'Kerri\'s Study',
		  'kerri\'s study' => 'Kerri\'s Study',
		  'kerri;s study' => 'Kerri\'s Study',
		  'kind/color' => 'Kind/Color',
		  'l45' => 'I45',
		  'l60' => 'I60',
		  'lat/crawl' => 'DR CrawlSit1',
		  'latarality' => 'DR CrawlSit1',
		  'lateral reach-crawl' => 'DR CrawlSit1',
		  'lateralitly/crawl' => 'DR CrawlSit1',
		  'laterality' => 'DR CrawlSit1',
		  'laterality & crawling' => 'DR CrawlSit1',
		  'laterality/crawl' => 'DR CrawlSit1',
		  'laterality/crawl prog' => 'DR CrawlSit1',
		  'laterality/crawling' => 'DR CrawlSit1',
		  'loc  ap w' => 'Loc Ap',
		  'loc ap' => 'Loc Ap',
		  'loc ap big kids' => 'Loc Ap',
		  'loc ap c' => 'Loc Ap',
		  'loc ap infant' => 'Loc Ap',
		  'loc ap w' => 'Loc Ap',
		  'loc ap walk' => 'Loc Ap',
		  'loc aperture' => 'Loc Ap',
		  'loc aps' => 'Loc Ap',
		  'loc aps college' => 'Loc Ap',
		  'loc aps infant' => 'Loc Ap',
		  'loc aps infant wall' => 'Loc Ap',
		  'loc apt w' => 'Loc Ap',
		  'locao' => 'Loc Ap',
		  'locap' => 'Loc Ap',
		  'locaps' => 'Loc Ap',
		  'loco aps' => 'Loc Ap',
		  'locomotor ap' => 'Loc Ap',
		  'locomotor ap/mom advice 12' => 'Loc Ap',
		  'locomotor aperture' => 'Loc Ap',
		  'locomotor aperture c' => 'Loc Ap',
		  'locomotor apertures' => 'Loc Ap',
		  'locomotor apertures (walking)' => 'Loc Ap',
		  'locomotor apertures c' => 'Loc Ap',
		  'locomotor apertures crawl' => 'Loc Ap',
		  'locomotor apertures w' => 'Loc Ap',
		  'locomotor apertures walk' => 'Loc Ap',
		  'locomotor apertures walkin' => 'Loc Ap',
		  'locomotor apertures/mom advice 12' => 'Loc Ap',
		  'locomotor apetures' => 'Loc Ap',
		  'locomotor w/c' => 'Loc Ap',
		  'lpa' => 'LPS',
		  'lps' => 'LPS',
		  'lps
		>
		> nyu infant language lab
		> 4 washington place
		> new york, new york
		> (212) 998-3870' => 'LPS',
		  'lps pilot' => 'LPS',
		  'lps-45 sec' => 'LPS',
		  'lps-jackie' => 'LPS',
		  'lps-jared' => 'LPS',
		  'lps/head turn' => 'LPS',
		  'lps?' => 'LPS',
		  'lsa' => 'LPS',
		  'lsp' => 'LSP',
		  'lst' => 'LST',
		  'lsx' => 'LAX',
		  'lto' => 'LTO',
		  'ma' => '12 MA',
		  'ma 12' => '12 MA',
		  'ma 12 c' => '12 MA',
		  'ma 12 crawl' => '12 MA',
		  'ma 12 crawl / apertures' => '12 MA',
		  'ma 12 crawling' => '12 MA',
		  'ma 12 w/aperture crawl' => '12 MA',
		  'ma 12 walking' => '12 MA',
		  'ma 12c' => '12 MA',
		  'ma 12crawl' => '12 MA',
		  'ma 12w or crawling ap' => '12 MA',
		  'ma 18 teflon' => 'MA Teflon',
		  'ma 19 teflon' => 'MA Teflon',
		  'ma cliff' => 'Cliff',
		  'ma crawl' => '12 MA',
		  'ma flatshoe' => 'MA Flatshoe',
		  'ma staircase' => 'MA Staircase',
		  'ma tef' => 'MA Teflon',
		  'ma tefllon' => 'MA Teflon',
		  'ma teflon' => 'MA Teflon',
		  'ma teflon 1' => 'MA Teflon',
		  'ma teflon 19' => 'MA Teflon',
		  'ma teflon 19 tef' => 'MA Teflon',
		  'ma teflon staircase' => 'MA Teflon',
		  'ma teflon19' => 'MA Teflon',
		  'ma teflorn' => 'MA Teflon',
		  'ma telfon' => 'MA Teflon',
		  'ma12 crawling' => '12 MA',
		  'ma12 or locomotor aperture' => '13 MA',
		  'ma12 walk/crawling aperture' => '14 MA',
		  'ma12 walking' => '15 MA',
		  'ma12c' => '16 MA',
		  'ma12c or crawling ap' => '17 MA',
		  'macliff' => 'Cliff',
		  'marcus hab' => 'Marcus Habit',
		  'marcus habi' => 'Marcus Habit',
		  'marcus habit' => 'Marcus Habit',
		  'marcus habit/pi habit' => 'Marcus Habit',
		  'marcus habit?' => 'Marcus Habit',
		  'marcus habt' => 'Marcus Habit',
		  'marcus hait' => 'Marcus Habit',
		  'marcus hbit' => 'Marcus Habit',
		  'marcus pilot' => 'Marcus Pilot',
		  'marcus pilot/pi habit' => 'Marcus Pilot',
		  'marus habit' => 'Marcus Habit',
		  'mateflon' => 'MA Teflon',
		  'mc5' => 'MC5',
		  'mc5 & fp' => 'MC5 & FP',
		  'mc5 & fp2' => 'MC5 & FP',
		  'mc5 &fp' => 'MC5 & FP',
		  'mc5 &fp2' => 'MC5 & FP',
		  'mc5& fp' => 'MC5 & FP',
		  'mc5& fp2' => 'MC5 & FP',
		  'mc5-johnson' => 'MC5 & FP',
		  'measure' => 'Measurement',
		  'measurement' => 'Measurement',
		  'measurements' => 'Measurement',
		  'measuremments' => 'Measurement',
		  'ment rot' => 'Mental Rot',
		  'mental rot' => 'Mental Rot',
		  'mental rot-3mo' => 'Mental Rot',
		  'mental rot.' => 'Mental Rot',
		  'mental rot/ of & ms2' => 'Mental Rot',
		  'mental rot/info' => 'Mental Rot',
		  'mental rot/object form' => 'Mental Rot & OF',
		  'mental rot/of & ms2' => 'Mental Rot/OF & MS2',
		  'mental rot/of &ms2' => 'Mental Rot/OF & MS2',
		  'mental rot/of 300' => 'Mental Rot & OF 300',
		  'mental rot/of200' => 'Mental Rot & OF200',
		  'mental rot/of200/update' => 'Mental Rot & OF200',
		  'mental rot/of300' => 'Mental Rot & OF300',
		  'mental rot/update' => 'Mental Rot',
		  'mental rot/update info' => 'Mental Rot',
		  'mental rotation' => 'Mental Rot',
		  'mental rotation/of' => 'Mental Rot & OF',
		  'mental rotation/of & ofm' => 'Mental Rot/OF & OFM',
		  'mental rotation/of &ofm' => 'Mental Rot/OF & OFM',
		  'mental rotation/of 300' => 'Mental Rot & OF300',
		  'mental rotation/of200' => 'Mental Rot & OF200',
		  'mental rotation/of200.' => 'Mental Rot & OF200',
		  'mental rotation/of300' => 'Mental Rot & OF300',
		  'mental rotation/update' => 'Mentalk Rot',
		  'mental rotation/update info' => 'Mentalk Rot',
		  'mental rotaton' => 'Mentalk Rot',
		  'mentarot' => 'Mentalk Rot',
		  'mh' => 'MH',
		  'mh & pi habit' => 'MH',
		  'mh 5 mo' => 'MH',
		  'mh, fussed out of pi habit' => 'MH',
		  'mh/pi habit' => 'MH',
		  'mh11' => 'MH',
		  'mh5' => 'MH5',
		  'mh5 & pi huizer cube' => 'MH5 & pi huizer cube',
		  'mh5/of200' => 'MH5 & OF200',
		  'mi5' => 'MI5',
		  'mini dogs' => 'Mini dogs',
		  'minidogs' => 'Mini dogs',
		  'ml' => 'MI',
		  'ml & s#' => 'MI & SFF',
		  'ml & sn' => 'MI & SN',
		  'ml &s#' => 'MI & SFF',
		  'ml&s#' => 'MI & SFF',
		  'ml, s#' => 'MI & SFF',
		  'ml, snack' => 'MI',
		  'ml,s#' => 'MI & SFF',
		  'mo advice 12w' => '12 MA',
		  'modr' => '12 MA',
		  'mom' => 'MA Staircase',
		  'mom advice 12 walk' => '12 MA',
		  'mom  advice 12' => '12 MA',
		  'mom  advice 12 crawl' => '12 MA',
		  'mom  advice 18' => '18 MA',
		  'mom aadvice 18 walk' => '18 MA',
		  'mom adiv' => 'MA Staircase',
		  'mom adivce 12 crawl' => '12 MA',
		  'mom adivce 18 walk' => '18 MA',
		  'mom adive 18 walk' => '18 MA',
		  'mom advcie 12 w' => '12 MA',
		  'mom advcie 18' => '18 MA',
		  'mom advice  18' => '18 MA',
		  'mom advice (13)' => '13 MA',
		  'mom advice 13'	=> '13 MA',
		  'mom advice 13c'	=> '13 MA',
		  'mom advice 13crawl'	=> '13 MA',
		  'mom advice 12c'	=> '12 MA',
		  'mom advice 12w'	=> '12 MA',
		  'mom advice (crawler)' => '18 MA',
		  'mom advice - 18' => '18 MA',
		  'mom advice -18w' => '18 MA',
		  'mom advice 12' => '12 MA',
		  'mom advice12' => '12 MA',
		  'mom advice 12 c' => '12 MA',
		  'mom advice 12 crawl' => '12 MA',
		  'mom advice 12 crawl/walk' => '12 MA',
		  'mom advice 12 crawl/wlk' => '12 MA',
		  'mom advice 12 crawler' => '12 MA',
		  'mom advice 12/locomotor ap' => '12 MA',
		  'mom advice 12/wobbly cruise' => '12 MA',
		  'mom advice 12c or locomotor apertures' => '12 MA',
		  'mom advice 12m' => '12 MA',
		  'mom advice 13 c' => '13 MA',
		  'mom advice 13 crawl' => '13 MA',
		  'mom advice 13 w' => '13 MA',
		  'mom advice 13 walk' => '13 MA',
		  'mom advice 13w' => '13 MA',
		  'mom advice 18  platform' => 'MA Platform',
		  'mom advice 18 gonio' => 'MA Gonio',
		  'mom advice 18 goniometer' => 'MA Gonio',
		  'mom advice 18 goniometer/mom comm 18' => 'MA Gonio',
		  'mom advice 18 goniomter' => 'MA Gonio',
		  'mom advice 18 plat' => 'MA Platform',
		  'mom advice 18 platform' => 'MA Platform',
		  'mom advice 18 platform/goni' => 'MA Platform',
		  'mom advice 18 platform/goniometer' => 'MA Platform',
		  'mom advice 18 tef' => 'MA Teflon',
		  'mom advice 18 tef/goni' => 'MA Teflon',
		  'mom advice 18 teflon' => 'MA Teflon',
		  'mom advice teflon' => 'MA Teflon',
		  'mom advice 18 w' => '18 MA',
		  'mom advice 18w' => '18 MA',
		  'mom advice 18 wak' => '18 MA',
		  'mom advice 18 walk' => '18 MA',
		  'mom advice 18 walkk' => '18 MA',
		  'mom advice 18/mom comm 18' => '18 MA',
		  'mom advice 18/teflon' => 'MA Teflon',
		  'mom advice 18walk' => '18 MA',
		  'mom advice 18' => '18 MA',
		  'mom advice 19' => '18 MA',
		  'mom advice 19 tef' => 'MA Teflon',
		  'mom advice 19 teflon' => 'MA Teflon',
		  'mom advice cliff' => 'Cliff',
		  'mom advice crawl' => '12 MA',
		  'mom advice crawl 12' => '12 MA',
		  'mom advice goni' => 'MA Gonio',
		  'mom advice goniometer' => 'MA Gonio',
		  'mom advice or locomotor ap' => 'Mom Advice',
		  'mom advice plat' => 'MA Platform',
		  'mom advice plat/goni' => 'MA Platform',
		  'mom advice platform/goni' => 'MA Platform',
		  'mom advice platform/goniometer' => 'MA Platform',
		  'mom advice tef' => 'MA Teflon',
		  'mom advice teflon 19' => 'MA Teflon',
		  'mom advice teflon/apertures' => 'MA Teflon',
		  'mom advice teflone' => 'MA Teflon',
		  'mom advice w18' => '18 MA',
		  'mom advice walk' => '18 MA',
		  'mom advice-18' => '18 MA',
		  'mom advice-18w' => '18 MA',
		  'mom advice/comm' => 'Mom Advice',
		  'mom advice/grant pilot' => 'Mom Advice',
		  'mom advice/locomotor ap' => 'Mom Advice',
		  'mom advice12 crawl' => '12 MA',
		  'mom advice12 walk' => '12 MA',
		  'mom advice18 teflon' => 'MA Teflon',
		  'mom advice18 walk' => '18 MA',
		  'mom advise' => 'Mom Advice',
		  'mom advoce teflon' => 'MA Teflon',
		  'mom avice' => 'Mom Advice',
		  'mom comm 20' => 'Mom Comm',
		  'mom comm20' => 'Mom Comm',
		  'mom comm' => 'Mom Comm',
		  'mom comm 36' => 'Mom Comm',
		  'mom caomm 36' => 'Mom Comm',
		  'mom cliff' => 'Cliff',
		  'mom com' => 'Mom Comm',
		  'mom com 18' => 'Mom Comm',
		  'mom com 36' => 'Mom Comm',
		  'mom comm   18' => 'Mom Comm',
		  'mom comm & mom advice' => 'Mom Comm',
		  'mom comm -20' => 'Mom Comm',
		  'mom comm 18 (duplicate entry in database)' => 'Mom Comm',
		  'mom comm 18/info' => 'Mom Comm',
		  'mom comm 18/mom advice teflon' => 'Mom Comm',
		  'mom comm 18m' => 'Mom Comm',
		  'mom comm 36/grant pilot' => 'Mom Comm',
		  'mom comm 38' => 'Mom Comm',
		  'mom comm-20' => 'Mom Comm',
		  'mom comm.' => 'Mom Comm',
		  'mom communication' => 'Mom Comm',
		  'mom communication 18' => 'Mom Comm',
		  'momadvice 12 walk' => '12 MA',
		  'momadvice 18 teflon' => 'MA Teflon',
		  'momadvice 18 walk' => '18 MA',
		  'momadvice teflon 18' => 'MA Teflon',
		  'momadvice/cliff' => 'Cliff',
		  'momadvice12' => '12 MA',
		  'momcliff' => 'Cliff',
		  'momcomm 36' => 'Mom Comm',
		  'momcomm36' => 'Mom Comm',
		  'momm comm' => 'Mom Comm',
		  'momm comm 18' => 'Mom Comm',
	      'mom comm 18'	=> 'Mom Comm',
	      'mom comm18'	=> 'Mom Comm',
	      'mom comm36'	=> 'Mom Comm',
	      'momcomm'	=> 'Mom Comm',
		  'mon advice teflon' => 'MA Teflon',
		  'motion sensitivity' => 'Motion Sensitivity',
		  'motion sensitivtiy' => 'Motion Sensitivity',
		  'motion sensitivty' => 'Motion Sensitivity',
		  'motion sensitvity' => 'Motion Sensitivity',
		  'mr' => 'MR',
		  'mr 3' => 'MR',
		  'mr, em' => 'MR & OF300',
		  'mr, of300' => 'MR & OF300',
		  'mr-3' => 'MR',
		  'mr-3mo' => 'MR',
		  'mr/of300' => 'MR & OF300',
		  'mr3' => 'MR',
		  'mr5' => 'MR',
		  'mr5 & pi insides' => 'MR5 & pi Insides',
		  'ms' => 'MS',
		  'ms 2' => 'MS',
		  'ms 6 mo' => 'MS',
		  'ms/sp' => 'MS/SP',
		  'ms/sp/unity' => 'MS/SP',
		  'ms2' => 'MS & OF',
		  'ms2 & of' => 'MS & OF',
		  'ms2 & of100l' => 'MS & OF',
		  'ms2 & of200' => 'MS & OF',
		  'ms6 mo' => 'MS',
		  'ms6mo' => 'MS',
		  'msii' => 'MS',
		  'music' => 'Music & Memory',
		  'music and mem' => 'Music & Memory',
		  'music and memory' => 'Music & Memory',
		  'music and verbal memory' => 'Music & Memory',
		  'music memory' => 'Music & Memory',
		  'music/mem + pp' => 'Music & Memory',
		  'music/memory' => 'Music & Memory',
		  'na&wa' => 'NAD & WA',
		  'nad' => 'NAD & WA',
		  'nad  & wa' => 'NAD & WA',
		  'nad &  wa' => 'NAD & WA',
		  'nad & wa' => 'NAD & WA',
		  'nad & wa (last day: 1/27)' => 'NAD & WA',
		  'nad & wa*' => 'NAD & WA',
		  'nad & wa, update' => 'NAD & WA',
		  'nad & wa-14' => 'NAD & WA',
		  'nad & wa-s 14' => 'NAD & WA',
		  'nad & wa/ 12b' => 'NAD & WA',
		  'nad & wa/ stats' => 'NAD & WA',
		  'nad & wa/ stats rules' => 'NAD & WA',
		  'nad & wa/12b' => 'NAD & WA',
		  'nad & wa/stats' => 'NAD & WA',
		  'nad & wa/stats rules' => 'NAD & WA',
		  'nad &wa' => 'NAD & WA',
		  'nad + wa' => 'NAD & WA',
		  'nad - wa' => 'NAD & WA',
		  'nad and wa' => 'NAD & WA',
		  'nad control' => 'NAD',
		  'nad or et' => 'NAD',
		  'nad wa' => 'NAD & WA',
		  'nad& wa' => 'NAD & WA',
		  'nad&wa' => 'NAD & WA',
		  'nad&wa  or  signs' => 'NAD & WA',
		  'nad&wa - 14' => 'NAD & WA',
		  'nad&wa -14' => 'NAD & WA',
		  'nad&wa 14' => 'NAD & WA',
		  'nad&wa or signs' => 'NAD & WA',
		  'nad&wa-14' => 'NAD & WA',
		  'nad&wa/signs' => 'NAD & WA',
		  'nad+wa' => 'NAD & WA',
		  'nad/wa' => 'NAD & WA',
		  'nad/wa and stats' => 'NAD & WA',
		  'nad/word ass' => 'NAD & WA',
		  'nadcontrol & wasp' => 'NAD & WA',
		  'nas&wa-14' => 'NAD & WA',
		  'national geo and vt 12 w' => 'Nat Geo',
		  'national geographic' => 'Nat Geo',
		  'national geography' => 'Nat Geo',
		  'nc' => 'NC',
		  'nc &s#' => 'NC & S#',
		  'neg prim' => 'NP',
		  'neg prim; vis sen' => 'NP',
		  'neg prim; vis sensitivity' => 'NP',
		  'neg prim; visu sen' => 'NP',
		  'neg prim; visual sen' => 'NP',
		  'neg prim; visual sensitivity' => 'NP',
		  'neg prime' => 'NP',
		  'neg prime, rod&box' => 'NP',
		  'neg prime-3 mo' => 'NP',
		  'negative priming' => 'NP',
		  'np' => 'NP',
		  'np  3 mo' => 'NP',
		  'np  3 mo & pi habit' => 'NP',
		  'np  6 mo' => 'NP',
		  'np  6mo' => 'NP',
		  'np  9 mo' => 'NP',
		  'np 3 m' => 'NP',
		  'np 3 mo' => 'NP',
		  'np 3m0' => 'NP',
		  'np 3mo' => 'NP',
		  'np 3mo (sched beg. 1/13/05)' => 'NP',
		  'np 6 mo' => 'NP',
		  'np 6mo' => 'NP',
		  'np 9' => 'NP',
		  'np 9 m0' => 'NP',
		  'np 9 mo' => 'NP',
		  'np 9 mo, pi pref' => 'NP',
		  'np 9 mo/pi pref' => 'NP',
		  'np 9 moj' => 'NP',
		  'np 9mo' => 'NP',
		  'np 9mp' => 'NP',
		  'np- 6mo' => 'NP',
		  'np-3mo' => 'NP',
		  'np-6mo' => 'NP',
		  'np-6mp' => 'NP',
		  'np-9 mo' => 'NP',
		  'np-9mo' => 'NP',
		  'np-9mo, bounces-stream, vis sensitivity tobii pilot' => 'NP',
		  'np-9mo, vis sens pilot' => 'NP',
		  'np/modr' => 'NP',
		  'np3' => 'NP',
		  'np3 - 1/13/05--- pnas4 1/14/05' => 'NP',
		  'np3 mo' => 'NP',
		  'np3--next mon or wed' => 'NP',
		  'np3mo' => 'NP',
		  'np3mo?' => 'NP',
		  'np4mo' => 'NP',
		  'np6' => 'NP',
		  'np6 mo' => 'NP',
		  'np6mo' => 'NP',
		  'np6mos' => 'NP',
		  'np6mp' => 'NP',
		  'np6o' => 'NP',
		  'np9 mo' => 'NP',
		  'np9m0' => 'NP',
		  'np9mo' => 'NP',
		  'np; 6 mo' => 'NP',
		  'np; visual sen' => 'NP',
		  'nrobot' => 'nRobot',
		  'nrobot-no model' => 'nRobot',
		  'nrobot-practice' => 'nRobot',
		  'nrobot`' => 'nRobot',
		  'nronot' => 'nRobot',
		  'object form' => 'Object Form',
		  'object form (beg. 11/17)' => 'Object Form',
		  'object form/pi picture grasp' => 'Object Form',
		  'object form/update info' => 'Object Form',
		  'object forn' => 'Object Form',
		  'object manipulation' => 'Object Manipulation',
		  'object manipulation & of' => 'Object Manipulation',
		  'object manipultation' => 'Object Manipulation',
		  'objobj' => 'objobj',
		  'objobj 8' => 'objobj',
		  'objobj8' => 'objobj',
		  'objojb8' => 'objobj',
		  'obox' => 'O',
		  'of' => 'OF',
		  'of & fv' => 'OF',
		  'of & hugh r\'s sign test' => 'OF',
		  'of & m2' => 'OF & MS',
		  'of & ms 2' => 'OF & MS',
		  'of & ms2' => 'OF & MS',
		  'of & ms2/update info' => 'OF & MS',
		  'of & of m' => 'OF & OFM',
		  'of & ofm' => 'OF & OFM',
		  'of & pi' => 'OF & PI',
		  'of & pi (02/22--last day)' => 'OF & PI',
		  'of & pi (beg 05/12/06)' => 'OF & PI',
		  'of & pi (beg 05/30)' => 'OF & PI',
		  'of & pi (beg 06/20)' => 'OF & PI',
		  'of & pi (beg 07/12)' => 'OF & PI',
		  'of & pi (beg 11/14).' => 'OF & PI',
		  'of & pi (beg 12/6)' => 'OF & PI',
		  'of & pi (beg. 01.17)' => 'OF & PI',
		  'of & pi (beg. 02/24)' => 'OF & PI',
		  'of & pi (beg. 04/03)' => 'OF & PI',
		  'of & pi (beg. 05/01)' => 'OF & PI',
		  'of & pi (beg. 12/19)' => 'OF & PI',
		  'of & pi (beg. 12/6)' => 'OF & PI',
		  'of & pi (beg. fri 02/17)' => 'OF & PI',
		  'of & pi (beg.01/17)' => 'OF & PI',
		  'of & pi (beg.02/24)' => 'OF & PI',
		  'of & pi (beg.03/08)/update' => 'OF & PI',
		  'of & pi (beg.12/2)' => 'OF & PI',
		  'of & pi (last day of range 02/22)' => 'OF & PI',
		  'of & pi (tobi)' => 'OF & PI',
		  'of & pi (tobii)' => 'OF & PI',
		  'of & pi (update info)' => 'OF & PI',
		  'of & pi huizer cube' => 'OF & PI',
		  'of & pi insides' => 'OF & PI',
		  'of & pi picture' => 'OF & PI',
		  'of & pi picture graps' => 'OF & PI',
		  'of & pi picture grasp' => 'OF & PI',
		  'of & pi picture grasping' => 'OF & PI',
		  'of & pi picutre grasp' => 'OF & PI',
		  'of & pi picuture grasp' => 'OF & PI',
		  'of & pi tobii' => 'OF & PI',
		  'of & pi tobii & huizer cube' => 'OF & PI',
		  'of & pi tobii 2' => 'OF & PI',
		  'of & pi(tobii)' => 'OF & PI',
		  'of & pi-huizer cube' => 'OF & PI',
		  'of & pi/mc5 & fp2' => 'OF & PI',
		  'of & pi/mental rot' => 'OF & PI',
		  'of & pi/mental rotation' => 'OF & PI',
		  'of & pi/mh5' => 'OF & PI',
		  'of & pi/update info' => 'OF & PI',
		  'of & pi2(tobii)' => 'OF & PI',
		  'of & pi4m' => 'OF & PI',
		  'of & pnas' => 'OF & PNES',
		  'of & pnas4' => 'OF & PNES',
		  'of &pi' => 'OF & PI',
		  'of / pi' => 'OF & PI',
		  'of 100' => 'OF 100',
		  'of 100l & piinsides' => 'OF',
		  'of 200' => 'OF 200',
		  'of 200 & ms2' => 'OF 200',
		  'of 300' => 'OF 300',
		  'of and fv' => 'OF & FV',
		  'of and pi' => 'OF & PI',
		  'of or nad/wa' => 'OF',
		  'of pi' => 'OF',
		  'of& ms2' => 'OF & MS2',
		  'of& pi' => 'OF & PI',
		  'of&ms2' => 'OF & MS2',
		  'of&pi/mental rotation' => 'OF',
		  'of, em, mr' => 'OF',
		  'of, emotion match' => 'OF',
		  'of--manipulation' => 'OF',
		  'of--object manipulation' => 'OF',
		  'of-7mo' => 'OF',
		  'of-manipulation' => 'OF',
		  'of-object manipulation' => 'OF',
		  'of/em' => 'OF',
		  'of/pi picture grasp' => 'OF',
		  'of/update info' => 'OF',
		  'of00' => 'OF 100',
		  'of10' => 'OF 100',
		  'of100' => 'OF 100',
		  'of100 & pi' => 'OF 100',
		  'of100 & pi (tobi)' => 'OF 100',
		  'of100 & pi (tobii)' => 'OF 100',
		  'of100 & pi-huizer cube' => 'OF 100',
		  'of100/ms2' => 'OF 100',
		  'of100l' => 'OF 100',
		  'of100l & ms2' => 'OF 100',
		  'of100l & of100w' => 'OF 100',
		  'of100l & pi' => 'OF 100',
		  'of100l & pitobii' => 'OF 100',
		  'of100l &ms2' => 'OF 100',
		  'of100l/update info' => 'OF 100',
		  'of100w & ms' => 'OF 100',
		  'of200' => 'OF 200',
		  'of200 & fv' => 'OF 200 & FV',
		  'of200 & motion sensitivity' => 'OF 200 & MS',
		  'of200 & ms2' => 'OF 200 & MS',
		  'of200 & msii' => 'OF 200 & MS',
		  'of200 & pi' => 'OF 200 & PI',
		  'of200 & pi (tobi)' => 'OF 200 & PI',
		  'of200 & pi 2 (tobii)' => 'OF 200 & PI',
		  'of200 & pi huizer' => 'OF 200 & PI',
		  'of200 & pi(tobii)' => 'OF 200 & PI',
		  'of200 & pi2 tobii' => 'OF 200 & PI',
		  'of200 &ms2' => 'OF 200 & MS',
		  'of200 (beg monday 02/13' => 'OF 200',
		  'of200 (beg.0/18)' => 'OF 200',
		  'of200 (beg.01/18)' => 'OF 200',
		  'of200 (of rnage until 03/10)' => 'OF 200',
		  'of200/ms' => 'OF 200 & MS',
		  'of200/saf rep' => 'OF 200',
		  'of200/update info' => 'OF 200',
		  'of300' => 'OF 300',
		  'of300 & mental rot' => 'OF 300',
		  'of300 (pilot)' => 'OF 300',
		  'of300 (piot)' => 'OF 300',
		  'of300 pilot' => 'OF 300',
		  'of300(pilot)' => 'OF 300',
		  'of300/em' => 'OF 300',
		  'of9' => 'OF',
		  'of9mo' => 'OF',
		  'ofm & pi4m' => 'OF & PI',
		  'ofm/pi4m' => 'OF & PI',
		  'ofma/pi4m' => 'OF & PI',
		  'ord' => 'Ordinal',
		  'ord 1' => 'Ordinal',
		  'ord 1&2' => 'Ordinal',
		  'ord 2' => 'Ordinal',
		  'ordinal' => 'Ordinal',
		  'ordinal comp' => 'Ordinal',
		  'ordinal1' => 'Ordinal',
		  'ordinal2' => 'Ordinal',
		  'ot' => 'OT',
		  'p/i' => 'P/I Habit',
		  'p/i habit' => 'P/I Habit',
		  'p/i habituation' => 'P/I Habit',
		  'p/i or mc5' => 'p/l',
		  'p/l' => 'p/l',
		  'p13' => 'p13',
		  'patch 37' => 'Patch',
		  'paatch 39' => 'Patch',
		  'parent\'s beliefs' => 'Parents\' Beliefs',
		  'parents beliefs' => 'Parents\' Beliefs',
		  'parents\' belief' => 'Parents\' Beliefs',
		  'patch  39' => 'Patch',
		  'patch long' => 'Patch Long',
		  'patch  long' => 'Patch Long',
		  'patch 15' => 'Patch',
		  'patch 15 control' => 'Patch',
		  'patch 33
		patch 33' => 'Patch',
		  'patch 33' => 'Patch',
		  'patch 39'	=> 'Patch',
		  'patch'	=> 'Patch',
		  'patch friction' => 'Patch',
		  'patch long (2nd visit)' => 'Patch Long',
		  'patch long (sess 1)' => 'Patch Long',
		  'patch long (sess 2)' => 'Patch Long',
		  'patch long (sess 3)' => 'Patch Long',
		  'patch long (sess 4)' => 'Patch Long',
		  'patch long (sess 5)' => 'Patch Long',
		  'patch long (sess 6)' => 'Patch Long',
		  'patch long (sess 7)' => 'Patch Long',
		  'patch long (session 1)' => 'Patch Long',
		  'patch long (session 2)' => 'Patch Long',
		  'patch long (session 3)' => 'Patch Long',
		  'patch long (session 4)' => 'Patch Long',
		  'patch long (session 5)' => 'Patch Long',
		  'patch long (session 6)' => 'Patch Long',
		  'patch long (session 7 resch)' => 'Patch Long',
		  'patch long (session 7)' => 'Patch Long',
		  'patch long (session2)' => 'Patch Long',
		  'patch long (sesstion 4)' => 'Patch Long',
		  'patch long pilot' => 'Patch Long',
		  'patch long session 1' => 'Patch Long',
		  'patch long session 2' => 'Patch Long',
		  'patch long session 3' => 'Patch Long',
		  'patch long session 4' => 'Patch Long',
		  'patch long session 5' => 'Patch Long',
		  'patch long session 6' => 'Patch Long',
		  'patch long session 7' => 'Patch Long',
		  'patch long(sess 1)' => 'Patch Long',
		  'patch long(sess 2)' => 'Patch Long',
		  'patch long`' => 'Patch Long',
		  'patch rigidity' => 'Patch',
		  'patch-15' => 'Patch',
		  'patch15' => 'Patch',
		  'patch33' => 'Patch',
		  'patch39' => 'Patch',
		  'patchlong' => 'Patch',
		  'patchr' => 'Patch',
		  'path 39' => 'Patch',
		  'pedestal' => 'Pedestal',
		  'pedesatl' => 'Pedestal',
		  'pedestal 13' => 'Pedestal',
		  'pedestal 24' => 'Pedestal',
		  'pedestals' => 'Pedestal',
		  'pedestals 24' => 'Pedestal',
		  'pedstal' => 'Pedestal',
		  'perceing people' => 'Perceiving People',
		  'perceiving' => 'Perceiving People',
		  'perceiving p' => 'Perceiving People',
		  'perceiving peope' => 'Perceiving People',
		  'perceiving peopl' => 'Perceiving People',
		  'perceiving people' => 'Perceiving People',
		  'perceiving people (casey\'s)' => 'Perceiving People',
		  'perceiving people, music/memory' => 'Perceiving People',
		  'perceiving people/m&mem' => 'Perceiving People',
		  'perceiving people/mm' => 'Perceiving People',
		  'perceiving people/mus&mem' => 'Perceiving People',
		  'perceiving people/music&mem' => 'Perceiving People',
		  'perceiving people/musmem' => 'Perceiving People',
		  'perceiving pople' => 'Perceiving People',
		  'perception' => 'Perceiving People',
		  'percieving people' => 'Perceiving People',
		  'pereceiving people' => 'Perceiving People',
		  'pers. detours' => 'Persev',
		  'pers.fl vs detour crawling' => 'Persev',
		  'perserv' => 'Persev',
		  'perserv 13' => 'Persev',
		  'persev' => 'Persev',
		  'persev - crawl' => 'Persev',
		  'persev crawl' => 'Persev',
		  'persev crawl or mom advice' => 'Persev',
		  'persev crawl/walk' => 'Persev',
		  'persev detour' => 'Persev',
		  'persev or mom advice' => 'Persev',
		  'persev walk' => 'Persev',
		  'persev walking' => 'Persev',
		  'persev-bridge' => 'Persev',
		  'persev-bridges' => 'Persev',
		  'persev-crawl' => 'Persev',
		  'persev-walk' => 'Persev',
		  'persev.' => 'Persev',
		  'persev/walking' => 'Persev',
		  'persev:walking' => 'Persev',
		  'persevation' => 'Persev',
		  'persevcrawl' => 'Persev',
		  'perseve crawler' => 'Persev',
		  'perseveration' => 'Persev',
		  'pi' => 'PI',
		  'pi  (tobii)' => 'PI',
		  'pi & of' => 'PI & OF',
		  'pi & of200' => 'PI & OF 200',
		  'pi (of range end of october)' => 'PI',
		  'pi (out of range 03/14)' => 'PI',
		  'pi (tobi)' => 'PI',
		  'pi (tobi) & of200' => 'PI',
		  'pi (tobii)' => 'PI',
		  'pi -eye tracking' => 'PI',
		  'pi 2m' => 'PI',
		  'pi 2m (tobbi) v2' => 'PI',
		  'pi 2m (tobi)' => 'PI',
		  'pi 2m (tobii) v1' => 'PI',
		  'pi 2m (tonii-v2)' => 'PI',
		  'pi 2m rpt' => 'PI',
		  'pi 2m(tobi) v3' => 'PI',
		  'pi 2m/update info' => 'PI',
		  'pi 2mo' => 'PI',
		  'pi 2mo/ freev 3mo' => 'PI',
		  'pi 2mos tobii' => 'PI',
		  'pi 4 mo' => 'PI',
		  'pi 4mo' => 'PI',
		  'pi baseline' => 'PI',
		  'pi circleline habit' => 'PI',
		  'pi cube' => 'PI',
		  'pi eye tracking' => 'PI',
		  'pi eyetracking' => 'PI',
		  'pi eyetracking/habit' => 'PI',
		  'pi follow up' => 'PI',
		  'pi follow up tobi study' => 'PI',
		  'pi hab it' => 'PI',
		  'pi habit' => 'PI',
		  'pi habit & vm' => 'PI',
		  'pi habit again (oops)' => 'PI',
		  'pi habituation' => 'PI',
		  'pi habti' => 'PI',
		  'pi huizer cube' => 'PI',
		  'pi insides' => 'PI',
		  'pi insides & mental rotation' => 'PI',
		  'pi insides & of' => 'PI',
		  'pi line' => 'PI',
		  'pi of' => 'PI',
		  'pi or pnas' => 'PI',
		  'pi pcture grasp' => 'Picture Grasping',
		  'pi picture' => 'Picture Grasping',
		  'pi picture grasp' => 'Picture Grasping',
		  'pi picture grasp & freev' => 'Picture Grasping',
		  'pi picture grasp & of' => 'Picture Grasping',
		  'pi picture grasp &freev' => 'Picture Grasping',
		  'pi picture grasp.' => 'Picture Grasping',
		  'pi picture grasp/freev' => 'Picture Grasping',
		  'pi picture grasp/of' => 'Picture Grasping',
		  'pi picture grasping' => 'Picture Grasping',
		  'pi picture grasping & of' => 'Picture Grasping',
		  'pi picture grasping task' => 'Picture Grasping',
		  'pi picture grasping/fv' => 'Picture Grasping',
		  'pi picutre grasp' => 'Picture Grasping',
		  'pi pref' => 'PI',
		  'pi repeat' => 'PI',
		  'pi repeat #3' => 'PI',
		  'pi repeat & fv' => 'PI',
		  'pi repeat & of' => 'PI',
		  'pi repeat (4mo)' => 'PI',
		  'pi repeat (tobii)' => 'PI',
		  'pi repeat 3' => 'PI',
		  'pi repeat session' => 'PI',
		  'pi repeat-3 mo' => 'PI',
		  'pi repeat-3mo' => 'PI',
		  'pi study' => 'PI',
		  'pi study (05/29 beginning)' => 'PI',
		  'pi study (unexpectedly)' => 'PI',
		  'pi test' => 'PI',
		  'pi test & nbb' => 'PI',
		  'pi test & nbb study' => 'PI',
		  'pi test &nbb study' => 'PI',
		  'pi test (beg. 0ct 10)' => 'PI',
		  'pi test and ball/box study' => 'PI',
		  'pi test and ballbox study' => 'PI',
		  'pi test and nbb' => 'PI',
		  'pi test and new ball box' => 'PI',
		  'pi test and new ball/box' => 'PI',
		  'pi test/ ball box study' => 'PI',
		  'pi tobii' => 'PI',
		  'pi tobii 2 mos' => 'PI',
		  'pi v.4' => 'PI',
		  'pi(tobii)' => 'PI',
		  'pi-2m' => 'PI',
		  'pi-2mo' => 'PI',
		  'pi-3' => 'PI',
		  'pi-4m' => 'PI',
		  'pi-4mo' => 'PI',
		  'pi-eye tracking' => 'PI',
		  'pi-tobii' => 'PI',
		  'pi/fp2' => 'PI',
		  'pi/sounds' => 'PI',
		  'pi/vm' => 'PI',
		  'pi2' => 'PI',
		  'pi2 2mo' => 'PI',
		  'pi2m' => 'PI',
		  'pi2m (tobi)' => 'PI',
		  'pi2m /update info' => 'PI',
		  'pi2m tobii' => 'PI',
		  'pi2m/update' => 'PI',
		  'pi2m/update info' => 'PI',
		  'pi2mo' => 'PI',
		  'pi2motobii' => 'PI',
		  'pi3' => 'PI',
		  'pi3 2mo' => 'PI',
		  'pi3 4mo' => 'PI',
		  'pi3 and fv' => 'PI',
		  'pi3 or of300' => 'PI',
		  'pi3-2m' => 'PI',
		  'pi3-2mo' => 'PI',
		  'pi3-4m' => 'PI',
		  'pi3-4mo' => 'PI',
		  'pi3/fv' => 'PI',
		  'pi4 & of' => 'PI',
		  'pi4m' => 'PI',
		  'pi4m & of' => 'PI',
		  'pi4m & pnas' => 'PI',
		  'pi4m-tobii' => 'PI',
		  'pi4mo' => 'PI',
		  'pian' => 'Piano Intervals',
		  'pian  aba' => 'Piano Intervals',
		  'pian aba' => 'Piano Intervals',
		  'pianaba' => 'Piano Intervals',
		  'piano interval' => 'Piano Intervals',
		  'piano intervals' => 'Piano Intervals',
		  'piano intervals & picture grasp' => 'Piano Intervals',
		  'piano intervals, analogy study' => 'Piano Intervals',
		  'piano intervals/pi picture' => 'Piano Intervals',
		  'piano intevals' => 'Piano Intervals',
		  'piano-intervals' => 'Piano Intervals',
		  'pic r' => 'picr',
		  'picr' => 'picr',
		  'picr2' => 'picr',
		  'picr3' => 'picr',
		  'picr3/sdg' => 'picr',
		  'picrep' => 'picr',
		  'picry' => 'picr',
		  'pict grasp' => 'Picture Grasping',
		  'picture grasping' => 'Picture Grasping',
		  'picture grasping & freev' => 'Picture Grasping',
		  'pilot' => 'Grant Pilot',
		  'pilot grant studies' => 'Grant Pilot',
		  'pilot studies' => 'Grant Pilot',
		  'pilot studies for grant' => 'Grant Pilot',
		  'pilot studies for grants' => 'Grant Pilot',
		  'pilot study for grant' => 'Grant Pilot',
		  'pit test' => 'PI',
		  'pitest' => 'PI',
		  'pitest & nbb' => 'PI',
		  'pitest & nbb study' => 'PI',
		  'pitest &nbb study' => 'PI',
		  'pitest (beg.oct.10)' => 'PI',
		  'pitest and nbb' => 'PI',
		  'pitest/update info' => 'PI',
		  'pitobii' => 'PI',
		  'platform' => 'Platform Shoe',
		  'platform shoe/step counter' => 'Platform Shoe',
		  'platform shoe/weighted vest' => 'Platform Shoe',
		  'platformshoe' => 'Platform Shoe',
		  'platform shoe' => 'Platform Shoe',
		  'pnas' => 'PNAS',
		  'pnas & pi' => 'PNAS & PI',
		  'pnas & pi habit' => 'PNAS & PI',
		  'pnas -1 trial' => 'PNAS',
		  'pnas 1 trial' => 'PNAS',
		  'pnas 4' => 'PNAS',
		  'pnas 4 trials' => 'PNAS',
		  'pnas 4trials' => 'PNAS',
		  'pnas or pi' => 'PNAS',
		  'pnas rep' => 'PNAS',
		  'pnas rep, identity pilot' => 'PNAS',
		  'pnas rep-1trial' => 'PNAS',
		  'pnas rep; identity pilot' => 'PNAS',
		  'pnas replication' => 'PNAS',
		  'pnas trial' => 'PNAS',
		  'pnas trial 1' => 'PNAS',
		  'pnas, pi habit' => 'PNAS',
		  'pnas- 1 trial' => 'PNAS',
		  'pnas- 1trial' => 'PNAS',
		  'pnas- 4 trials' => 'PNAS',
		  'pnas- rep' => 'PNAS',
		  'pnas-1 tiral' => 'PNAS',
		  'pnas-1 trial' => 'PNAS',
		  'pnas-1trial' => 'PNAS',
		  'pnas-4' => 'PNAS',
		  'pnas-4 trial' => 'PNAS',
		  'pnas-4 trials' => 'PNAS',
		  'pnas-rep' => 'PNAS',
		  'pnas-rep (6 mo)' => 'PNAS',
		  'pnas-t trial' => 'PNAS',
		  'pnas-trial' => 'PNAS',
		  'pnas4' => 'PNAS',
		  'pnas4 & pi' => 'PNAS',
		  'pnas4 & pi habit' => 'PNAS',
		  'pnas4 trial' => 'PNAS',
		  'pnas4 trials' => 'PNAS',
		  'pnas4 w/ delay' => 'PNAS',
		  'pnas4/mental rotation' => 'PNAS',
		  'pnas4trials' => 'PNAS',
		  'pnas_ 1trial' => 'PNAS',
		  'pnas_1 trial' => 'PNAS',
		  'pnasr' => 'PNAS',
		  'pnasr4' => 'PNAS',
		  'poss' => 'Poss',
		  'poss/imposs-4mo' => 'Poss/imposs',
		  'preg ap' => 'Preg Ap',
		  'pregnant ap' => 'Preg Ap',
		  'pregnant apertures' => 'Preg Ap',
		  'prone progression' => 'DR CrawlSit1',
		  'proneprogress' => 'DR CrawlSit1',
		  'proneprogressiob' => 'DR CrawlSit1',
		  'proneprogression' => 'DR CrawlSit1',
		  'reach  ap' => 'Reach Ap',
		  'reach ap' => 'Reach Ap',
		  'reach ap 34' => 'Reach Ap',
		  'reach aps' => 'Reach Ap',
		  'reach aps 5-7' => 'Reach Ap',
		  'reachap' => 'Reach Ap',
		  'reachin ap' => 'Reach Ap',
		  'reachinap' => 'Reach Ap',
		  'reaching' => 'Reach Ap',
		  'reaching 22' => 'Reach Ap',
		  'reaching 28' => 'Reach Ap',
		  'reaching ap' => 'Reach Ap',
		  'reaching ap 22' => 'Reach Ap',
		  'reaching ap 34' => 'Reach Ap',
		  'reaching ap infant' => 'Reach Ap',
		  'reaching ap.' => 'Reach Ap',
		  'reaching aperture' => 'Reach Ap',
		  'reaching apertures' => 'Reach Ap',
		  'reaching apertures 16' => 'Reach Ap',
		  'reaching apertures 34' => 'Reach Ap',
		  'reaching apertures infant' => 'Reach Ap',
		  'reaching aps' => 'Reach Ap',
		  'reaching doors' => 'Reaching Doors',
		  'reaching doors/wobbly handrails' => 'Reaching Doors',
		  'reachinga' => 'Reach Ap',
		  'reachingap' => 'Reach Ap',
		  'reachingaps' => 'Reach Ap',
		  'reachings aps' => 'Reach Ap',
		  'reachng ap' => 'Reach Ap',
		  'reachs aps' => 'Reach Ap',
		  'rhthym control' => 'Rhythm Control',
		  'rhtyhm control' => 'Rhythm Control',
		  'rhyhtm control' => 'Rhythm Control',
		  'rhythm control' => 'Rhythm Control',
		  'rl' => 'rl',
		  'rob' => 'Robot',
		  'robot' => 'Robot',
		  'robot 16 pilot' => 'Robot',
		  'robot pilot' => 'Robot',
		  'robot-marcus lab' => 'Robot',
		  'robt' => 'Robot',
		  'ronot' => 'Robot',
		  'saf rep' => 'Saff Rep',
		  'saf rep & dogs & signs?' => 'Saff Rep',
		  'saf rep/wa' => 'Saff Rep',
		  'saff rep' => 'Saff Rep',
		  'saff rep & dogs' => 'Saff Rep',
		  'saff rep & dogs & signs?' => 'Saff Rep',
		  'saffran stats rep' => 'Saff Rep',
		  'sc first steps' => 'First Steps',
		  'se' => 'SE',
		  'sem' => 'SEM',
		  'sem
		sem' => 'SEM',
		  'sentences' => 'Sentences',
		  'sentences #1 and #2' => 'Sentences',
		  'sentences 1' => 'Sentences',
		  'sentences/exp 5' => 'Sentences',
		  'shape sorter' => 'Shape Sorter',
		  'shape sorters' => 'Shape Sorter',
		  'shaper sorter' => 'Shape Sorter',
		  'shapes' => 'Shapes',
		  'shapes & wa' => 'Shapes',
		  'shapes and of&ms2' => 'Shapes',
		  'shapes survey' => 'Shapes',
		  'shapes+perception' => 'Shapes',
		  'shapes/object form' => 'Shapes',
		  'sign' => 'Signs',
		  'sign pretest tones conrol' => 'Signs',
		  'signs' => 'Signs',
		  'signs & dogs' => 'Signs',
		  'signs & wa' => 'Signs',
		  'signs (language lab)' => 'Signs',
		  'signs 11' => 'Signs-11',
		  'signs 11*' => 'Signs-11',
		  'signs 7' => 'Signs-7',
		  'signs pilot' => 'Signs',
		  'signs-11' => 'Signs-11',
		  'signs-11*' => 'Signs-11',
		  'signs-11/exp 5' => 'Signs-11',
		  'signs-7' => 'Signs-7',
		  'signs-7/ update info' => 'Signs-7',
		  'signs11' => 'Signs-11',
		  'signs7' => 'Signs-7',
		  'signs7 and piano int. practice' => 'Signs-7',
		  'signs?' => 'Signs-7',
		  'sitting' => 'Sitting',
		  'sitting (or reaching)' => 'Sitting',
		  'sitting long' => 'Sitting',
		  'sitting/ pilot data' => 'Sitting',
		  'sitting/ sitting long' => 'Sitting',
		  'sitting/grant pilot' => 'Sitting',
		  'sitting/sitting long' => 'Sitting',
		  'sj' => 'SJ',
		  'sl mixed' => 'SL',
		  'sl solo' => 'SL-Solo',
		  'sl solo & visual sensitivity' => 'SL-Solo',
		  'sl solo mixed' => 'SL-Solo',
		  'sl solo-2mo' => 'SL-Solo',
		  'sl solo/mixed' => 'SL-Solo',
		  'sl solol' => 'SL-Solo',
		  'sl- solo' => 'SL-Solo',
		  'sl-mixed' => 'SL-Solo',
		  'sl-solo' => 'SL-Solo',
		  'sl-solo & visual sensitivity' => 'SL-Solo',
		  'sl-solo, vis sens pilot' => 'SL-Solo',
		  'sl-solo, vis sensitivity' => 'SL-Solo',
		  'sl-solo, visual sensitivity' => 'SL-Solo',
		  'smiles' => 'Smiley Faces',
		  'smiley faces' => 'Smiley Faces',
		  'smiley faces and piano int' => 'Smiley Faces',
		  'smileyfaces' => 'Smiley Faces',
		  'smileys' => 'Smiley Faces',
		  'smileys and piano intervals' => 'Smiley Faces',
		  'sms' => 'SMS',
		  'socail judge' => 'Social Judgement',
		  'socal judge' => 'Social Judgement',
		  'social' => 'Social Judgement',
		  'social jude' => 'Social Judgement',
		  'social judge' => 'Social Judgement',
		  'social judgement' => 'Social Judgement',
		  'social judgment' => 'Social Judgement',
		  'social juge' => 'Social Judgement',
		  'socila judge' => 'Social Judgement',
		  'socual judge' => 'Social Judgement',
		  'soial judge' => 'Social Judgement',
		  'sounds' => 'Sounds',
		  'sounds/em' => 'Sounds',
		  'sounds/pi3' => 'Sounds',
		  'sounds/pref baby' => 'Sounds',
		  'sp' => 'SP',
		  'sp & update info' => 'SP',
		  'sp / update info' => 'SP',
		  'sp 80-90 days' => 'SP',
		  'sp or np3mo' => 'SP',
		  'sp/ms/uity' => 'SP',
		  'sp/ms/unity' => 'SP',
		  'sp/sl solo' => 'SP',
		  'sp/update' => 'SP',
		  'sp/update info' => 'SP',
		  'spatial handrails' => 'Spatial Handrails',
		  'spatial' => 'Spatial Handrails',
		  'spacial' => 'Spatial Handrails',
		  'spat handrails' => 'Spatial Handrails',
		  'spatail handrails' => 'Spatial Handrails',
		  'spatial bridges' => 'Spatial Handrails',
		  'spatial handrail' => 'Spatial Handrails',
		  'spatial handrials' => 'Spatial Handrails',
		  'speech to tim' => 'Speech to Timbre',
		  'speech to timb' => 'Speech to Timbre',
		  'speech to timbre' => 'Speech to Timbre',
		  'speech to tones' => 'Speech to Timbre',
		  'speech to tones?' => 'Speech to Timbre',
		  'speech to x' => 'Speech to X',
		  'speech-tim' => 'Speech to Timbre',
		  'speech-timbre' => 'Speech to Timbre',
		  'speechtimbre' => 'Speech to Timbre',
		  'srp' => 'SRP',
		  'srp (beg.10/13)' => 'SRP',
		  'srp - saff tones' => 'SRP',
		  'srp beginning 10/13' => 'SRP',
		  'srp beginning 10/14' => 'SRP',
		  'srp beginning 10/15' => 'SRP',
		  'srp word' => 'SRP',
		  'srp- hugh\'s practice' => 'SRP',
		  'srp-r' => 'SRP-R',
		  'srp-r
		srp-r' => 'SRP-R',
		  'srp-rules' => 'SRP-R',
		  'srp-s' => 'SRP-S',
		  'srp-saff tones' => 'SRP-S',
		  'srp-stats' => 'SRP-S',
		  'srp-stats-ns' => 'SRP-S',
		  'srp-stats-tone' => 'SRP-S',
		  'srp/upate info' => 'SRP',
		  'srp/update info' => 'SRP',
		  'srp/wa2' => 'SRP',
		  'srp/word assoc' => 'SRP',
		  'srp/wordass' => 'SRP',
		  'srp/wordass2' => 'SRP',
		  'srpl' => 'SRP',
		  'ss' => 'SS',
		  'ss 2' => 'SS',
		  'ss/ml' => 'SS',
		  'ss2' => 'SS',
		  'stag' => 'STAG',
		  'stag  young' => 'STAG',
		  'stag y' => 'STAG',
		  'stag young' => 'STAG',
		  'stagy' => 'STAG',
		  'stagyoung' => 'STAG',
		  'stats' => 'Stats Rules',
		  'stats ones' => 'Stats Tones',
		  'stats rule' => 'Stats Rules',
		  'stats rules' => 'Stats Rules',
		  'stats rules, update' => 'Stats Rules',
		  'stats rules,update' => 'Stats Rules',
		  'stats rules/ nad & wa' => 'Stats Rules',
		  'stats rules/ wa' => 'Stats Rules',
		  'stats rules/voiced aba' => 'Stats Rules',
		  'stats rules/wa' => 'Stats Rules',
		  'stats tones' => 'Stats Tones',
		  'stats tones, upd' => 'Stats Tones',
		  'stats tones, update' => 'Stats Tones',
		  'stats tones/ funny faces' => 'Stats Tones',
		  'stats tones/vab' => 'Stats Tones',
		  'stats tones`' => 'Stats Tones',
		  'stats, update' => 'Stats Rules',
		  'stats, updated' => 'Stats Rules',
		  'stats/nad' => 'Stats Rules',
		  'stats/vaba' => 'Stats Rules',
		  'step adapt' => 'Step Adapt',
		  'step adapt, weighted vest' => 'Step Adapt / WV',
		  'step adapt/ weighted vest' => 'Step Adapt / WV',
		  'step adapt/weight vest' => 'Step Adapt / WV',
		  'step adapt/weighted vest' => 'Step Adapt / WV',
		  'step adapt/weightedvest' => 'Step Adapt / WV',
		  'step adaptation' => 'Step Adapt',
		  'step counder' => 'Step Counter',
		  'step counter' => 'Step Counter',
		  'step cpunter' => 'Step Counter',
		  'step/counter' => 'Step Counter',
		  'stpe counter' => 'Step Counter',
		  'step counter /wv' => 'Step Counter / WV',
		  'step counter test' => 'Step Counter',
		  'step counter/ weighted vest' => 'Step Counter / WV',
		  'step counter/ wv' => 'Step Counter / WV',
		  'step counter/friction' => 'Step Counter',
		  'step counter/friction 15' => 'Step Counter',
		  'step counter/wv' => 'Step Counter / WV',
		  'step-a' => 'Step Adapt',
		  'step-ad' => 'Step Adapt',
		  'step-adapt' => 'Step Adapt',
		  'step/counter/wv' => 'Step Counter / WV',
		  'stepa' => 'Step Adapt',
		  'stepad' => 'Step Adapt',
		  'stepadapt' => 'Step Adapt',
		  'stepadapt/weightedvest' => 'Step Adapt / WV',
		  'stepcounter' => 'Step Counter',
		  'stepcounter/wv' => 'Step Counter / WV',
		  'strobe' => 'Strobe',
		  'strobe 1' => 'Strobe',
		  'strobe 4' => 'Strobe',
		  'strobe-1' => 'Strobe',
		  'strobe-4' => 'Strobe',
		  'strobe/pi3' => 'Strobe',
		  'strobe1' => 'Strobe',
		  'strobe1/pi3' => 'Strobe',
		  'studies for grant' => 'Grant Pilot',
		  't #' => 't#',
		  't#' => 't#',
		  't#2' => 't#',
		  'tc' => 'TC',
		  'tc 1' => 'TC',
		  'tc ii' => 'TC',
		  'tc1' => 'TC',
		  'tc2' => 'TC',
		  'tcii' => 'TC',
		  'teflon' => 'MA Teflon',
		  'teflon staircase' => 'MA Teflon',
		  'tei' => 'TEI',
		  'tei 2' => 'TEI',
		  'tei-2' => 'TEI',
		  'tei2' => 'TEI',
		  'things & stuff' => 'Things & Stuff',
		  'things & stuff - 11' => 'Things & Stuff',
		  'things & stuff 11' => 'Things & Stuff',
		  'things & stuff 9' => 'Things & Stuff',
		  'things & stuff, signs' => 'Things & Stuff',
		  'things & stuff-11' => 'Things & Stuff',
		  'things & suff' => 'Things & Stuff',
		  'things and stuf' => 'Things & Stuff',
		  'things and stuff' => 'Things & Stuff',
		  'things and stuff - 11' => 'Things & Stuff',
		  'things and stuff -11' => 'Things & Stuff',
		  'things and suff' => 'Things & Stuff',
		  'things& stuff' => 'Things & Stuff',
		  'things&stuff' => 'Things & Stuff',
		  'things&stuff-11' => 'Things & Stuff',
		  'thinsg & stuff' => 'Things & Stuff',
		  'timb  med' => 'Timbre Med',
		  'timb med' => 'Timbre Med',
		  'timb med in replacement of dogs' => 'Timbre Med',
		  'timber mediation' => 'Timbre Med',
		  'timbre' => 'Timbre',
		  'timbre & lps' => 'Timbre',
		  'timbre (practice)' => 'Timbre',
		  'timbre 11' => 'Timbre',
		  'timbre 11 month' => 'Timbre',
		  'timbre 11m' => 'Timbre',
		  'timbre 2' => 'Timbre',
		  'timbre and lps' => 'Timbre',
		  'timbre me' => 'Timbre Med',
		  'timbre med' => 'Timbre Med',
		  'timbre med 11' => 'Timbre Med',
		  'timbre med 2' => 'Timbre Med',
		  'timbre med/nad&wa' => 'Timbre Med',
		  'timbre mediation' => 'Timbre Med',
		  'timbre pilot' => 'Timbre',
		  'timbre pilot (not lps)' => 'Timbre',
		  'timbre pilot and lps' => 'Timbre',
		  'timbre-11' => 'Timbre',
		  'timbre-11 & things&stuff' => 'Timbre',
		  'timbre-11 + things&stuff' => 'Timbre',
		  'timbre-marcus' => 'Timbre',
		  'timbre-practice' => 'Timbre',
		  'timbre/ hugh\'s practice' => 'Timbre',
		  'timre' => 'Timbre',
		  'tone' => 'Tones',
		  'tone control' => 'Tones',
		  'tones' => 'Tones',
		  'tones control' => 'Tones',
		  'tonescontrol/sign pretest' => 'Tones',
		  'un signs' => 'Un-Signs',
		  'un-signs' => 'Un-Signs',
		  'unsigns' => 'Un-Signs',
		  'vaba' => 'Voiced ABA',
		  'vaba, update' => 'Voiced ABA',
		  'vaba/ exp 5' => 'Voiced ABA',
		  'vaba/12b' => 'Voiced ABA',
		  'vaba/exp 12b' => 'Voiced ABA',
		  'vaba/exp 5' => 'Voiced ABA',
		  'vaba/stats tones' => 'Voiced ABA',
		  'vaba`' => 'Voiced ABA',
		  'vender tray' => 'Vendor Tray',
		  'vendor' => 'Vendor Tray',
		  'vendor tray' => 'Vendor Tray',
		  'ventor tray' => 'Vendor Tray',
		  'video track 12' => 'VT 12',
		  'video track 12w' => 'VT 12',
		  'video tracking' => 'VT 12',
		  'videotracking' => 'VT 12',
		  'vioced intervals' => 'Voiced ABA',
		  'visual' => 'Visual Marcus',
		  'visual macrus' => 'Visual Marcus',
		  'visual marccus mixed-8mo' => 'Visual Marcus',
		  'visual marcus' => 'Visual Marcus',
		  'visual marcus location' => 'Visual Marcus',
		  'visual marcus mixd-8mo' => 'Visual Marcus',
		  'visual marcus mixed' => 'Visual Marcus',
		  'visual marcus mixed + rhythm control' => 'Visual Marcus',
		  'visual marcus mixed -5mo' => 'Visual Marcus',
		  'visual marcus mixed 5 month' => 'Visual Marcus',
		  'visual marcus mixed 5mo' => 'Visual Marcus',
		  'visual marcus mixed-5m' => 'Visual Marcus',
		  'visual marcus mixed-5mo' => 'Visual Marcus',
		  'visual marcus mixed-8mo' => 'Visual Marcus',
		  'visual marcus mixed-8mo; np-9 mo' => 'Visual Marcus',
		  'visual marcus mixed-8mo; np-9mo' => 'Visual Marcus',
		  'visual marcus mixed-8mo; rhythm control' => 'Visual Marcus',
		  'visual marcus mixed08mo' => 'Visual Marcus',
		  'visual marcus, auditory pitch' => 'Visual Marcus',
		  'visual marcus, identity-pilot-11 mo' => 'Visual Marcus',
		  'visual marcus- 5mo' => 'Visual Marcus',
		  'visual marcus-11' => 'Visual Marcus',
		  'visual marcus-11 mo' => 'Visual Marcus',
		  'visual marcus-11-mo' => 'Visual Marcus',
		  'visual marcus-11mo' => 'Visual Marcus',
		  'visual marcus-5 mo' => 'Visual Marcus',
		  'visual marcus-5mo' => 'Visual Marcus',
		  'visual marcus-8 mo' => 'Visual Marcus',
		  'visual marcus-8 mo; arl-pitch' => 'Visual Marcus',
		  'visual marcus-mixed-8mo' => 'Visual Marcus',
		  'visual marcus: abb vs. aab- 8 mo' => 'Visual Marcus',
		  'visual mix' => 'Visual Mixed',
		  'visual mixed' => 'Visual Mixed',
		  'visual pattern' => 'Visual Pattern',
		  'visual preference' => 'Visual Preference',
		  'visual preference 1&2' => 'Visual Preference 1 & 2',
		  'visual preference part 2' => 'Visual Preference 2',
		  'visual search' => 'Visual Search',
		  'visual search & neg prime' => 'Visual Search & Neg Priming',
		  'visual search, neg prim' => 'Visual Search & Neg Priming',
		  'visual search, negative priming' => 'Visual Search & Neg Priming',
		  'visual search-try two' => 'Visual Search',
		  'visual search.' => 'Visual Search',
		  'visual search/ah' => 'Visual Search',
		  'visual search/motion sensitivity' => 'Visual Search',
		  'visual search; neg prim 3-mo' => 'Visual Search',
		  'visual sensitivity' => 'Visual Sensitivity',
		  'visual sensitivity pilot' => 'Visual Sensitivity',
		  'visual sensitivity pilot with tobii' => 'Visual Sensitivity',
		  'visual serch' => 'Visual Search',
		  'vm' => 'VM',
		  'vm & p-i' => 'VM',
		  'vm & p-l' => 'VM',
		  'vm & p/i' => 'VM',
		  'vm & p/l' => 'VM',
		  'vm & pi' => 'VM',
		  'vm & pi habit' => 'VM',
		  'vm &p/l' => 'VM',
		  'vm (maybe pi)' => 'VM',
		  'vm 14' => 'VM',
		  'vm abbvsaab-8 mo; bounce-stream' => 'VM',
		  'vm and or p/i' => 'VM',
		  'vm follow up' => 'VM',
		  'vm follow-up' => 'VM',
		  'vm or pi' => 'VM',
		  'vm pilot' => 'VM',
		  'vm& p-l' => 'VM',
		  'vm& p/l' => 'VM',
		  'vm& pi' => 'VM',
		  'vm&p-l' => 'VM',
		  'vm, pi' => 'VM',
		  'vm- p/l' => 'VM',
		  'vm/ p/i' => 'VM',
		  'vm/ pi' => 'VM',
		  'vm/p /i' => 'VM',
		  'vm/p/i' => 'VM',
		  'vm/pi' => 'VM',
		  'vm11' => 'VM 11',
		  'vm14' => 'VM 14',
		  'vm14-1' => 'VM 14-1',
		  'vm14-2' => 'VM 14-2',
		  'vm5 mo' => 'VM',
		  'vm5mo' => 'VM',
		  'vm? pi ?' => 'VM',
		  'vmc' => 'VMC',
		  'vmc  & pi pref' => 'VMC',
		  'vmc & pi habit' => 'VMC',
		  'vmc &pi' => 'VMC',
		  'vmc &pi  habit' => 'VMC',
		  'vmc 11' => 'VMC',
		  'vmc and pi' => 'VMC',
		  'vmc, pi' => 'VMC',
		  'vmc, pi habit' => 'VMC',
		  'vmc/pi habit' => 'VMC',
		  'vmc11' => 'VMC',
		  'vmc8' => 'VMC',
		  'vmc8mo' => 'VMC',
		  'vmf1' => 'VMC',
		  'vmf11' => 'VMF-11',
		  'vmf11 (of range 12/21)' => 'VMF-11',
		  'vmf11(beg thurs 10/27)' => 'VMF-11',
		  'vmf11-2' => 'VMF 11-2',
		  'vmf11/update info' => 'VMF-11',
		  'vmf14' => 'VMF-14',
		  'vmf14-2' => 'VMF-14-2',
		  'vmf14-2 johnson' => 'VMF-14-2',
		  'vmf8' => 'VMF-8',
		  'vmm' => 'VMM',
		  'vmm & fp2' => 'VMM',
		  'vmm & p/i' => 'VMM',
		  'vmm (5mos)' => 'VMM',
		  'vmm 5' => 'VMM',
		  'vmm 5mo' => 'VMM',
		  'vmm-5mo' => 'VMM',
		  'vmm5' => 'VMM',
		  'vmm5 & fp2' => 'VMM',
		  'vmm5mo' => 'VMM',
		  'vmm8mo' => 'VMM',
		  'vmmj' => 'VMM',
		  'vocied aba' => 'Voiced ABA',
		  'voiced / exp5' => 'Voiced ABA',
		  'voiced aba' => 'Voiced ABA',
		  'voiced aba and exp 5' => 'Voiced ABA',
		  'voiced aba*' => 'Voiced ABA',
		  'voiced aba, update' => 'Voiced ABA',
		  'voiced aba,update' => 'Voiced ABA',
		  'voiced aba/exp 5' => 'Voiced ABA',
		  'voiced inervals' => 'Voiced ABA',
		  'voiced int' => 'Voiced ABA',
		  'voiced int aba' => 'Voiced ABA',
		  'voiced int.' => 'Voiced ABA',
		  'voiced intervals' => 'Voiced ABA',
		  'voiced intervals aba' => 'Voiced ABA',
		  'voicedi ntervals' => 'Voiced ABA',
		  'voived intervals' => 'Voiced ABA',
		  'vs' => 'VS',
		  'vsm' => 'VSM',
		  'vt' => 'VT 12',
		  'vt  12' => 'VT 13',
		  'vt 12' => 'VT 14',
		  'vt 12 c' => 'VT 15',
		  'vt 12 c/w' => 'VT 16',
		  'vt 12 crawl' => 'VT 17',
		  'vt 12 w' => 'VT 18',
		  'vt 12 walk' => 'VT 19',
		  'vt 12/cliff' => 'VT 20',
		  'vt crawl 12' => 'VT 21',
		  'vt-12' => 'VT 22',
		  'vt12' => 'VT 23',
		  'vt12 c' => 'VT 24',
		  'vt12 w' => 'VT 25',
		  'vt12 walk' => 'VT 26',
		  'vt12c' => 'VT 27',
		  'vt12w' => 'VT 28',
		  'vt212' => 'VT 29',
		  'wa' => 'WA',
		  'wa-14' => 'WA',
		  'wa/ funny faces' => 'WA',
		  'wa/f or st' => 'WA',
		  'wa/f or st, update' => 'WA',
		  'wa/funnt' => 'WA',
		  'wa/funny' => 'WA',
		  'wa/funny faces' => 'WA',
		  'wa/funny, udpate' => 'WA',
		  'wa/funny, update' => 'WA',
		  'wa/funnyfaces' => 'WA',
		  'wa/shapes' => 'WA',
		  'wa/stats' => 'WA',
		  'wa/stats rules' => 'WA',
		  'wad & wa' => 'NAD & WA',
		  'walking ap' => 'Loc Ap',
		  'walking aperture' => 'Loc Ap',
		  'walking apertures' => 'Loc Ap',
		  'walking apertures (shape sorter)' => 'Loc Ap',
		  'walking/crawling aperture' => 'Loc Ap',
		  'walking/crawling apertures' => 'Loc Ap',
		  'wallking apertures' => 'Loc Ap',
		  'weight vest' => 'WV',
		  'weightd vest' => 'WV',
		  'weighted' => 'WV',
		  'weighted vest' => 'WV',
		  'weighted  vest' => 'WV',
		  'weighted vest/ step adapt' => 'Step Adapt / WV',
		  'weighted vest/step counter' => 'Step Counter / WV',
		  'weighted vest/step coutner' => 'Step Counter / WV',
		  'weighted vest/stepcounter' => 'Step Counter / WV',
		  'weightedvest' => 'WV',
		  'weigthed vest' => 'WV',
		  'wobbling rail cruising' => 'Wobbly Cruise',
		  'wobbly' => 'Wobbly Cruise',
		  'wobbly / fs' => 'Wobbly Cruise',
		  'wobbly cruise/ mom advice 12 crawl' => 'Wobbly Cruise',
		  'wobbly cruising' => 'Wobbly Cruise',
		  'wobbly cruisse' => 'Wobbly Cruise',
		  'wobbly curise' => 'Wobbly Cruise',
		  'wobbly cruise' => 'Wobbly Cruise',
		  'wobbly handrails' => 'Wobbly Cruise',
		  'wobbly rail' => 'Wobbly Cruise',
		  'wobby cruise' => 'Wobbly Cruise',
		  'word asoc srp' => 'WA/SRP',
		  'word asoc/srp' => 'WA/SRP',
		  'word ass' => 'WA',
		  'word assoc' => 'WA',
		  'word assoc/nad & wa' => 'NAD & WA',
		  'word assoc/nad& wa' => 'NAD & WA',
		  'word assoc/srp' => 'WA/SRP',
		  'word assoc/srp (starting 10/31)' => 'WA/SRP',
		  'word association' => 'WA',
		  'word asssoc/srp' => 'WA/SRP',
		  'word/srp' => 'WA/SRP',
		  'wordass' => 'WA',
		  'wv/ step counter' => 'Step Counter / WV',
		  'wv/sa' => 'Step Adapt / WV',
		  'wv/step counter' => 'Step Counter / WV',
		  'xabyb' => 'xabyb',
		  'xabyb practice' => 'xabyb',
		  12 => 'Exp 12B',
		);
		
		return($studyList);
	}
	
	function tempStudyList()
	{
		$temp = array (
			  0 => '# changed to non-published #',
			  1 => '# changed to non-published #',
			  2 => '# disconnect',
			  3 => '# disconnected',
			  4 => '# disconnected (both home and work)',
			  5 => '# not in service',
			  6 => '#disconnected',
			  7 => '#not in service',
			  8 => '(entered)',
			  9 => '-',
			  10 => '---',
			  11 => '--------',
			  12 => '----------------',
			  13 => '------------------',
			  14 => '2 cht',
			  15 => '2 cht pilot',
			  16 => '212- 423-2205',
			  17 => '2cht',
			  18 => '2cht pilot',
			  19 => '5 mo',
			  20 => '5 weeks premature',
			  21 => '6 wks early',
			  22 => '646- 201-4012',
			  23 => 'aabyb',
			  24 => 'auditory patterm',
			  25 => 'auditory pattern',
			  26 => 'bounce/auditory',
			  27 => 'came & said we',
			  28 => 'checklist',
			  29 => 'cold call- entered',
			  30 => 'daughter into database',
			  31 => 'didn\'t get all info- mom needed to go',
			  32 => 'disconnected',
			  33 => 'discovery health',
			  34 => 'do not call',
			  35 => 'don"t call',
			  36 => 'don\'t call',
			  37 => 'don\'t call for other studies',
			  38 => 'don\'t call premature',
			  39 => 'don\'t call, not interested',
			  40 => 'double entry',
			  41 => 'duplicate',
			  42 => 'duplicate record',
			  43 => 'entered',
			  44 => 'entered from cold',
			  45 => 'entered from cold call',
			  46 => 'entered into database',
			  47 => 'entry',
			  48 => 'extremely interested in tests',
			  49 => 'in with sibling',
			  50 => 'info call',
			  51 => 'johnson',
			  52 => 'lives too far away to come down',
			  53 => 'ma teflon/platform 19',
			  54 => 'marcus',
			  55 => 'mom adive 18',
			  56 => 'mom advice',
			  57 => 'mom advice 12 crawl',
			  58 => 'mom advice 12c',
			  59 => 'mom advice 12w',
			  60 => 'mom advice 13',
			  61 => 'mom advice12',
			  62 => 'mom com 20',
			  63 => 'mom comm 18',
			  64 => 'mom comm18',
			  65 => 'mom comm36',
			  66 => 'mom doesn\'t live at this #',
			  67 => 'mom not interested',
			  68 => 'mom seek',
			  69 => 'momcomm',
			  70 => 'momcomm 18',
			  71 => 'momcomm 20',
			  72 => 'momcomm18',
			  73 => 'momcomm20',
			  74 => 'moved to california',
			  75 => 'moving to texas',
			  76 => 'new additon',
			  77 => 'new num   718-837-8448',
			  78 => 'new number',
			  79 => 'new set up',
			  80 => 'none',
			  81 => 'not in service',
			  82 => 'not interested',
			  83 => 'not interested anymore.',
			  84 => 'not interested don\'t call',
			  85 => 'not interested ever',
			  86 => 'not interested, don\'t call',
			  87 => 'not interested.  don\'t call',
			  88 => 'not interested. don\'t call',
			  89 => 'not interseted',
			  90 => 'number changed',
			  91 => 'number disconnected',
			  92 => 'only call for phone surveys does want to come in',
			  93 => 'patch',
			  94 => 'patch 39',
			  95 => 'patch control',
			  96 => 'patch friction',
			  97 => 'patch27',
			  98 => 'platform shoe',
			  99 => 'practice headturn',
			  100 => 'pre mature',
			  101 => 'pre-mature',
			  102 => 'premature',
			  103 => 'premature!',
			  104 => 'premature!',
			  105 => 'premautre',
			  106 => 'put in database from cold calling',
			  107 => 'rule-learning',
			  108 => 'same as marluna machdromi',
			  109 => 'shaziela',
			  110 => 'sibling',
			  111 => 'sibling study',
			  112 => 'sibling\'s entry her dob',
			  113 => 'sounded a little hesitant',
			  114 => 'step counter',
			  115 => 'step cpunter',
			  116 => 'use other 1',
			  117 => 'use the other one!!',
			  118 => 'very interested-',
			  119 => 'wobbly cruise',
			  120 => 'wrong #',
			  121 => 'wrong number',
			  122 => 'wv'
			);
		
		return($temp);
	}
	
	function getDuplicateFamilies()
	{
		$duplicateFamilies = array(
			30 => array(
			    'email'    => '',
			    'phones' => array('7183211188', '5165073254')
			),
			84 => array(
			    'email'    => '',
			    'phones' => array('2129795849')
			),
			97 => array(
			    'email'    => '',
			    'phones' => array('2126919445')
			),
			217 => array(
			    'email'    => '',
			    'phones' => array('2125709602')
			),
			244 => array(
			    'email'    => '',
			    'phones' => array('2125709602')
			),
			254 => array(
			    'email'    => '',
			    'phones' => array('2122061066')
			),
			330 => array(
			    'email'    => '',
			    'phones' => array('2126777171')
			),
			357 => array(
			    'email'    => '',
			    'phones' => array('2125851604')
			),
			489 => array(
			    'email'    => '',
			    'phones' => array('2122391154')
			),
			505 => array(
			    'email'    => '',
			    'phones' => array('2019461002', '2122548124')
			),
			518 => array(
			    'email'    => '',
			    'phones' => array('2126853927')
			),
			523 => array(
			    'email'    => '',
			    'phones' => array('2128730012')
			),
			525 => array(
			    'email'    => '',
			    'phones' => array('2123711400')
			),
			541 => array(
			    'email'    => '',
			    'phones' => array('2125295932')
			),
			542 => array(
			    'email'    => '',
			    'phones' => array('2125295932')
			),
			546 => array(
			    'email'    => '',
			    'phones' => array('2126773095', '2123061723')
			),
			558 => array(
			    'email'    => '',
			    'phones' => array('2126140102')
			),
			626 => array(
			    'email'    => '',
			    'phones' => array('2126278734')
			),
			627 => array(
			    'email'    => '',
			    'phones' => array('2129896042')
			),
			632 => array(
			    'email'    => '',
			    'phones' => array('2122741475')
			),
			657 => array(
			    'email'    => '',
			    'phones' => array('2129292431', '2129293125')
			),
			660 => array(
			    'email'    => '',
			    'phones' => array('2128071862')
			),
			667 => array(
			    'email'    => '',
			    'phones' => array('2123169642')
			),
			669 => array(
			    'email'    => '',
			    'phones' => array('2128602928')
			),
			672 => array(
			    'email'    => '',
			    'phones' => array('2129660095')
			),
			674 => array(
			    'email'    => '',
			    'phones' => array('2123083459')
			),
			694 => array(
			    'email'    => '',
			    'phones' => array('2126741154')
			),
			703 => array(
			    'email'    => '',
			    'phones' => array('9149678365')
			),
			737 => array(
			    'email'    => '',
			    'phones' => array('2126275278')
			),
			824 => array(
			    'email'    => '',
			    'phones' => array('2127222019')
			),
			835 => array(
			    'email'    => '',
			    'phones' => array('2122546366', '7185269113')
			),
			839 => array(
			    'email'    => '',
			    'phones' => array('2127694472')
			),
			841 => array(
			    'email'    => '',
			    'phones' => array('2127537521')
			),
			842 => array(
			    'email'    => '',
			    'phones' => array('2127694472', '7185222300')
			),
			881 => array(
			    'email'    => '',
			    'phones' => array('2122285977')
			),
			900 => array(
			    'email'    => '',
			    'phones' => array('2126896322', '9178337943')
			),
			915 => array(
			    'email'    => '',
			    'phones' => array('2129415969')
			),
			917 => array(
			    'email'    => '',
			    'phones' => array('2125299481')
			),
			921 => array(
			    'email'    => '',
			    'phones' => array('7186531136')
			),
			939 => array(
			    'email'    => '',
			    'phones' => array('2125297175')
			),
			947 => array(
			    'email'    => '',
			    'phones' => array('2126770247')
			),
			950 => array(
			    'email'    => '',
			    'phones' => array('2126336372')
			),
			952 => array(
			    'email'    => '',
			    'phones' => array('2126731241')
			),
			957 => array(
			    'email'    => '',
			    'phones' => array('2128890513')
			),
			962 => array(
			    'email'    => '',
			    'phones' => array('2125794750')
			),
			967 => array(
			    'email'    => '',
			    'phones' => array('2124775861', '2123532570')
			),
			996 => array(
			    'email'    => '',
			    'phones' => array('2122541268')
			),
			999 => array(
			    'email'    => '',
			    'phones' => array('2122530629')
			),
			1001 => array(
			    'email'    => '',
			    'phones' => array('2122284941')
			),
			1003 => array(
			    'email'    => '',
			    'phones' => array('2126865398')
			),
			1006 => array(
			    'email'    => '',
			    'phones' => array('2128291581')
			),
			1009 => array(
			    'email'    => '',
			    'phones' => array('2122609633', '2122703021')
			),
			1011 => array(
			    'email'    => '',
			    'phones' => array('2125815726')
			),
			1013 => array(
			    'email'    => '',
			    'phones' => array('2125815726')
			),
			1026 => array(
			    'email'    => '',
			    'phones' => array('2126082140', '2123912555')
			),
			1043 => array(
			    'email'    => '',
			    'phones' => array('2122609512')
			),
			1047 => array(
			    'email'    => '',
			    'phones' => array('2127174505')
			),
			1076 => array(
			    'email'    => '',
			    'phones' => array('2124965250')
			),
			1089 => array(
			    'email'    => '',
			    'phones' => array('2127582630')
			),
			1101 => array(
			    'email'    => '',
			    'phones' => array('2126738058')
			),
			1107 => array(
			    'email'    => '',
			    'phones' => array('2122272252', '2125062400')
			),
			1122 => array(
			    'email'    => '',
			    'phones' => array('2034619339')
			),
			1128 => array(
			    'email'    => '',
			    'phones' => array('2124316599')
			),
			1190 => array(
			    'email'    => '',
			    'phones' => array('2124106103')
			),
			1196 => array(
			    'email'    => '',
			    'phones' => array('2126479720')
			),
			1203 => array(
			    'email'    => '',
			    'phones' => array('2127324221', '2123343277')
			),
			1217 => array(
			    'email'    => '',
			    'phones' => array('2126479384')
			),
			1239 => array(
			    'email'    => '',
			    'phones' => array('2122607671')
			),
			1245 => array(
			    'email'    => '',
			    'phones' => array('2122498905')
			),
			1253 => array(
			    'email'    => '',
			    'phones' => array('2127790572', '2128341157')
			),
			1256 => array(
			    'email'    => '',
			    'phones' => array('2122540241')
			),
			1260 => array(
			    'email'    => '',
			    'phones' => array('2128734799')
			),
			1262 => array(
			    'email'    => '',
			    'phones' => array('2125996414')
			),
			1275 => array(
			    'email'    => '',
			    'phones' => array('2124731768', '2129987533')
			),
			1294 => array(
			    'email'    => '',
			    'phones' => array('2128772590')
			),
			1302 => array(
			    'email'    => '',
			    'phones' => array('2129821317')
			),
			1306 => array(
			    'email'    => '',
			    'phones' => array('2124968914')
			),
			1314 => array(
			    'email'    => '',
			    'phones' => array('9738245962')
			),
			1331 => array(
			    'email'    => '',
			    'phones' => array('2129861034', '2123268540')
			),
			1343 => array(
			    'email'    => '',
			    'phones' => array('9147130646', '2128788041')
			),
			1345 => array(
			    'email'    => '',
			    'phones' => array('2128736300')
			),
			1365 => array(
			    'email'    => '',
			    'phones' => array('2124209394')
			),
			1378 => array(
			    'email'    => '',
			    'phones' => array('2122604133')
			),
			1386 => array(
			    'email'    => '',
			    'phones' => array('2127699783')
			),
			1409 => array(
			    'email'    => '',
			    'phones' => array('2126736750')
			),
			1411 => array(
			    'email'    => '',
			    'phones' => array('2127373055', '2126393310')
			),
			1419 => array(
			    'email'    => '',
			    'phones' => array('2123664377')
			),
			1445 => array(
			    'email'    => '',
			    'phones' => array('2122136171')
			),
			1458 => array(
			    'email'    => '',
			    'phones' => array('2125058284')
			),
			1459 => array(
			    'email'    => '',
			    'phones' => array('2122540263')
			),
			1470 => array(
			    'email'    => '',
			    'phones' => array('2123152369')
			),
			1471 => array(
			    'email'    => '',
			    'phones' => array('2126918577')
			),
			1477 => array(
			    'email'    => '',
			    'phones' => array('2129822379')
			),
			1478 => array(
			    'email'    => '',
			    'phones' => array('2127775154')
			),
			1493 => array(
			    'email'    => '',
			    'phones' => array('2126275185')
			),
			1541 => array(
			    'email'    => '',
			    'phones' => array('2126917927')
			),
			1565 => array(
			    'email'    => '',
			    'phones' => array('2127278734')
			),
			1566 => array(
			    'email'    => '',
			    'phones' => array('2127771582')
			),
			1574 => array(
			    'email'    => '',
			    'phones' => array('2127417041', '2123828513')
			),
			1578 => array(
			    'email'    => '',
			    'phones' => array('2129890346', '2126642367')
			),
			1584 => array(
			    'email'    => '',
			    'phones' => array('2126773006', '2123343277')
			),
			1585 => array(
			    'email'    => '',
			    'phones' => array('2126372696')
			),
			1597 => array(
			    'email'    => '',
			    'phones' => array('2127779294')
			),
			1605 => array(
			    'email'    => '',
			    'phones' => array('2122549753')
			),
			1615 => array(
			    'email'    => '',
			    'phones' => array('2124479114')
			),
			1627 => array(
			    'email'    => '',
			    'phones' => array('2126274718')
			),
			1628 => array(
			    'email'    => '',
			    'phones' => array('2124727811')
			),
			1637 => array(
			    'email'    => '',
			    'phones' => array('2126850723')
			),
			1638 => array(
			    'email'    => '',
			    'phones' => array('2122609949')
			),
			1647 => array(
			    'email'    => '',
			    'phones' => array('2127591024')
			),
			1655 => array(
			    'email'    => '',
			    'phones' => array('2123880669')
			),
			1657 => array(
			    'email'    => '',
			    'phones' => array('2124770383', '2126783876')
			),
			1660 => array(
			    'email'    => '',
			    'phones' => array('2126772654', '2124426860')
			),
			1671 => array(
			    'email'    => '',
			    'phones' => array('2126148801')
			),
			1678 => array(
			    'email'    => '',
			    'phones' => array('2122431966')
			),
			1721 => array(
			    'email'    => '',
			    'phones' => array('2122741105')
			),
			1744 => array(
			    'email'    => '',
			    'phones' => array('2127691684')
			),
			1768 => array(
			    'email'    => '',
			    'phones' => array('2125953197')
			),
			1777 => array(
			    'email'    => '',
			    'phones' => array('2123530468')
			),
			1786 => array(
			    'email'    => '',
			    'phones' => array('2126278037')
			),
			1796 => array(
			    'email'    => '',
			    'phones' => array('2124755233', '2126644324')
			),
			1803 => array(
			    'email'    => '',
			    'phones' => array('2129418970')
			),
			1836 => array(
			    'email'    => '',
			    'phones' => array('2127347030')
			),
			1842 => array(
			    'email'    => '',
			    'phones' => array('2125299568')
			),
			1853 => array(
			    'email'    => '',
			    'phones' => array('2126286602')
			),
			1855 => array(
			    'email'    => '',
			    'phones' => array('2124750264')
			),
			1860 => array(
			    'email'    => '',
			    'phones' => array('2126739124')
			),
			1861 => array(
			    'email'    => '',
			    'phones' => array('2122170416')
			),
			1865 => array(
			    'email'    => '',
			    'phones' => array('2129806730')
			),
			1867 => array(
			    'email'    => '',
			    'phones' => array('2122629853')
			),
			1878 => array(
			    'email'    => '',
			    'phones' => array('2126773368', '2129983931')
			),
			1880 => array(
			    'email'    => '',
			    'phones' => array('2127217633')
			),
			1887 => array(
			    'email'    => '',
			    'phones' => array('2126787883')
			),
			1888 => array(
			    'email'    => '',
			    'phones' => array('2129964121', '2128867330')
			),
			1889 => array(
			    'email'    => '',
			    'phones' => array('2125295902')
			),
			1890 => array(
			    'email'    => '',
			    'phones' => array('7188539830')
			),
			1891 => array(
			    'email'    => '',
			    'phones' => array('9178067054', '2123665757')
			),
			1899 => array(
			    'email'    => '',
			    'phones' => array('2126753944', '2013523548')
			),
			1901 => array(
			    'email'    => '',
			    'phones' => array('2122553373')
			),
			1902 => array(
			    'email'    => '',
			    'phones' => array('2123348823', '2125747874')
			),
			1908 => array(
			    'email'    => '',
			    'phones' => array('2122975058')
			),
			1911 => array(
			    'email'    => '',
			    'phones' => array('2129321874')
			),
			1912 => array(
			    'email'    => '',
			    'phones' => array('2122131736')
			),
			1922 => array(
			    'email'    => '',
			    'phones' => array('2129822347')
			),
			1937 => array(
			    'email'    => '',
			    'phones' => array('2125818450')
			),
			1939 => array(
			    'email'    => '',
			    'phones' => array('2128732805', '2124550396')
			),
			1942 => array(
			    'email'    => '',
			    'phones' => array('2122547860')
			),
			1943 => array(
			    'email'    => '',
			    'phones' => array('2122547860')
			),
			1948 => array(
			    'email'    => '',
			    'phones' => array('2126773449')
			),
			1958 => array(
			    'email'    => '',
			    'phones' => array('2129828788')
			),
			1970 => array(
			    'email'    => '',
			    'phones' => array('7187940858')
			),
			2003 => array(
			    'email'    => '',
			    'phones' => array('2125820566')
			),
			2009 => array(
			    'email'    => '',
			    'phones' => array('2127240338')
			),
			2023 => array(
			    'email'    => '',
			    'phones' => array('2125175470')
			),
			2041 => array(
			    'email'    => '',
			    'phones' => array('2122602672', '2129638744')
			),
			2043 => array(
			    'email'    => '',
			    'phones' => array('9085750138')
			),
			2071 => array(
			    'email'    => '',
			    'phones' => array('2128616479', '2123395042')
			),
			2073 => array(
			    'email'    => '',
			    'phones' => array('2124605680')
			),
			2085 => array(
			    'email'    => '',
			    'phones' => array('2123075434')
			),
			2092 => array(
			    'email'    => '',
			    'phones' => array('2127776370')
			),
			2098 => array(
			    'email'    => '',
			    'phones' => array('7187981154')
			),
			2102 => array(
			    'email'    => '',
			    'phones' => array('9174939561', '2124272256')
			),
			2123 => array(
			    'email'    => '',
			    'phones' => array('2127325037', '9178853916')
			),
			2125 => array(
			    'email'    => '',
			    'phones' => array('2127537709')
			),
			2127 => array(
			    'email'    => '',
			    'phones' => array('2127493817')
			),
			2129 => array(
			    'email'    => '',
			    'phones' => array('2129882286')
			),
			2144 => array(
			    'email'    => '',
			    'phones' => array('2129890744')
			),
			2157 => array(
			    'email'    => '',
			    'phones' => array('2124963276')
			),
			2192 => array(
			    'email'    => '',
			    'phones' => array('2125292840')
			),
			2196 => array(
			    'email'    => '',
			    'phones' => array('2129809383')
			),
			2198 => array(
			    'email'    => '',
			    'phones' => array('2124721035', '2129028478')
			),
			2200 => array(
			    'email'    => '',
			    'phones' => array('2122289700')
			),
			2202 => array(
			    'email'    => '',
			    'phones' => array('2126478647')
			),
			2222 => array(
			    'email'    => '',
			    'phones' => array('2124735433')
			),
			2230 => array(
			    'email'    => '',
			    'phones' => array('2122190621')
			),
			2272 => array(
			    'email'    => '',
			    'phones' => array('2124609033')
			),
			2293 => array(
			    'email'    => '',
			    'phones' => array('7188883149')
			),
			2303 => array(
			    'email'    => '',
			    'phones' => array('2128740565')
			),
			2304 => array(
			    'email'    => '',
			    'phones' => array('2128750823')
			),
			2328 => array(
			    'email'    => '',
			    'phones' => array('2038570586')
			),
			2336 => array(
			    'email'    => '',
			    'phones' => array('2129799611')
			),
			2342 => array(
			    'email'    => '',
			    'phones' => array('2126777607')
			),
			2343 => array(
			    'email'    => '',
			    'phones' => array('2123344214')
			),
			2353 => array(
			    'email'    => '',
			    'phones' => array('2128387782')
			),
			2354 => array(
			    'email'    => '',
			    'phones' => array('2123716969')
			),
			2358 => array(
			    'email'    => '',
			    'phones' => array('2129881656')
			),
			2360 => array(
			    'email'    => '',
			    'phones' => array('2019460431', '2129986852')
			),
			2376 => array(
			    'email'    => '',
			    'phones' => array('2126448077', '2126061888')
			),
			2377 => array(
			    'email'    => '',
			    'phones' => array('2129245951', '2127505550')
			),
			2381 => array(
			    'email'    => '',
			    'phones' => array('2128612966')
			),
			2389 => array(
			    'email'    => '',
			    'phones' => array('2126752723', '9178818045')
			),
			2407 => array(
			    'email'    => '',
			    'phones' => array('2122600150')
			),
			2413 => array(
			    'email'    => '',
			    'phones' => array('7183211188', '5165073254')
			),
			2429 => array(
			    'email'    => '',
			    'phones' => array('2125338037', '2129657386')
			),
			2432 => array(
			    'email'    => '',
			    'phones' => array('2127520562')
			),
			2438 => array(
			    'email'    => '',
			    'phones' => array('2127220405', '6463191921')
			),
			2446 => array(
			    'email'    => '',
			    'phones' => array('2123601845')
			),
			2463 => array(
			    'email'    => '',
			    'phones' => array('2126796194')
			),
			2470 => array(
			    'email'    => '',
			    'phones' => array('2126624076')
			),
			2479 => array(
			    'email'    => '',
			    'phones' => array('2122636681', '2125948843')
			),
			2482 => array(
			    'email'    => '',
			    'phones' => array('2124861645')
			),
			2504 => array(
			    'email'    => '',
			    'phones' => array('2123076837')
			),
			2511 => array(
			    'email'    => '',
			    'phones' => array('2123964906')
			),
			2538 => array(
			    'email'    => '',
			    'phones' => array('2127211854')
			),
			2549 => array(
			    'email'    => '',
			    'phones' => array('7184431205')
			),
			2572 => array(
			    'email'    => '',
			    'phones' => array('2125050678')
			),
			2589 => array(
			    'email'    => '',
			    'phones' => array('2124962995')
			),
			2595 => array(
			    'email'    => '',
			    'phones' => array('2124861645')
			),
			2604 => array(
			    'email'    => '',
			    'phones' => array('2122545989', '2129746230')
			),
			2605 => array(
			    'email'    => '',
			    'phones' => array('2127342745')
			),
			2606 => array(
			    'email'    => '',
			    'phones' => array('2127342745')
			),
			2608 => array(
			    'email'    => '',
			    'phones' => array('2122556939')
			),
			2609 => array(
			    'email'    => '',
			    'phones' => array('2129795091')
			),
			2630 => array(
			    'email'    => '',
			    'phones' => array('2127502544', '6464224454')
			),
			2635 => array(
			    'email'    => '',
			    'phones' => array('2016533475')
			),
			2640 => array(
			    'email'    => '',
			    'phones' => array('2129795091')
			),
			2648 => array(
			    'email'    => '',
			    'phones' => array('2124861645')
			),
			2662 => array(
			    'email'    => '',
			    'phones' => array('2126738348')
			),
			2677 => array(
			    'email'    => '',
			    'phones' => array('2126282553')
			),
			2679 => array(
			    'email'    => '',
			    'phones' => array('9733794914', '2012174407')
			),
			2681 => array(
			    'email'    => '',
			    'phones' => array('2129795177', '6462524628')
			),
			2686 => array(
			    'email'    => '',
			    'phones' => array('2126633848')
			),
			2690 => array(
			    'email'    => '',
			    'phones' => array('2126626075')
			),
			2691 => array(
			    'email'    => '',
			    'phones' => array('9082768693', '9739715285')
			),
			2693 => array(
			    'email'    => '',
			    'phones' => array('2126478647')
			),
			2702 => array(
			    'email'    => '',
			    'phones' => array('2126148801')
			),
			2703 => array(
			    'email'    => '',
			    'phones' => array('2014188698')
			),
			2729 => array(
			    'email'    => '',
			    'phones' => array('2127279350', '9174493214')
			),
			2759 => array(
			    'email'    => '',
			    'phones' => array('2124861645')
			),
			2768 => array(
			    'email'    => '',
			    'phones' => array('2126611461', '9175450913')
			),
			2790 => array(
			    'email'    => '',
			    'phones' => array('2125297175')
			),
			2803 => array(
			    'email'    => '',
			    'phones' => array('2127170363', '2127024103')
			),
			2818 => array(
			    'email'    => '',
			    'phones' => array('2124861645')
			),
			2833 => array(
			    'email'    => '',
			    'phones' => array('2122749994', '7186090352')
			),
			2844 => array(
			    'email'    => '',
			    'phones' => array('2124961045', '9173282790')
			),
			2852 => array(
			    'email'    => '',
			    'phones' => array('2123870731', '2129980518')
			),
			2878 => array(
			    'email'    => '',
			    'phones' => array('2129958377', '2125487439')
			),
			2888 => array(
			    'email'    => '',
			    'phones' => array('2125853680', '2124868100')
			),
			2916 => array(
			    'email'    => '',
			    'phones' => array('2128386501')
			),
			2922 => array(
			    'email'    => '',
			    'phones' => array('2122749994', '9178462318')
			),
			2923 => array(
			    'email'    => '',
			    'phones' => array('2122749994', '9178462318')
			),
			2925 => array(
			    'email'    => '',
			    'phones' => array('2125290419', '2129950920')
			),
			2929 => array(
			    'email'    => '',
			    'phones' => array('2124313626')
			),
			2932 => array(
			    'email'    => '',
			    'phones' => array('2126832665')
			),
			2953 => array(
			    'email'    => '',
			    'phones' => array('2124816751')
			),
			4683 => array(
			    'email'    => '',
			    'phones' => array('2128650501', '9173275783')
			),
			7598 => array(
			    'email'    => '',
			    'phones' => array('2122749450', '2126912900')
			),
			9116 => array(
			    'email'    => '',
			    'phones' => array('2129987806')
			),
			10065 => array(
			    'email'    => '',
			    'phones' => array('2122605390')
			),
			10070 => array(
			    'email'    => '',
			    'phones' => array('2128076525')
			),
			10082 => array(
			    'email'    => '',
			    'phones' => array('6462157973')
			),
			10618 => array(
			    'email'    => '',
			    'phones' => array('6463365761', '2123379220')
			),
			11181 => array(
			    'email'    => '',
			    'phones' => array('7187452276')
			),
			11222 => array(
			    'email'    => '',
			    'phones' => array('7186995055', '2125627403')
			),
			11315 => array(
			    'email'    => '',
			    'phones' => array('2126634987')
			),
			11500 => array(
			    'email'    => '',
			    'phones' => array('2123532778', '2129251656')
			),
			11811 => array(
			    'email'    => '',
			    'phones' => array('2017144620', '2013208785')
			),
			11855 => array(
			    'email'    => '',
			    'phones' => array('2122545119')
			),
			11899 => array(
			    'email'    => '',
			    'phones' => array('2122134714')
			),
			12012 => array(
			    'email'    => '',
			    'phones' => array('2126632526')
			),
			12118 => array(
			    'email'    => '',
			    'phones' => array('7188553325')
			),
			12119 => array(
			    'email'    => '',
			    'phones' => array('2125298065')
			),
			12122 => array(
			    'email'    => '',
			    'phones' => array('2129860981')
			),
			12124 => array(
			    'email'    => '',
			    'phones' => array('2126620660', '2125984400')
			),
			12137 => array(
			    'email'    => '',
			    'phones' => array('2122556958')
			),
			12141 => array(
			    'email'    => '',
			    'phones' => array('9175538188', '2122262307')
			),
			12148 => array(
			    'email'    => '',
			    'phones' => array('2124605003', '2127881585')
			),
			12152 => array(
			    'email'    => '',
			    'phones' => array('2122551799')
			),
			12154 => array(
			    'email'    => '',
			    'phones' => array('2126638912')
			),
			12156 => array(
			    'email'    => '',
			    'phones' => array('7184325667', '6464736445')
			),
			12161 => array(
			    'email'    => '',
			    'phones' => array('2122752460')
			),
			12162 => array(
			    'email'    => '',
			    'phones' => array('2122752460')
			),
			12166 => array(
			    'email'    => '',
			    'phones' => array('7183029778')
			),
			12179 => array(
			    'email'    => '',
			    'phones' => array('9176733214')
			),
			12180 => array(
			    'email'    => '',
			    'phones' => array('9176733214')
			),
			12182 => array(
			    'email'    => '',
			    'phones' => array('2125702365')
			),
			12183 => array(
			    'email'    => '',
			    'phones' => array('9178817872')
			),
			12487 => array(
			    'email'    => '',
			    'phones' => array('8458313778', '2129987806')
			),
			12854 => array(
			    'email'    => '',
			    'phones' => array('2012328018')
			),
			12994 => array(
			    'email'    => '',
			    'phones' => array('2123554365')
			),
			13027 => array(
			    'email'    => '',
			    'phones' => array('2128745142', '2125149454')
			),
			13029 => array(
			    'email'    => '',
			    'phones' => array('9737634744', '2122283889')
			),
			13058 => array(
			    'email'    => '',
			    'phones' => array('2128655301')
			),
			13190 => array(
			    'email'    => '',
			    'phones' => array('7187898744')
			),
			13247 => array(
			    'email'    => '',
			    'phones' => array('2123992305')
			),
			13253 => array(
			    'email'    => '',
			    'phones' => array('2129869650', '2124712990')
			),
			13255 => array(
			    'email'    => '',
			    'phones' => array('2125851293')
			),
			13256 => array(
			    'email'    => '',
			    'phones' => array('2123431541')
			),
			13257 => array(
			    'email'    => '',
			    'phones' => array('7188553446')
			),
			13264 => array(
			    'email'    => '',
			    'phones' => array('2122074979')
			),
			13267 => array(
			    'email'    => '',
			    'phones' => array('2124316262', '2127136901')
			),
			13273 => array(
			    'email'    => '',
			    'phones' => array('2125339786', '6462638160')
			),
			13274 => array(
			    'email'    => '',
			    'phones' => array('2128790062')
			),
			13276 => array(
			    'email'    => '',
			    'phones' => array('7183993695')
			),
			13277 => array(
			    'email'    => '',
			    'phones' => array('7186470982', '2125637054')
			),
			13286 => array(
			    'email'    => '',
			    'phones' => array('2017921141')
			),
			13288 => array(
			    'email'    => '',
			    'phones' => array('2124816006')
			),
			13289 => array(
			    'email'    => '',
			    'phones' => array('2128773292')
			),
			13290 => array(
			    'email'    => '',
			    'phones' => array('2014207882')
			),
			13291 => array(
			    'email'    => '',
			    'phones' => array('2016560019')
			),
			13294 => array(
			    'email'    => '',
			    'phones' => array('2126631823')
			),
			13296 => array(
			    'email'    => '',
			    'phones' => array('7187887159')
			),
			13298 => array(
			    'email'    => '',
			    'phones' => array('2122491144')
			),
			13299 => array(
			    'email'    => '',
			    'phones' => array('2122491144')
			),
			13300 => array(
			    'email'    => '',
			    'phones' => array('2122491144')
			),
			13303 => array(
			    'email'    => '',
			    'phones' => array('7183848438')
			),
			13305 => array(
			    'email'    => '',
			    'phones' => array('7186339410')
			),
			13306 => array(
			    'email'    => '',
			    'phones' => array('2124725994')
			),
			13307 => array(
			    'email'    => '',
			    'phones' => array('7187898744')
			),
			13308 => array(
			    'email'    => '',
			    'phones' => array('7187898744')
			),
			13309 => array(
			    'email'    => '',
			    'phones' => array('2125955692')
			),
			13312 => array(
			    'email'    => '',
			    'phones' => array('2124522448')
			),
			13314 => array(
			    'email'    => '',
			    'phones' => array('2122637963')
			),
			13315 => array(
			    'email'    => '',
			    'phones' => array('7186337473')
			),
			13316 => array(
			    'email'    => '',
			    'phones' => array('6463192929')
			),
			13318 => array(
			    'email'    => '',
			    'phones' => array('7182482235')
			),
			13319 => array(
			    'email'    => '',
			    'phones' => array('2123553667')
			),
			13321 => array(
			    'email'    => '',
			    'phones' => array('6464729630')
			),
			13482 => array(
			    'email'    => '',
			    'phones' => array('2124773161', '3716477985')
			),
			14283 => array(
			    'email'    => '',
			    'phones' => array('7183986616')
			),
			14284 => array(
			    'email'    => '',
			    'phones' => array('7185961793')
			),
			14290 => array(
			    'email'    => '',
			    'phones' => array('2126811217', '2129634388')
			),
			14291 => array(
			    'email'    => '',
			    'phones' => array('7187831920')
			),
			14292 => array(
			    'email'    => '',
			    'phones' => array('7182563614')
			),
			14293 => array(
			    'email'    => '',
			    'phones' => array('2124062084')
			),
			14294 => array(
			    'email'    => '',
			    'phones' => array('2128741769')
			),
			14295 => array(
			    'email'    => '',
			    'phones' => array('2126755124')
			),
			14296 => array(
			    'email'    => '',
			    'phones' => array('2127641427')
			),
			14297 => array(
			    'email'    => '',
			    'phones' => array('2012391444')
			),
			14299 => array(
			    'email'    => '',
			    'phones' => array('7182309268', '6462503855')
			),
			14302 => array(
			    'email'    => '',
			    'phones' => array('2124757182')
			),
			14303 => array(
			    'email'    => '',
			    'phones' => array('6464235707')
			),
			14304 => array(
			    'email'    => '',
			    'phones' => array('7184924055')
			),
			14306 => array(
			    'email'    => '',
			    'phones' => array('7184924055')
			),
			14307 => array(
			    'email'    => '',
			    'phones' => array('2126942448', '6177840296')
			),
			14309 => array(
			    'email'    => '',
			    'phones' => array('2129825905')
			),
			14310 => array(
			    'email'    => '',
			    'phones' => array('2016597453')
			),
			14312 => array(
			    'email'    => '',
			    'phones' => array('9089289067')
			),
			14314 => array(
			    'email'    => '',
			    'phones' => array('2017959470', '2076747038')
			),
			14565 => array(
			    'email'    => '',
			    'phones' => array('2129350757', '7188775048')
			),
			15854 => array(
			    'email'    => '',
			    'phones' => array('6464725063', '2124595398')
			),
			15869 => array(
			    'email'    => '',
			    'phones' => array('2125341950', '2128286000')
			),
			16041 => array(
			    'email'    => '',
			    'phones' => array('7189632570')
			),
			16266 => array(
			    'email'    => '',
			    'phones' => array('7188553446')
			),
			16269 => array(
			    'email'    => '',
			    'phones' => array('7186236520')
			),
			16270 => array(
			    'email'    => '',
			    'phones' => array('7186236520')
			),
			16279 => array(
			    'email'    => '',
			    'phones' => array('2123628284')
			),
			16281 => array(
			    'email'    => '',
			    'phones' => array('2124595398')
			),
			16282 => array(
			    'email'    => '',
			    'phones' => array('2126282293')
			),
			16284 => array(
			    'email'    => '',
			    'phones' => array('8608228813')
			),
			16286 => array(
			    'email'    => '',
			    'phones' => array('7188514292')
			),
			16288 => array(
			    'email'    => '',
			    'phones' => array('2128629308')
			),
			16289 => array(
			    'email'    => '',
			    'phones' => array('2129574414')
			),
			16290 => array(
			    'email'    => '',
			    'phones' => array('2128744871')
			),
			16301 => array(
			    'email'    => '',
			    'phones' => array('7184390960', '9176029124')
			),
			16302 => array(
			    'email'    => '',
			    'phones' => array('7186332885')
			),
			16303 => array(
			    'email'    => '',
			    'phones' => array('7184385920')
			),
			16304 => array(
			    'email'    => '',
			    'phones' => array('2014201299')
			),
			16306 => array(
			    'email'    => '',
			    'phones' => array('2124319132')
			),
			16308 => array(
			    'email'    => '',
			    'phones' => array('2129627098', '2123340717')
			),
			16309 => array(
			    'email'    => '',
			    'phones' => array('2122492440')
			),
			16310 => array(
			    'email'    => '',
			    'phones' => array('2126911505', '6462471907')
			),
			16311 => array(
			    'email'    => '',
			    'phones' => array('7186219722')
			),
			16314 => array(
			    'email'    => '',
			    'phones' => array('7182955362')
			),
			17844 => array(
			    'email'    => '',
			    'phones' => array('9149663538', '7184096977', '9174498349')
			),
			18292 => array(
			    'email'    => '',
			    'phones' => array('6464725063', '2124595398')
			),
			18339 => array(
			    'email'    => '',
			    'phones' => array('2122559206')
			),
			18340 => array(
			    'email'    => '',
			    'phones' => array('2124639301')
			),
			18345 => array(
			    'email'    => '',
			    'phones' => array('2128076792')
			),
			18346 => array(
			    'email'    => '',
			    'phones' => array('2123889362', '6465220089')
			),
			18349 => array(
			    'email'    => '',
			    'phones' => array('9175191993')
			),
			18359 => array(
			    'email'    => '',
			    'phones' => array('9176587676')
			),
			18362 => array(
			    'email'    => '',
			    'phones' => array('2127741806')
			),
			18365 => array(
			    'email'    => '',
			    'phones' => array('7183836781', '7184585059')
			),
			18367 => array(
			    'email'    => '',
			    'phones' => array('2122430012')
			),
			18368 => array(
			    'email'    => '',
			    'phones' => array('2017144620', '2012391137')
			),
			18374 => array(
			    'email'    => '',
			    'phones' => array('2124727352', '2125597377')
			),
			18375 => array(
			    'email'    => '',
			    'phones' => array('2124201544', '9178645050')
			),
			18376 => array(
			    'email'    => 'susan@fennessey.com',
			    'phones' => array('2128765783', '2128487878')
			),
			18387 => array(
			    'email'    => '',
			    'phones' => array('2123629775', '2125005825')
			),
			18388 => array(
			    'email'    => '',
			    'phones' => array('2122600888')
			),
			18392 => array(
			    'email'    => '',
			    'phones' => array('2124777374', '6467322743')
			),
			18393 => array(
			    'email'    => '',
			    'phones' => array('2126142870')
			),
			18394 => array(
			    'email'    => '',
			    'phones' => array('2127448907', '2127461535')
			),
			18395 => array(
			    'email'    => '',
			    'phones' => array('2125172950')
			),
			18397 => array(
			    'email'    => '',
			    'phones' => array('2125829501')
			),
			18398 => array(
			    'email'    => '',
			    'phones' => array('2124219205', '9174531983')
			),
			18399 => array(
			    'email'    => '',
			    'phones' => array('2122607671')
			),
			18400 => array(
			    'email'    => '',
			    'phones' => array('2126888215', '2125602092')
			),
			18406 => array(
			    'email'    => '',
			    'phones' => array('2124968990', '2122405339')
			),
			18736 => array(
			    'email'    => '',
			    'phones' => array('9176537962', '2126297993', '9178067460')
			),
			18802 => array(
			    'email'    => '',
			    'phones' => array('2122869302')
			),
			20282 => array(
			    'email'    => '',
			    'phones' => array('2129899402')
			),
			20300 => array(
			    'email'    => '',
			    'phones' => array('2122434593')
			),
			20303 => array(
			    'email'    => '',
			    'phones' => array('2122547276')
			),
			20304 => array(
			    'email'    => '',
			    'phones' => array('2122068210')
			),
			20311 => array(
			    'email'    => '',
			    'phones' => array('2123758722')
			),
			20313 => array(
			    'email'    => '',
			    'phones' => array('7187420326')
			),
			20318 => array(
			    'email'    => '',
			    'phones' => array('2017144620')
			),
			20320 => array(
			    'email'    => '',
			    'phones' => array('2125853119')
			),
			20331 => array(
			    'email'    => '',
			    'phones' => array('2126271418')
			),
			20337 => array(
			    'email'    => '',
			    'phones' => array('7188366011')
			),
			20338 => array(
			    'email'    => '',
			    'phones' => array('2125635585')
			),
			20342 => array(
			    'email'    => '',
			    'phones' => array('2127800242')
			),
			20348 => array(
			    'email'    => '',
			    'phones' => array('2128285358')
			),
			20359 => array(
			    'email'    => '',
			    'phones' => array('7186265683')
			),
			20360 => array(
			    'email'    => '',
			    'phones' => array('6466729699')
			),
			20365 => array(
			    'email'    => '',
			    'phones' => array('2122536106')
			),
			20366 => array(
			    'email'    => '',
			    'phones' => array('7182204199', '2122394477')
			),
			20368 => array(
			    'email'    => '',
			    'phones' => array('9179748396')
			),
			20370 => array(
			    'email'    => '',
			    'phones' => array('6462704028')
			),
			20371 => array(
			    'email'    => '',
			    'phones' => array('7187070388')
			),
			20377 => array(
			    'email'    => '',
			    'phones' => array('2129611778')
			),
			20391 => array(
			    'email'    => '',
			    'phones' => array('7184924359')
			),
			20398 => array(
			    'email'    => '',
			    'phones' => array('7326716979')
			),
			20399 => array(
			    'email'    => '',
			    'phones' => array('7326716979')
			),
			20403 => array(
			    'email'    => '',
			    'phones' => array('2016535365')
			),
			20404 => array(
			    'email'    => '',
			    'phones' => array('2016535365')
			),
			20408 => array(
			    'email'    => '',
			    'phones' => array('7185677914')
			),
			20410 => array(
			    'email'    => '',
			    'phones' => array('2127325400')
			),
			20412 => array(
			    'email'    => '',
			    'phones' => array('2125344401')
			),
			20414 => array(
			    'email'    => '',
			    'phones' => array('2014591852')
			),
			20417 => array(
			    'email'    => '',
			    'phones' => array('9174497939')
			),
			20423 => array(
			    'email'    => '',
			    'phones' => array('2122869384')
			),
			20434 => array(
			    'email'    => '',
			    'phones' => array('7186347439')
			),
			20445 => array(
			    'email'    => '',
			    'phones' => array('7186583492')
			),
			20446 => array(
			    'email'    => '',
			    'phones' => array('2124777374')
			),
			20447 => array(
			    'email'    => '',
			    'phones' => array('2124777374')
			),
			20450 => array(
			    'email'    => 'reneeparli@yahoo.com',
			    'phones' => array('2122531673', '9179218832')
			),
			20453 => array(
			    'email'    => '',
			    'phones' => array('2122490918')
			),
			20455 => array(
			    'email'    => '',
			    'phones' => array('2124753083')
			),
			20459 => array(
			    'email'    => '',
			    'phones' => array('2125689853')
			),
			20461 => array(
			    'email'    => '',
			    'phones' => array('2124732507')
			),
			20469 => array(
			    'email'    => '',
			    'phones' => array('7187033674')
			),
			20470 => array(
			    'email'    => '',
			    'phones' => array('2127336204')
			),
			20472 => array(
			    'email'    => '',
			    'phones' => array('2129821036')
			),
			20473 => array(
			    'email'    => '',
			    'phones' => array('2129796694')
			),
			21648 => array(
			    'email'    => '',
			    'phones' => array('6462158299')
			),
			21649 => array(
			    'email'    => '',
			    'phones' => array('2127372784')
			),
			21650 => array(
			    'email'    => '',
			    'phones' => array('2126635923')
			),
			21651 => array(
			    'email'    => '',
			    'phones' => array('2128310925')
			),
			21654 => array(
			    'email'    => '',
			    'phones' => array('7188348709')
			),
			21657 => array(
			    'email'    => '',
			    'phones' => array('2126142870')
			),
			21658 => array(
			    'email'    => '',
			    'phones' => array('2126142870')
			),
			21659 => array(
			    'email'    => '',
			    'phones' => array('2126274451')
			),
			21660 => array(
			    'email'    => '',
			    'phones' => array('2125806281')
			),
			21661 => array(
			    'email'    => '',
			    'phones' => array('2128282580')
			),
			21663 => array(
			    'email'    => 'fpreilly@yahoo.com',
			    'phones' => array('2123344034', '9176704634')
			),
			21706 => array(
			    'email'    => 'lanasema@att.net',
			    'phones' => array('2129453383', '9175537170')
			),
			21708 => array(
			    'email'    => '',
			    'phones' => array('7185226972')
			),
			21711 => array(
			    'email'    => '',
			    'phones' => array('9176933889')
			),
			21679 => array(
			    'email'    => '',
			    'phones' => array('2128287890')
			),
			21680 => array(
			    'email'    => '',
			    'phones' => array('2128287890')
			),
			21718 => array(
			    'email'    => '',
			    'phones' => array('9176996323')
			),
			21723 => array(
			    'email'    => '',
			    'phones' => array('2128653095')
			),
			24152 => array(
			    'email'    => '',
			    'phones' => array('2125313999')
			),
			24153 => array(
			    'email'    => '',
			    'phones' => array('7184093436')
			),
			24155 => array(
			    'email'    => '',
			    'phones' => array('7188963957')
			),
			24177 => array(
			    'email'    => '',
			    'phones' => array('2123701579')
			),
			24179 => array(
			    'email'    => '',
			    'phones' => array('7187596301')
			),
			24180 => array(
			    'email'    => '',
			    'phones' => array('7187596301')
			),
			22728 => array(
			    'email'    => '',
			    'phones' => array('2127177358')
			),
			24158 => array(
			    'email'    => '',
			    'phones' => array('2126140492')
			),
			24167 => array(
			    'email'    => '',
			    'phones' => array('2123531445')
			),
			22740 => array(
			    'email'    => '',
			    'phones' => array('7182786524')
			),
			21728 => array(
			    'email'    => '',
			    'phones' => array('9175920422')
			),
			25563 => array(
			    'email'    => '',
			    'phones' => array('7184828593')
			),
			25567 => array(
			    'email'    => '',
			    'phones' => array('7188583866')
			),
			25568 => array(
			    'email'    => '',
			    'phones' => array('2129570368')
			),
			25635 => array(
			    'email'    => 'mahsapelosky@hotmail.com',
			    'phones' => array('2127800242')
			),
			25637 => array(
			    'email'    => 'sandra430@juno.com',
			    'phones' => array('2126148093')
			),
			25651 => array(
			    'email'    => '',
			    'phones' => array('2126790682')
			),
			25654 => array(
			    'email'    => 'k@mechner.com',
			    'phones' => array('2125980668', '6463543335')
			),
			25663 => array(
			    'email'    => '',
			    'phones' => array('2126140324')
			),
			25619 => array(
			    'email'    => '',
			    'phones' => array('2127490674')
			),
			25657 => array(
			    'email'    => 'brogan.ganley@mac.com',
			    'phones' => array()
			),
			21863 => array(
			    'email'    => '',
			    'phones' => array('2129950487')
			),
			23857 => array(
			    'email'    => '',
			    'phones' => array('7184910169')
			),
			25676 => array(
			    'email'    => '',
			    'phones' => array('2123572571')
			),
			25685 => array(
			    'email'    => 'suroisin@yahoo.com',
			    'phones' => array('2122602302')
			),
			26979 => array(
			    'email'    => '',
			    'phones' => array('2126478647')
			),
			26982 => array(
			    'email'    => '',
			    'phones' => array('7188719081')
			),
			25622 => array(
			    'email'    => '',
			    'phones' => array('9176815669')
			),
			25629 => array(
			    'email'    => '',
			    'phones' => array('6463254031')
			),
			26999 => array(
			    'email'    => '',
			    'phones' => array('9177166997')
			),
			27015 => array(
			    'email'    => '',
			    'phones' => array('2125451806')
			),
			27025 => array(
			    'email'    => 'anrod01@hotmail.com',
			    'phones' => array('2127321835')
			),
			25569 => array(
			    'email'    => 'Adele@BFGFProductions.com',
			    'phones' => array('9175794780', '2129789894')
			),
			25574 => array(
			    'email'    => 'cheike@nyc.rr.com',
			    'phones' => array('2123621320')
			),
			25643 => array(
			    'email'    => '',
			    'phones' => array('7184997832')
			),
			27013 => array(
			    'email'    => '',
			    'phones' => array('7186099250')
			),
			22763 => array(
			    'email'    => '',
			    'phones' => array('7186862930')
			),
			22764 => array(
			    'email'    => '',
			    'phones' => array('2124770167')
			),
			27031 => array(
			    'email'    => '',
			    'phones' => array('3472937136')
			),
			27035 => array(
			    'email'    => '',
			    'phones' => array('2126337822')
			),
			27036 => array(
			    'email'    => '',
			    'phones' => array('2123666168')
			),
			29466 => array(
			    'email'    => '',
			    'phones' => array('7184869477')
			),
			22742 => array(
			    'email'    => '',
			    'phones' => array('2126940046')
			),
			22743 => array(
			    'email'    => '',
			    'phones' => array('2126940046')
			),
			22744 => array(
			    'email'    => '',
			    'phones' => array('2126940046')
			),
			25578 => array(
			    'email'    => '',
			    'phones' => array('2125354488')
			),
			25644 => array(
			    'email'    => '',
			    'phones' => array('2129959745')
			),
			27046 => array(
			    'email'    => '',
			    'phones' => array('7185291992')
			),
			22484 => array(
			    'email'    => 'Willhart3@hotmail.com',
			    'phones' => array('9177315849')
			),
			22748 => array(
			    'email'    => '',
			    'phones' => array('7182367938')
			),
			22749 => array(
			    'email'    => '',
			    'phones' => array('2128657318')
			),
			27869 => array(
			    'email'    => 'babylovek1227@yahoo.com',
			    'phones' => array('7183929948')
			),
			27870 => array(
			    'email'    => 'babylovek1227@yahoo.com',
			    'phones' => array('7183929948')
			),
			27873 => array(
			    'email'    => '',
			    'phones' => array('7182734124')
			),
			27874 => array(
			    'email'    => '',
			    'phones' => array('2125790575')
			),
			27877 => array(
			    'email'    => 'c_libling@yahoo.com',
			    'phones' => array('9174413577')
			),
			27882 => array(
			    'email'    => 'lindsayandchris@aol.com',
			    'phones' => array('7188341814')
			),
			25595 => array(
			    'email'    => 'tebatan918@aol.com',
			    'phones' => array('7184467376', '9173328702')
			),
			27885 => array(
			    'email'    => '',
			    'phones' => array('7189729266')
			),
			27886 => array(
			    'email'    => 'lana_star27@yahoo.com',
			    'phones' => array('2124915066')
			),
			38207 => array(
			    'email'    => 'rnewman504@nyc.rr.com',
			    'phones' => array('2126657140', '9176086501')
			),
			27728 => array(
			    'email'    => 'berryarc@aol.com',
			    'phones' => array('2127401633')
			),
			27893 => array(
			    'email'    => 'michelemax@mac.com',
			    'phones' => array('2122601336')
			),
			27677 => array(
			    'email'    => '',
			    'phones' => array('6463391745')
			),
			27895 => array(
			    'email'    => '',
			    'phones' => array('2122342835')
			),
			27896 => array(
			    'email'    => '',
			    'phones' => array('6467852980')
			),
			27898 => array(
			    'email'    => '',
			    'phones' => array('9178596316')
			),
			27899 => array(
			    'email'    => '',
			    'phones' => array('2017984267')
			),
			27900 => array(
			    'email'    => '',
			    'phones' => array('2017984267')
			),
			27912 => array(
			    'email'    => 'thegoods@mac.com',
			    'phones' => array('2017950662', '2013390662', '2014062310')
			),
			27919 => array(
			    'email'    => 'melissa.robbins@nyu.edu',
			    'phones' => array('2126140223')
			),
			27925 => array(
			    'email'    => 'ooyosyoo@aol.com',
			    'phones' => array('2129959066')
			),
			27940 => array(
			    'email'    => '',
			    'phones' => array('2127957891')
			),
			27943 => array(
			    'email'    => '',
			    'phones' => array('7184227634')
			),
			27945 => array(
			    'email'    => '',
			    'phones' => array('2124063290')
			),
			21927 => array(
			    'email'    => '',
			    'phones' => array('2124730025', '9179039897')
			),
			30311 => array(
			    'email'    => '',
			    'phones' => array('7182582793')
			),
			30314 => array(
			    'email'    => '',
			    'phones' => array('7186860764')
			),
			30349 => array(
			    'email'    => '',
			    'phones' => array('7186010840')
			),
			33093 => array(
			    'email'    => '',
			    'phones' => array('7182582793')
			),
			33094 => array(
			    'email'    => '',
			    'phones' => array('7182582793')
			),
			30368 => array(
			    'email'    => 'ogomez777@aol.com',
			    'phones' => array('7185018152')
			),
			31736 => array(
			    'email'    => '',
			    'phones' => array('7183874482')
			),
			25608 => array(
			    'email'    => 'Erica.German@mssm.edu',
			    'phones' => array('2128286493')
			),
			27935 => array(
			    'email'    => '',
			    'phones' => array('2126773368')
			),
			27936 => array(
			    'email'    => '',
			    'phones' => array('2126773368')
			),
			27938 => array(
			    'email'    => 'gbeissel@hotmail.com',
			    'phones' => array('2124754136')
			),
			29407 => array(
			    'email'    => '',
			    'phones' => array('7184593007')
			),
			29408 => array(
			    'email'    => 'mmilberg@nyc.rr.com',
			    'phones' => array('2127210340')
			),
			29419 => array(
			    'email'    => '',
			    'phones' => array('7186213457')
			),
			29420 => array(
			    'email'    => '',
			    'phones' => array('7186213457')
			),
			34969 => array(
			    'email'    => '',
			    'phones' => array('2012174407')
			),
			34970 => array(
			    'email'    => '',
			    'phones' => array('2012174407')
			),
			29422 => array(
			    'email'    => 'npackman@hotmail.com',
			    'phones' => array('2127068888', '2017806363')
			),
			29425 => array(
			    'email'    => '',
			    'phones' => array('9173198992')
			),
			29449 => array(
			    'email'    => '',
			    'phones' => array('2123489894')
			),
			29475 => array(
			    'email'    => 'maria2chiang@nyc.rr.com',
			    'phones' => array('2126337822')
			),
			29476 => array(
			    'email'    => '',
			    'phones' => array('2126337822')
			),
			29440 => array(
			    'email'    => '',
			    'phones' => array('7182395567')
			),
			29467 => array(
			    'email'    => 'Theresatdc1011@aol.com',
			    'phones' => array('7185744895')
			),
			29468 => array(
			    'email'    => '',
			    'phones' => array('7183025765')
			),
			29471 => array(
			    'email'    => '',
			    'phones' => array('2126081655')
			),
			29480 => array(
			    'email'    => '',
			    'phones' => array('7188325588')
			),
			29488 => array(
			    'email'    => '',
			    'phones' => array('2128655301')
			),
			33095 => array(
			    'email'    => 'zimbarb@nyc.rr.com',
			    'phones' => array('7184593007')
			),
			33096 => array(
			    'email'    => '',
			    'phones' => array('7184593007')
			),
			31788 => array(
			    'email'    => 'Lizpicante17@yahoo.com',
			    'phones' => array('2128138298')
			),
			31790 => array(
			    'email'    => '',
			    'phones' => array('2122227301', '6462367919')
			),
			31793 => array(
			    'email'    => 'deonne70@msn.com',
			    'phones' => array('7187895135')
			),
			31802 => array(
			    'email'    => '',
			    'phones' => array('7186657539')
			),
			31803 => array(
			    'email'    => '',
			    'phones' => array('7186657539')
			),
			31804 => array(
			    'email'    => '',
			    'phones' => array('7185742220')
			),
			33104 => array(
			    'email'    => 'ibaigor@yahoo.com',
			    'phones' => array('9176730187')
			),
			33105 => array(
			    'email'    => '',
			    'phones' => array('9176730187')
			),
			34197 => array(
			    'email'    => '',
			    'phones' => array('2127402323')
			),
			34205 => array(
			    'email'    => '',
			    'phones' => array('7182685885')
			),
			34206 => array(
			    'email'    => '',
			    'phones' => array('7188976578')
			),
			24142 => array(
			    'email'    => '',
			    'phones' => array('2127250338')
			),
			24144 => array(
			    'email'    => 'lauraiverson@yahoo.com',
			    'phones' => array('2125809797')
			),
			22859 => array(
			    'email'    => '',
			    'phones' => array('2126140492')
			),
			24147 => array(
			    'email'    => 'brooklyndave2001@aol.com',
			    'phones' => array('7189720967')
			),
			30331 => array(
			    'email'    => 'SYIU@YAHOO.COM',
			    'phones' => array('7186612354')
			),
			30359 => array(
			    'email'    => 'jcepeda@grandstreet.org',
			    'phones' => array('9175290876')
			),
			30360 => array(
			    'email'    => '',
			    'phones' => array('9175290876')
			),
			31733 => array(
			    'email'    => 'Glitterguurl1102@aol.com',
			    'phones' => array('7182670540')
			),
			31739 => array(
			    'email'    => 'soniasotovera@yahoo.es',
			    'phones' => array('2129962196', '9175763875')
			),
			31740 => array(
			    'email'    => '',
			    'phones' => array('2129962196')
			),
			31742 => array(
			    'email'    => '',
			    'phones' => array('6462286422')
			),
			31753 => array(
			    'email'    => 'hekemian@vzavenue.net',
			    'phones' => array('2123664236')
			),
			31766 => array(
			    'email'    => '',
			    'phones' => array('6467322743')
			),
			31773 => array(
			    'email'    => 'anrod01@hotmail.com',
			    'phones' => array('2127321835')
			),
			23275 => array(
			    'email'    => '',
			    'phones' => array('2128281230')
			),
			24149 => array(
			    'email'    => '',
			    'phones' => array('2126660075')
			),
			23217 => array(
			    'email'    => '',
			    'phones' => array('7184667501')
			),
			31743 => array(
			    'email'    => '',
			    'phones' => array('2126746620')
			),
			26065 => array(
			    'email'    => 'seaglasslover@aol.com',
			    'phones' => array('9175191993')
			),
			31744 => array(
			    'email'    => '',
			    'phones' => array('2125310814')
			),
			31765 => array(
			    'email'    => '',
			    'phones' => array('6467322743')
			),
			31242 => array(
			    'email'    => 'SSanchez598@aol.com',
			    'phones' => array('7187894469', '7189410320')
			),
			34190 => array(
			    'email'    => '',
			    'phones' => array('2123807878')
			),
			33133 => array(
			    'email'    => '',
			    'phones' => array('7185885966')
			),
			34259 => array(
			    'email'    => '',
			    'phones' => array('2122539189')
			),
			34260 => array(
			    'email'    => '',
			    'phones' => array('7184725428')
			),
			34959 => array(
			    'email'    => '',
			    'phones' => array('2124752451')
			),
			34973 => array(
			    'email'    => '',
			    'phones' => array('9175863571')
			),
			33142 => array(
			    'email'    => '',
			    'phones' => array('2127699418')
			),
			34216 => array(
			    'email'    => '',
			    'phones' => array('7182461928')
			),
			34218 => array(
			    'email'    => '',
			    'phones' => array('7187830289')
			),
			34262 => array(
			    'email'    => '',
			    'phones' => array('2124776534')
			),
			34964 => array(
			    'email'    => '',
			    'phones' => array('9146294534')
			),
			34966 => array(
			    'email'    => '',
			    'phones' => array('7185881671')
			),
			34967 => array(
			    'email'    => '',
			    'phones' => array('7185881671')
			),
			34670 => array(
			    'email'    => 'rupalk71@yahoo.com',
			    'phones' => array('7189630690')
			),
			34987 => array(
			    'email'    => '',
			    'phones' => array('7188888888')
			),
			34994 => array(
			    'email'    => '',
			    'phones' => array('9179039897')
			),
			33148 => array(
			    'email'    => '',
			    'phones' => array('2124450075')
			),
			34954 => array(
			    'email'    => '',
			    'phones' => array('2129297650')
			),
			36129 => array(
			    'email'    => 'sunshyne0666@hotmail.com',
			    'phones' => array('7186937176', '6463026644')
			),
			35270 => array(
			    'email'    => 'gourves-fromigue@un.org',
			    'phones' => array('2124269290', '6466231662')
			),
			38231 => array(
			    'email'    => 'tsuyo-n@gf7.so-net.nejp',
			    'phones' => array('2125345915', '6466430713')
			),
			36483 => array(
			    'email'    => 'ninaecheverria@hotmail.com',
			    'phones' => array('7182395567')
			),
			42198 => array(
			    'email'    => '',
			    'phones' => array('9175663957')
			),
			42246 => array(
			    'email'    => '',
			    'phones' => array('7186099146')
			),
			42249 => array(
			    'email'    => '',
			    'phones' => array('9175826275')
			),
			42253 => array(
			    'email'    => '',
			    'phones' => array('3475686312')
			),
			42254 => array(
			    'email'    => '',
			    'phones' => array('3475686312')
			),
			38241 => array(
			    'email'    => '',
			    'phones' => array('2012174394')
			),
			38247 => array(
			    'email'    => '',
			    'phones' => array('7182377802')
			),
			33151 => array(
			    'email'    => '',
			    'phones' => array('2127658802')
			),
			34238 => array(
			    'email'    => '',
			    'phones' => array('7185062061')
			),
			38430 => array(
			    'email'    => '',
			    'phones' => array('9737446981')
			),
			26143 => array(
			    'email'    => 'sarashirsch7@yahoo.com',
			    'phones' => array('7183381267', '6469635778')
			),
			38477 => array(
			    'email'    => '',
			    'phones' => array('3475619411')
			),
			38495 => array(
			    'email'    => '',
			    'phones' => array('2016837955')
			),
			40448 => array(
			    'email'    => '',
			    'phones' => array('2126617873')
			),
			40457 => array(
			    'email'    => 'nycswiss@yahoo.com',
			    'phones' => array('2123879342')
			),
			40459 => array(
			    'email'    => '',
			    'phones' => array('9143204886')
			),
			40361 => array(
			    'email'    => '',
			    'phones' => array('6466495347')
			),
			40733 => array(
			    'email'    => '',
			    'phones' => array('7189512175')
			),
			41305 => array(
			    'email'    => '',
			    'phones' => array('7187717658')
			),
			41361 => array(
			    'email'    => 'herina.ayot@hotmail.com',
			    'phones' => array('2019842726')
			),
			41362 => array(
			    'email'    => '',
			    'phones' => array('2019842726')
			),
			41722 => array(
			    'email'    => 'monumentally@yahoo.com',
			    'phones' => array('9178225940')
			),
			32931 => array(
			    'email'    => 'rtbooks@nyc.rr.com',
			    'phones' => array('7188134845')
			),
			32025 => array(
			    'email'    => 'Rocio0830@yahoo.com',
			    'phones' => array('2122837938')
			),
			33160 => array(
			    'email'    => '',
			    'phones' => array('7184466043')
			),
			36480 => array(
			    'email'    => 'nottybeba69@yahoo.com',
			    'phones' => array('2014204817')
			),
			36532 => array(
			    'email'    => '',
			    'phones' => array('2122170789')
			),
			38252 => array(
			    'email'    => '',
			    'phones' => array('9147255234')
			),
			38253 => array(
			    'email'    => 'staylor@cims.nyu.edu',
			    'phones' => array('6467342010')
			),
			38257 => array(
			    'email'    => '',
			    'phones' => array('9175417019')
			),
			38504 => array(
			    'email'    => '',
			    'phones' => array('7188345312')
			),
			38507 => array(
			    'email'    => '',
			    'phones' => array('2018719217')
			),
			42577 => array(
			    'email'    => '',
			    'phones' => array('7186224382')
			),
			33162 => array(
			    'email'    => '',
			    'phones' => array('9179071437')
			),
			33164 => array(
			    'email'    => '',
			    'phones' => array('7183235191')
			),
			34251 => array(
			    'email'    => '',
			    'phones' => array('2127587622')
			),
			34968 => array(
			    'email'    => '',
			    'phones' => array('2124961646')
			),
			36541 => array(
			    'email'    => '',
			    'phones' => array('8456342088')
			),
			41586 => array(
			    'email'    => '',
			    'phones' => array('6466704669')
			),
			41587 => array(
			    'email'    => '',
			    'phones' => array('6466704669')
			),
			41588 => array(
			    'email'    => '',
			    'phones' => array('6466704669')
			),
			41590 => array(
			    'email'    => '',
			    'phones' => array('9172917325')
			),
			41609 => array(
			    'email'    => '',
			    'phones' => array('2123532921')
			),
			40418 => array(
			    'email'    => '',
			    'phones' => array('9173496591')
			),
			41318 => array(
			    'email'    => '',
			    'phones' => array('7185967702')
			),
			41368 => array(
			    'email'    => '',
			    'phones' => array('3473856935')
			),
			36496 => array(
			    'email'    => 'susanisaak@earthlink.net',
			    'phones' => array('2033218686')
			),
			37951 => array(
			    'email'    => '',
			    'phones' => array('2126892739')
			),
			38532 => array(
			    'email'    => 'lisa.puckett@gmail.com',
			    'phones' => array('9172704058')
			),
			38533 => array(
			    'email'    => '',
			    'phones' => array('2129825905')
			),
			38537 => array(
			    'email'    => 'sebennison@yahoo.com',
			    'phones' => array('2123379963')
			),
			40357 => array(
			    'email'    => '',
			    'phones' => array('9177517396')
			),
			40491 => array(
			    'email'    => '',
			    'phones' => array('6462280121')
			),
			36474 => array(
			    'email'    => '',
			    'phones' => array('9174504964')
			),
			37989 => array(
			    'email'    => '',
			    'phones' => array('9083174920')
			),
			36477 => array(
			    'email'    => '',
			    'phones' => array('2128463737')
			),
			38308 => array(
			    'email'    => '',
			    'phones' => array('5168250626')
			),
			40431 => array(
			    'email'    => '',
			    'phones' => array('7184409589')
			),
			40793 => array(
			    'email'    => '',
			    'phones' => array('7186381944')
			),
			38034 => array(
			    'email'    => '',
			    'phones' => array('7188588901')
			),
			38572 => array(
			    'email'    => '',
			    'phones' => array('7188571320')
			),
			40134 => array(
			    'email'    => '',
			    'phones' => array('2124269290')
			),
			41383 => array(
			    'email'    => '',
			    'phones' => array('9175665382')
			),
			36510 => array(
			    'email'    => '',
			    'phones' => array('2127210690')
			),
			42834 => array(
			    'email'    => '',
			    'phones' => array('6464541861')
			),
			42835 => array(
			    'email'    => '',
			    'phones' => array('6464541861')
			),
			42838 => array(
			    'email'    => '',
			    'phones' => array('7183427385')
			),
			42839 => array(
			    'email'    => '',
			    'phones' => array('7183427385')
			),
			42840 => array(
			    'email'    => '',
			    'phones' => array('7183427385')
			),
			42841 => array(
			    'email'    => '',
			    'phones' => array('7183427385')
			),
			42842 => array(
			    'email'    => '',
			    'phones' => array('7183427385')
			),
			42871 => array(
			    'email'    => '',
			    'phones' => array('6463367699')
			),
			42921 => array(
			    'email'    => 'lannrosado@yahoo.com',
			    'phones' => array('3475613524')
			),
			40807 => array(
			    'email'    => '',
			    'phones' => array('6465861002')
			),
			40808 => array(
			    'email'    => '',
			    'phones' => array('3473297535')
			),
			40809 => array(
			    'email'    => '',
			    'phones' => array('3473297535')
			),
			40811 => array(
			    'email'    => '',
			    'phones' => array('7183221261')
			),
			41795 => array(
			    'email'    => '',
			    'phones' => array('2126273141')
			),
			41827 => array(
			    'email'    => '',
			    'phones' => array('9175094524')
			),
			38059 => array(
			    'email'    => '',
			    'phones' => array('2127174284')
			),
			42845 => array(
			    'email'    => '',
			    'phones' => array('7185077217')
			),
			42888 => array(
			    'email'    => 'leahkalotay@hotmail.com',
			    'phones' => array('2129796620')
			),
			43110 => array(
			    'email'    => '',
			    'phones' => array('2124912672')
			),
			43112 => array(
			    'email'    => '',
			    'phones' => array('2012242683')
			),
			38341 => array(
			    'email'    => '',
			    'phones' => array('7184959044')
			),
			38587 => array(
			    'email'    => 'kevlola@hotmail.com',
			    'phones' => array('2127779785')
			),
			38590 => array(
			    'email'    => 'kmalawski@hotmail.com',
			    'phones' => array('2129882679')
			),
			40139 => array(
			    'email'    => '',
			    'phones' => array('9178176668')
			),
			40547 => array(
			    'email'    => '',
			    'phones' => array('2123379963')
			),
			40550 => array(
			    'email'    => '',
			    'phones' => array('7189818257')
			),
			40819 => array(
			    'email'    => '',
			    'phones' => array('7186860856')
			),
			41407 => array(
			    'email'    => '',
			    'phones' => array('9176931905')
			),
			41435 => array(
			    'email'    => '',
			    'phones' => array('8454967917')
			),
			38608 => array(
			    'email'    => '',
			    'phones' => array('2129883207')
			),
			38363 => array(
			    'email'    => '',
			    'phones' => array('2126846914')
			),
			39986 => array(
			    'email'    => 'poojaclayton@gmail.com',
			    'phones' => array('3474273220', '2016734619')
			),
			39990 => array(
			    'email'    => '',
			    'phones' => array('2125648890')
			),
			39884 => array(
			    'email'    => 'lisplaco@hotmail.com',
			    'phones' => array('7184573803')
			),
			38097 => array(
			    'email'    => 'leesh@scriban.com',
			    'phones' => array('2127270475')
			),
			38105 => array(
			    'email'    => 'ariel.rey@gmail.com',
			    'phones' => array('7187012190')
			),
			38368 => array(
			    'email'    => '',
			    'phones' => array('2126912801')
			),
			40008 => array(
			    'email'    => '',
			    'phones' => array('2126293042')
			),
			40010 => array(
			    'email'    => '',
			    'phones' => array('7185431292')
			),
			40151 => array(
			    'email'    => '',
			    'phones' => array('2122629205')
			),
			40153 => array(
			    'email'    => '',
			    'phones' => array('2124918119')
			),
			40574 => array(
			    'email'    => '',
			    'phones' => array('5162847736')
			),
			40848 => array(
			    'email'    => '',
			    'phones' => array('2124103045')
			),
			40851 => array(
			    'email'    => '',
			    'phones' => array('7185925664')
			),
			40852 => array(
			    'email'    => '',
			    'phones' => array('7185925664')
			),
			40853 => array(
			    'email'    => '',
			    'phones' => array('7185925664')
			),
			40876 => array(
			    'email'    => '',
			    'phones' => array('7184934440')
			),
			41782 => array(
			    'email'    => '',
			    'phones' => array('2127950857')
			),
			41786 => array(
			    'email'    => 'Mashag0s@ad.com',
			    'phones' => array('2013496991')
			),
			41787 => array(
			    'email'    => '',
			    'phones' => array('2013496991')
			),
			36847 => array(
			    'email'    => 'LBahamonde423@msn.com',
			    'phones' => array('2125311608')
			),
			42304 => array(
			    'email'    => '',
			    'phones' => array('7184154342')
			),
			42307 => array(
			    'email'    => '',
			    'phones' => array('7183646216')
			),
			42357 => array(
			    'email'    => 'BmbTzn@gmail.com',
			    'phones' => array('9175336060')
			),
			42799 => array(
			    'email'    => '',
			    'phones' => array('2129661770')
			),
			41675 => array(
			    'email'    => '',
			    'phones' => array('3475295609')
			),
			41774 => array(
			    'email'    => 'aliaspook@aol.com',
			    'phones' => array('5165995665')
			),
			41777 => array(
			    'email'    => '',
			    'phones' => array('2129253049')
			),
			41778 => array(
			    'email'    => '',
			    'phones' => array('2129253049')
			),
			41910 => array(
			    'email'    => '',
			    'phones' => array('2129950370')
			),
			42175 => array(
			    'email'    => 'dawaram5@yahoo.com',
			    'phones' => array('7188855859')
			),
			38116 => array(
			    'email'    => 'amy_fisher@yahoo.com.au',
			    'phones' => array('2125955308')
			),
			40597 => array(
			    'email'    => '',
			    'phones' => array('2129296944')
			),
			40887 => array(
			    'email'    => 'jennifer.delarosa@gmail.com',
			    'phones' => array('9176274854')
			),
			40898 => array(
			    'email'    => '',
			    'phones' => array('7186393764')
			),
			40900 => array(
			    'email'    => '',
			    'phones' => array('6462754514')
			),
			40901 => array(
			    'email'    => '',
			    'phones' => array('6462754514')
			),
			40903 => array(
			    'email'    => '',
			    'phones' => array('6468946695')
			),
			40904 => array(
			    'email'    => '',
			    'phones' => array('6468946695')
			),
			40907 => array(
			    'email'    => '',
			    'phones' => array('7185656062')
			),
			40909 => array(
			    'email'    => '',
			    'phones' => array('7187695936')
			),
			40911 => array(
			    'email'    => '',
			    'phones' => array('7185748360')
			),
			42215 => array(
			    'email'    => '',
			    'phones' => array('2126862552')
			),
			42255 => array(
			    'email'    => '',
			    'phones' => array('3475686312')
			),
			42258 => array(
			    'email'    => '',
			    'phones' => array('7188490475')
			),
			42267 => array(
			    'email'    => '',
			    'phones' => array('7186397584')
			),
			42599 => array(
			    'email'    => '',
			    'phones' => array('3474186126')
			),
			42600 => array(
			    'email'    => '',
			    'phones' => array('3474186126')
			),
			42601 => array(
			    'email'    => '',
			    'phones' => array('3474186126')
			),
			42602 => array(
			    'email'    => '',
			    'phones' => array('3474186126')
			),
			42603 => array(
			    'email'    => '',
			    'phones' => array('3474186126')
			),
			42606 => array(
			    'email'    => '',
			    'phones' => array('9178064516')
			),
			42608 => array(
			    'email'    => '',
			    'phones' => array('3472183341')
			),
			42611 => array(
			    'email'    => '',
			    'phones' => array('7184533698')
			),
			42613 => array(
			    'email'    => '',
			    'phones' => array('7182777086')
			),
			42615 => array(
			    'email'    => '',
			    'phones' => array('2126736645')
			),
			42616 => array(
			    'email'    => '',
			    'phones' => array('2126736645')
			),
			42627 => array(
			    'email'    => '',
			    'phones' => array('9176898944')
			),
			42904 => array(
			    'email'    => 'bshs99@verizon.net',
			    'phones' => array('2125339239')
			),
			42964 => array(
			    'email'    => 'nancylevene@gmail.com',
			    'phones' => array('2123620121')
			),
			40041 => array(
			    'email'    => '',
			    'phones' => array('2126844997')
			),
			40173 => array(
			    'email'    => '',
			    'phones' => array('2129799029')
			),
			40917 => array(
			    'email'    => '',
			    'phones' => array('6464787973')
			),
			40919 => array(
			    'email'    => '',
			    'phones' => array('9175531418')
			),
			41944 => array(
			    'email'    => '',
			    'phones' => array('7186792516')
			),
			41946 => array(
			    'email'    => '',
			    'phones' => array('7182046756')
			),
			43036 => array(
			    'email'    => 'shrinkrapp@gmail.com',
			    'phones' => array('2127214353')
			),
			43126 => array(
			    'email'    => '',
			    'phones' => array('6462440582')
			),
			40060 => array(
			    'email'    => '',
			    'phones' => array('2122273150')
			),
			40062 => array(
			    'email'    => '',
			    'phones' => array('2016316320')
			),
			42637 => array(
			    'email'    => '',
			    'phones' => array('2129742407')
			),
			42824 => array(
			    'email'    => '',
			    'phones' => array('2126735606')
			),
			42825 => array(
			    'email'    => 'aniaikamil@yahoo.com',
			    'phones' => array('3472688727')
			),
			43140 => array(
			    'email'    => 'abhamdoun@yahoo.com',
			    'phones' => array('9146137432')
			),
			41701 => array(
			    'email'    => '',
			    'phones' => array('2126792029')
			),
			41947 => array(
			    'email'    => '',
			    'phones' => array('7182046756')
			),
			41954 => array(
			    'email'    => '',
			    'phones' => array('7182716576')
			),
			42235 => array(
			    'email'    => '',
			    'phones' => array('7035982460')
			),
			42238 => array(
			    'email'    => '',
			    'phones' => array('2129230016')
			),
			42269 => array(
			    'email'    => '',
			    'phones' => array('2122608570')
			),
			43171 => array(
			    'email'    => '',
			    'phones' => array('7186391290')
			),
			43172 => array(
			    'email'    => '',
			    'phones' => array('3475987635')
			),
			43174 => array(
			    'email'    => '',
			    'phones' => array('9176840970')
			),
			43177 => array(
			    'email'    => '',
			    'phones' => array('7183874204')
			),
			43178 => array(
			    'email'    => '',
			    'phones' => array('7183874204')
			),
			38132 => array(
			    'email'    => 'monahank@nyc.rr.com',
			    'phones' => array('9175261001')
			),
			38404 => array(
			    'email'    => '',
			    'phones' => array('2124751258')
			),
			41640 => array(
			    'email'    => '',
			    'phones' => array('2016545580')
			),
			41651 => array(
			    'email'    => 'mremache@nyc.rr.com',
			    'phones' => array('6462610364')
			),
			42284 => array(
			    'email'    => '',
			    'phones' => array('2125313844')
			),
			40927 => array(
			    'email'    => '',
			    'phones' => array('9176474121')
			),
			40930 => array(
			    'email'    => '',
			    'phones' => array('9176178282')
			),
			38135 => array(
			    'email'    => '',
			    'phones' => array('2125802613')
			),
			40069 => array(
			    'email'    => '',
			    'phones' => array('2017669242')
			),
			40073 => array(
			    'email'    => '',
			    'phones' => array('7188565185')
			),
			40194 => array(
			    'email'    => '',
			    'phones' => array('7184523843')
			),
			40199 => array(
			    'email'    => '',
			    'phones' => array('6463393954')
			),
			40643 => array(
			    'email'    => 'ilianazm@gmail.com',
			    'phones' => array('6462905687', '6469155519')
			),
			40939 => array(
			    'email'    => 'mzfadia@earthlink.net',
			    'phones' => array('2129666220')
			),
			40944 => array(
			    'email'    => '',
			    'phones' => array('7184531918')
			),
			41712 => array(
			    'email'    => '',
			    'phones' => array('9173048130')
			),
			42403 => array(
			    'email'    => '',
			    'phones' => array('9176204661')
			),
			40660 => array(
			    'email'    => '',
			    'phones' => array('2124475746')
			),
			41530 => array(
			    'email'    => '',
			    'phones' => array('2019182164')
			),
			42006 => array(
			    'email'    => '',
			    'phones' => array('9179916302')
			),
			40241 => array(
			    'email'    => '',
			    'phones' => array('7183846409')
			),
			40242 => array(
			    'email'    => '',
			    'phones' => array('7183846409')
			),
			40964 => array(
			    'email'    => '',
			    'phones' => array('3477769281')
			),
			41624 => array(
			    'email'    => 'tuanddave@yahoo.com',
			    'phones' => array('2014599289')
			),
			42493 => array(
			    'email'    => '',
			    'phones' => array('9177040198')
			),
			40092 => array(
			    'email'    => '',
			    'phones' => array('2123689857')
			),
			38203 => array(
			    'email'    => 'jbmjbelliss@earthlink.net',
			    'phones' => array('7182854816', '9175586683')
			),
			38205 => array(
			    'email'    => '',
			    'phones' => array('7186389425')
			),
			38211 => array(
			    'email'    => '',
			    'phones' => array('2125801880')
			),
			38216 => array(
			    'email'    => 'ilyse01@yahoo.com',
			    'phones' => array('9176260444')
			),
			40991 => array(
			    'email'    => '',
			    'phones' => array('7184439569')
			),
			40992 => array(
			    'email'    => '',
			    'phones' => array('7184439569')
			),
			40994 => array(
			    'email'    => '',
			    'phones' => array('6462799817')
			),
			40996 => array(
			    'email'    => '',
			    'phones' => array('7185732543')
			),
			40997 => array(
			    'email'    => '',
			    'phones' => array('7185732543')
			),
			41556 => array(
			    'email'    => '',
			    'phones' => array('3472280453')
			),
			42015 => array(
			    'email'    => '',
			    'phones' => array('8453717885')
			),
			40113 => array(
			    'email'    => '',
			    'phones' => array('7184395284')
			),
			40287 => array(
			    'email'    => '',
			    'phones' => array('2126970039')
			),
			40290 => array(
			    'email'    => '',
			    'phones' => array('')
			),
			41563 => array(
			    'email'    => '',
			    'phones' => array('9178469170')
			),
			42661 => array(
			    'email'    => '',
			    'phones' => array('7187979335')
			),
			42769 => array(
			    'email'    => '',
			    'phones' => array('2129891248')
			),
			40107 => array(
			    'email'    => '',
			    'phones' => array('2122860052')
			),
			42506 => array(
			    'email'    => '',
			    'phones' => array('7185523005')
			),
			42670 => array(
			    'email'    => '',
			    'phones' => array('7182087637')
			),
			40726 => array(
			    'email'    => '',
			    'phones' => array('2126001204')
			),
			42461 => array(
			    'email'    => '',
			    'phones' => array('2036109774')
			),
			42465 => array(
			    'email'    => '',
			    'phones' => array('3477893780')
			),
			42686 => array(
			    'email'    => 'karatomko@yahoo.com',
			    'phones' => array('2015368779')
			),
			42779 => array(
			    'email'    => '',
			    'phones' => array('9175454904')
			),
			43042 => array(
			    'email'    => 'Laurel.Glaser@gmail.com',
			    'phones' => array('3475346581')
			),
			41025 => array(
			    'email'    => 'esmark@bigplanet.com',
			    'phones' => array('7185916448')
			),
			40297 => array(
			    'email'    => '',
			    'phones' => array('7182921404')
			),
			40298 => array(
			    'email'    => '',
			    'phones' => array('7182921404')
			),
			40307 => array(
			    'email'    => '',
			    'phones' => array('2123581911')
			),
			42516 => array(
			    'email'    => '',
			    'phones' => array('7188482312')
			),
			42519 => array(
			    'email'    => '',
			    'phones' => array('3477240717')
			),
			42521 => array(
			    'email'    => '',
			    'phones' => array('7183961073')
			),
			42522 => array(
			    'email'    => '',
			    'phones' => array('7183961073')
			),
			42696 => array(
			    'email'    => 'ariel.rey@gmail.com',
			    'phones' => array('7187012190')
			),
			42697 => array(
			    'email'    => 'anitapsharma@gmail.com',
			    'phones' => array('2125376975')
			),
			43068 => array(
			    'email'    => 'goldiek3865@yahoo.com',
			    'phones' => array('7182303865')
			),
			43181 => array(
			    'email'    => '',
			    'phones' => array('6464636042')
			),
			43182 => array(
			    'email'    => '',
			    'phones' => array('6464636042')
			),
			43184 => array(
			    'email'    => '',
			    'phones' => array('3475526977')
			),
			43191 => array(
			    'email'    => '',
			    'phones' => array('6465411179')
			),
			41044 => array(
			    'email'    => '',
			    'phones' => array('2128422955')
			),
			41046 => array(
			    'email'    => '',
			    'phones' => array('7182677194')
			),
			41050 => array(
			    'email'    => '',
			    'phones' => array('3476130590')
			),
			41070 => array(
			    'email'    => '',
			    'phones' => array('7183888397')
			),
			41071 => array(
			    'email'    => '',
			    'phones' => array('7183888397')
			),
			41074 => array(
			    'email'    => '',
			    'phones' => array('7184183848')
			),
			41075 => array(
			    'email'    => '',
			    'phones' => array('7184183848')
			),
			42037 => array(
			    'email'    => '',
			    'phones' => array('9176136188')
			),
			42533 => array(
			    'email'    => '',
			    'phones' => array('9733130620')
			),
			43081 => array(
			    'email'    => '',
			    'phones' => array('9732700386')
			),
			43082 => array(
			    'email'    => 'as_rtw@yahoo.com',
			    'phones' => array('2129958802')
			),
			43089 => array(
			    'email'    => 'fumiko_motozawa@hotmail.com',
			    'phones' => array('2125359011')
			),
			43194 => array(
			    'email'    => '',
			    'phones' => array('6462366253')
			),
			43200 => array(
			    'email'    => '',
			    'phones' => array('7186844815')
			),
			43202 => array(
			    'email'    => '',
			    'phones' => array('7182040737')
			),
			43206 => array(
			    'email'    => '',
			    'phones' => array('7184787307')
			),
			41052 => array(
			    'email'    => '',
			    'phones' => array('2123538904')
			),
			41079 => array(
			    'email'    => '',
			    'phones' => array('5172145867')
			),
			42547 => array(
			    'email'    => '',
			    'phones' => array('2127214901')
			),
			43233 => array(
			    'email'    => '',
			    'phones' => array('2127416394')
			),
			43238 => array(
			    'email'    => 'wafle@comcast.net',
			    'phones' => array('7326711038')
			),
			43241 => array(
			    'email'    => 'chachimoto@aol.com',
			    'phones' => array('')
			),
			41111 => array(
			    'email'    => '',
			    'phones' => array('3476793402')
			),
			41112 => array(
			    'email'    => '',
			    'phones' => array('3476793402')
			),
			42078 => array(
			    'email'    => '',
			    'phones' => array('2014342565')
			),
			42081 => array(
			    'email'    => '',
			    'phones' => array('9174057991')
			),
			42562 => array(
			    'email'    => 'nabigus@gmail.com',
			    'phones' => array('9175441756')
			),
			41122 => array(
			    'email'    => '',
			    'phones' => array('2129796694')
			),
			41124 => array(
			    'email'    => '',
			    'phones' => array('6464725248')
			),
			41135 => array(
			    'email'    => '',
			    'phones' => array('9176717555')
			),
			42105 => array(
			    'email'    => '',
			    'phones' => array('2127800932')
			),
			42109 => array(
			    'email'    => '',
			    'phones' => array('2129287501')
			),
			43262 => array(
			    'email'    => '',
			    'phones' => array('3472839070')
			),
			41153 => array(
			    'email'    => '',
			    'phones' => array('7329069329')
			),
			43270 => array(
			    'email'    => '',
			    'phones' => array('7184377605')
			),
			43271 => array(
			    'email'    => '',
			    'phones' => array('7184377605')
			),
			43273 => array(
			    'email'    => '',
			    'phones' => array('7184992153')
			),
			41157 => array(
			    'email'    => '',
			    'phones' => array('7187682526')
			),
			41162 => array(
			    'email'    => '',
			    'phones' => array('5167391543')
			),
			41163 => array(
			    'email'    => 'leah_kramnick@yahoo.com',
			    'phones' => array('2125337246')
			),
			43280 => array(
			    'email'    => '',
			    'phones' => array('7185701537')
			),
			43283 => array(
			    'email'    => '',
			    'phones' => array('6466432032')
			),
			43284 => array(
			    'email'    => '',
			    'phones' => array('6466432032')
			),
			43285 => array(
			    'email'    => '',
			    'phones' => array('6466432032')
			),
			43295 => array(
			    'email'    => '',
			    'phones' => array('2015692566')
			),
			41195 => array(
			    'email'    => '',
			    'phones' => array('7185741608')
			),
			41196 => array(
			    'email'    => '',
			    'phones' => array('7185741608')
			),
			41200 => array(
			    'email'    => '',
			    'phones' => array('6468971992')
			),
			41202 => array(
			    'email'    => '',
			    'phones' => array('2013880071')
			),
			41204 => array(
			    'email'    => 'lisa.puckett@gmail.com',
			    'phones' => array('9172704058')
			),
			41224 => array(
			    'email'    => '',
			    'phones' => array('2122602303')
			),
			41243 => array(
			    'email'    => '',
			    'phones' => array('3475378185')
			),
			41246 => array(
			    'email'    => '',
			    'phones' => array('6468084955')
			),
			41262 => array(
			    'email'    => '',
			    'phones' => array('7183819107')
			),
			41284 => array(
			    'email'    => '',
			    'phones' => array('7182921659')
			),
			41285 => array(
			    'email'    => '',
			    'phones' => array('7182921659')
			)
		);
		
		return $duplicateFamilies;
	}
}
