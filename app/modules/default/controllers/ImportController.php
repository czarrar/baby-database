<?php


#$origContactSources = array(
#    "Ad"                         => 1,  
#    "Articles on Baby Lab"       => 3,   
#    "Arts and Ideas"             => 12,  
#    "Arts and Ideas Concert"     => 12, 
#    "Brochures"                  => 1,   
#    "Cold Call"                  => 4, 
#    "Cold Calling"               => 4,   
#    "Craigslist"                 => 5,   
#    "Flyers"                     => 1,   
#    "Infant Lab Website"         => 7,   
#    "Info Request online"        => 8,   
#    "Kid Labs"                   => 2,   
#    "Mailing"                    => 6,   
#    "New Haven Farmer's Market"  => 13,  
#    "Parents of Baby Scientists" => 11, 
#    "Signup sheet at event"      => 14, 
#);

class ImportController extends Zend_Controller_Action 
{
    const FIX_DUPLICATES = TRUE;
	
	#const HOME_DIR = "/home/wpl/public_html/babydb/app/etc/";
	const HOME_DIR = "/Users/zarrar/Sites";
	
	function formatPhoneWorker($phone, $type, $extension) {
	    // if find c:, (c), or cell, assume it is a cell
	    $hasCell1 = stripos($phone, 'c:');
	    $hasCell2 = stripos($phone, '(c)');
	    $hasCell3 = stripos($phone, 'cell');
	    $isCell = FALSE;
	    if ($hasCell1!==FALSE || $hasCell2!==FALSE || $hasCell3!==FALSE)
	        $type = 2;  # cell
	    
	    // if find (h) or home, assume it is home
	    $hasHome1 = stripos($phone, '(h)');
	    $hasHome2 = stripos($phone, 'home');
	    $isCell = FALSE;
	    if ($hasHome1!==FALSE || $hasHome2!==FALSE)
	        $type = 1;  # home
	    
	    // strip everything except numbers
	    $filter = new Zend_Filter_Digits();
        $phone = $filter->filter($phone);
        
        // check length
        $lenPhone = strlen($phone);
        switch ($lenPhone) {
            case 5:
                $phones = array(array("phone" => "203" . "43" . $phone, "type" => $type, "extension" => $phone));
                break;
            case 7:
                $phone = "1203" . $phone;
            # NOTE: no break
            case 11:
                $firstDigit = substr($phone, 0, 1);
                if ($firstDigit == "1")
                    $phone = substr($phone, 1);
                else
                    $phone = substr($phone, 0, 10);
            # NOTE: no break
            case 10:
                $phones = array(array("phone" => $phone, "type" => $type, "extension" => $extension));
                break;
            case 14:
                $phone = "203" . $phone;
            # NOTE: no break
            case 17:
                # see if want add 203 for first set of numbers or second
                if (substr($phone, 0, 3) == "203")
                    $phone = substr($phone, 0, 10) . "203" . substr($phone, 10);
                else
                    $phone = "203" . $phone;
            # NOTE: no break
            case 20:
                $phones = array(
                    array("phone" => substr($phone, 0, 10), "type" => $type, "extension" => $extension),
                    array("phone" => substr($phone, 10, 10), "type" => $type, "extension" => $extension),
                );
                break;
            default:
                if ($lenPhone > 10) {
                    $phones = array(array("phone" => substr($phone, 0, 10), "type" => $type, "extension" => $extension));
                } else {
                    #echo "*** ERROR: {$lenPhone} phone digits ***";
                    $phones = array();
                }
                break;
        }
        
        return($phones);
	}
	
	function formatPhone($phone, $type) {
	    #echo "{$phone}";
	    
	    ## if find ext followed by some numbers, put that in the extension section    		    
	    $hasExt = stripos($phone, 'x');
	    if ($hasExt !== FALSE) {
	        $extension = substr($phone, $hasExt);
	        $filter = new Zend_Filter_Digits();
	        $extension = $filter->filter($extension);
	        if (strlen($extension) > 5)
	            $extension = "";
	        $phone = substr($phone, 0, $hasExt);
	    } else {
	        $extension = "";
	    }
	    
	    ## split phone numbers if find , or ;
	    if (strpos($phone, ',')!==FALSE)
	        $phones = explode(",", $phone);
	    elseif (strpos($phone, ';')!==FALSE)
	        $phones = explode(";", $phone);
	    else
	        $phones = array($phone);
	    
	    $formatPhones = array();
	    foreach ($phones as $phone)
	       $formatPhones = array_merge($formatPhones, $this->formatPhoneWorker($phone, $type, $extension));
	    
	    #echo " ---- ";
	    #print_r($formatPhones);
	    #echo "<br />\n";
	    
	    return($formatPhones);
	}
	
	// Add family, baby, family_emails, family_phones, baby_languages
	function fillMainTablesAction() {
	
	    set_time_limit(300);
	    
	    $origContactSources = array(
            "Ad"                         => 1,  
            "Articles on Baby Lab"       => 3,   
            "Arts and Ideas"             => 12,  
            "Arts and Ideas Concert"     => 12, 
            "Brochures"                  => 1,   
            "Cold Call"                  => 4, 
            "Cold Calling"               => 4,   
            "Craigslist"                 => 5,   
            "Flyers"                     => 1,   
            "Infant Lab Website"         => 7,   
            "Info Request online"        => 8,   
            "Kid Labs"                   => 2,   
            "Mailing"                    => 6,   
            "New Haven Farmer's Market"  => 13,  
            "Parents of Baby Scientists" => 11, 
            "Signup sheet at event"      => 14, 
        );
        
        $newContactSources = array(
            1 => "Baby Lab Brochure/Flyer",
            2 => "Kid Lab Brochure/Flyer",
            3 => "Popular Press",
            4 => "Cold Calling",
            5 => "Craigslist",
            6 => "Mailing",
            7 => "Baby Lab Website - Contacted Us",
            8 => "Baby Lab Website - Filled out Form",
            9 => "Kid Lab Website - Contacted Us",
            10 => "Kid Lab Website - Filled out Form",
            11 => "Heard from Participating Parent",
            12 => "Arts and Ideas",
            13 => "Farmer's Market",
            14 => "Other Event"
        );
        
        $languageIds = array(
            "English" => 1,
            "Eng" => 1,
            "French" => 2,
            "Spanish" => 3,
            "Arabic" => 10,
            "Italian" => 34,
            "German" => 28,
            "Urdu" => 64,
            "Mandarin" => 39,
            "Cantonese" => 17,
            "Chinese" => 71,
            "Hindi" => 32,
            "Punjabi" => 45,
            "sign" => 103,
            "ASL" => 9,
            "Lithuanian" => 37,
            "Polish" => 44,
            "Hebrew" => 31,
            "Portuguese" => 15,
            "Vietnamese" => 65,
            "Russian" => 48,
            "Japanese" => 35,
            "Thai" => 61,
            "Latvian" => 82,
            "Dutch" => 23,
            "Bengali" => 12,
            "Swedish" => 57,
            "Finnish" => 26,
            "Korean" => 36,
            "Romanian" => 47,
            "Marathi" => 80,
            "Hungarian" => 5,
            "norweigian" => 41
        );
	    
	    $handle = fopen("/Users/zarrar/Sites/database_exp.csv", "r");
	    
	    # arrays to check for duplicates
	    $arr1 = array();
	    $arr2 = array();
	    $arr3 = array();
	    $arr4 = array();
	    
	    // Get db adapter
		$db = Zend_Registry::get('db');    		
        
        // Family Table
        $fTbl = new Family();
        $feTbl = new FamilyEmail();
        $fpTbl = new FamilyPhone();
        
        $bTbl = new Baby();
        $blTbl = new BabyLanguage();
        $lTbl = new Language();
	    
	    $rowNum = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $num = count($data);
            #echo "<p> $num fields in line $rowNum: <br /></p>\n";

            // Skip header
            $rowNum++;
            if ($rowNum == 2)
                continue;

            // Trim each column
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }
	        
	        #/*
	        
    	    # Family
    	    $fRow = array();
            
            ## date_of_entry <= 77
            if (!empty($data[77]))
                $fRow['date_of_entry'] = date("Y-m-d", strtotime($data[77]));
            
            ## mother_first_name <= 79
            if (!empty($data[79]))
                $fRow['mother_first_name'] = $data[79];
            
            ## mother_last_name <= 82
            if (!empty($data[82]))
                $fRow['mother_last_name'] = $data[82];
            
            ## father_first_name <= 101
            if (!empty($data[101]))
                $fRow['father_first_name'] = $data[101];
            
            ## father_last_name <= 102
            if (!empty($data[102]))
                $fRow['father_last_name'] = $data[102];
            
            ## contact_source_id <= 76
            if (!empty($data[76]))
                $fRow['contact_source_id'] = $origContactSources[$data[76]];
            
            ## address_1 <= 66
            if (!empty($data[66]))
                $fRow['address_1'] = $data[66];
            
            ## city <= 114
            if (!empty($data[114]))
                $fRow['city'] = $data[114];
            
            ## state <= 68
            if (!empty($data[68]))
                $fRow['state'] = $data[68];
            
            ## zip <= 1
            if (!empty($data[1])) {
                if (strlen($data[1])==4)
                    $data[1] = "0" . $data[1];
                $filter = new Zend_Filter_Digits();
    	        $zip = $filter->filter($data[1]);
    	        $lenZip = strlen($zip);
    	        if ($lenZip == 9) {
    	            $fRow['zip'] = substr($zip, 0, 5);
    	            $fRow['zip_plus'] = substr($zip, 5);
    	        } elseif ($lenZip == 5) {
    	            $fRow['zip'] = $zip;
    	        } elseif ($lenZip == 4) {
    	            $fRow['zip'] = "0" . $zip;
    	        } else {
    	            echo "ERROR: zip code '{$zip}' has length {$lenZip}<br />\n";
    	        }
            }
            
            ## comments <= 72 (scheduling constraints)
            if (!empty($data[72]))
                $fRow['comments'] = "Scheduling Constraints: " . $data[72] . "\n";
            
            #print_r($fRow);
            
            #if ($rowNum>5)
            #    exit();
            
            # check for duplicate
            $familyId = NULL;
            $duplicated = FALSE;
            // Build select query (using fluent interface)
    		$select = $db->select()
    		// Want distinct rows
    			->distinct()
    		// Group by family id
    			->group("f.id")
    		// Start from family table + get family information
    			->from(array('f' => 'families'),
    	        	array('family_id' => 'id', "date_of_entry", 'mother_last_name', 'mother_first_name', 'father_last_name', 'father_first_name', "address_1", "city", "state", "zip", "zip_plus", "contact_source_id", "comments"))
	    	// Get email information
    			->joinLeft(array('fe' => 'family_emails'),
    				'f.id = fe.family_id', array('emails' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT email SEPARATOR ', ')")))
		    // Get phone information
    			->joinLeft(array('fp' => 'family_phones'),
    				'f.id = fp.family_id', array('telephone' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT phone_number SEPARATOR ', ')")));
    		
    		$emptyWhereData = $select->getPart(Zend_Db_Select::WHERE);
    		
    		// Process email
    		if (!empty($data[0])) {
    		    $email = $data[0];
    		    
    		    if (strpos($email, ',')!==FALSE)
        	        $emails = explode(",", $email);
        	    elseif (strpos($email, ';')!==FALSE)
        	        $emails = explode(";", $email);
        	    elseif (strpos($email, ' ')!==FALSE)
        	        $emails = explode(' ', $email);
        	    else
        	        $emails = array($email);
        	    
        	    #echo "$email --- ";
        	    #print_r($emails);
        	    #echo "<br />\n";
    		} else {
    		    $emails = array();
    		}
    		
    		// Process phone number
    		# 3 = work
    		if (!empty($data[3])) 
    		    $phones = $this->formatPhone($data[3], 3);
    		else 
    		    $phones = array();
    		# 1 = home
    		if (!empty($data[99]))
    		    $phones = array_merge($phones, $this->formatPhone($data[99], 1));
    		
    		// Check email
    		if (!empty($emails)) {
    		    foreach ($emails as $email) {
    		        if (!empty($email) && stripos($data[0], '@') !== FALSE)
    		            $select->orWhere("fe.email = ?", $email);
    		    }
    		}
    		
    		// Check phone
    		if (!empty($phones)) {
    		    foreach ($phones as $phone) {
    		        if (!empty($phone) && !empty($phone['phone']))
    		            $select->orWhere("fp.phone_number = ?", $phone['phone']);
    		    }
    		}
    		
    	    // Check zip etc
    	    if (!empty($fRow['zip'])) {
    	        $zip = $db->quote($fRow['zip']);
    	        // Check mother first name and mother last name
    	        if (!empty($fRow['mother_first_name']) && !empty($fRow['mother_last_name'])) {
    	            $mfn = $db->quote($fRow['mother_first_name']);
    	            $mln = $db->quote($fRow['mother_last_name']);
    	            $select->orWhere("f.mother_first_name LIKE $mfn AND f.mother_last_name LIKE $mln AND f.zip = $zip");
    	        }   
        	    // Check address
        	    elseif (!empty($fRow['address_1'])) {
        	        $address = $db->quote($fRow['address_1']);
        	        $select->orWhere("f.address_1 = $address AND f.zip = $zip");
    	        }
    	    }
    	    
    	    // Do Search
    	    $whereData = $select->getPart(Zend_Db_Select::WHERE);
    	    if (!empty($whereData)) {
    	        $query = $select->__toString();
    	        #print_r($query);
    	        #exit();
    	        
    	        $stmt = $select->query();
        	    $result = $stmt->fetchAll();
                
                if (!empty($result)) {
                    $duplicated = TRUE;
                    $familyId = $result[0]['family_id'];
                    $dateOfEntry = $result[0]['date_of_entry'];
                    #echo "DUPLICATE FOUND:<br />\n";
                    #print_r($query);
            	    #echo "<br />\n";
            	    #print_r($result);
            	    #echo "<br /><br />\n\n";
                }
    	    } else {
    	        $query = "";
    	        $duplicated = FALSE;
    	    }
    	    
    	    if (!$duplicated) {
    	        try {
        			$db->beginTransaction();
        			$familyId = $fTbl->insert($fRow);
        			$dateOfEntry = $fRow['date_of_entry'];
        			$db->commit();
        		} catch (Exception $e) {
        			echo "Error: " . $e . " <br /><br />\n\n";
        			$db->rollback();
        		}
        		
        		# Family Emails
                ## email <= 0
                if (!empty($emails)) {
                    # current number of emails with this family id
                    $where = $db->quoteInto("family_id = ?", $familyId);
    				$rows = $feTbl->fetchAll($where);
    				$cRows = count($rows);

    				#echo "<br />";
    				#echo "COUNT: $cRows";
    				#echo "<br />";

    				$cRows++;

                    foreach ($emails as $email) {
                        $feRow = array();

                        ## check that this is an email address
                        if (!empty($email) && stripos($email, '@') !== FALSE) {
                            ## email
                            $feRow['email'] = $email;
                            ## family_id
                            $feRow['family_id'] = $familyId;
                            ## family_owner_id <= #5 (unknown)
                            $feRow['family_owner_id'] = 5;
                            ## order
                            $feRow['order'] = $cRows;

                            try {
                    			$db->beginTransaction();
                    			$feTbl->insert($feRow);
                    			$db->commit();
                    			$cRows++;
                    		} catch (Exception $e) {
                    			echo "Error: " . $e . " <br />\n";
                    			print_r($fRow);
                                echo "<br />\n";
                                print_r($feRow);
                                echo "<br />\n";
                                print_r($query);
                    			echo "<br /><br />\n\n";
                    			$db->rollback();
                    		}
                        } else {
                            echo "ERROR: bad email address: {$email}<br />\n";
                            print_r($fRow);
                            echo "<br /><br />\n\n";
                        }
                    }
                }

                # Family Phones
                ## email <= 0
                if (!empty($phones)) {
                    foreach ($phones as $phone) {
                        $fpRow = array();
                        if (!empty($phone) && !empty($phone['phone'])) {
                            ## phone_number <= 99
                            $fpRow['phone_number'] = $phone['phone'];
                            ## family_id
                            $fpRow['family_id'] = $familyId;
                            ## family_setting_id
                            $fpRow['family_setting_id'] = $phone['type'];
                            ## family_owner_id <= #5 (unknown)
                            $fpRow['family_owner_id'] = 5;
                            ## extension
                            if (!empty($phone['extension']))
                                $fpRow['extension'] = $phone['extension'];

                            try {
                    			$db->beginTransaction();
                    			$fpTbl->insert($fpRow);
                    			$db->commit();
                    		} catch (Exception $e) {
                    			echo "Error: " . $e . " <br />\n";
                    			print_r($fRow);
                    			echo "<br />\n";
                    			print_r($fpRow);
                    			echo "<br />\n";
                                print_r($query);
                    			echo "<br /><br />\n\n";
                    			$db->rollback();
                    		}
                        }
                    }
                }
    	    }
            # Duplicate
            else {
                if (count($result) > 1) {
                    echo "ERROR: found more than 1 duplicate<br />\n";
                    exit();
                } else {
                    $row = $result[0];
                    $familyId = $row['family_id'];
                }
                
                $bad = FALSE;
                foreach ($fRow as $key => $value) {
                    $value = trim($value);
                    if (!empty($value)) {
                        if (empty($row[$key])) {
                            echo "$key => $value is not in database<br />\n";
                            
                            try {
                    			$db->beginTransaction();
                                $where = $db->quoteInto("id = ?", $familyId);
                                $fTbl->update(array($key => $value), $where);
                    			$db->commit();
                    		} catch (Exception $e) {
                    			echo "Error: " . $e . " <br />\n";
                    			print_r($fRow);
                    			echo "<br />\n";
                                print_r($query);
                    			echo "<br /><br />\n\n";
                    			$db->rollback();
                    			$bad = TRUE;
                    		}
                        } elseif (strnatcasecmp($value, $row[$key])) {
                            if ($key != "comments") {
                                switch ($key) {
                                    # for date-of-entry, take the value that is smaller
                                    case 'date_of_entry':
                                        $filter = new Zend_Filter_Digits();
                                        $dbDoe = $filter->filter($row[$key]);
                                        $fmDoe = $filter->filter($value);
                                        if ((int) $dbDoe >  (int) $fmDoe) {
                                            try {
                                    			$db->beginTransaction();
                                    			$where = $db->quoteInto("id = ?", $familyId);
                                    			$fTbl->update(array('date_of_entry' => $value), $where);
                                    			$db->commit();
                                    		} catch (Exception $e) {
                                    			echo "Error: " . $e . " <br />\n";
                                    			print_r($fRow);
                                    			echo "<br />\n";
                                                print_r($query);
                                    			echo "<br /><br />\n\n";
                                    			$db->rollback();
                                    			$bad = TRUE;
                                    		}
                                        
                                            #echo "Will replace {$value} with current {$row[$key]}<br />\n";
                                        }
                                        break;
                                    default:
                                        try {
                                			$db->beginTransaction();
                                			$where = $db->quoteInto("id = ?", $familyId);
                                			if (empty($row['comments']))
                                			    $comments = "DB Export Conflict: {$key} is currently {$row[$key]} but maybe should be {$value}\n";
                                			else
                                			    $comments = $row['comments'] . "DB Export Conflict: {$key} is currently {$row[$key]} but maybe should be {$value}\n";
                                			$fTbl->update(array('comments' => $comments), $where);
                                			$db->commit();
                                		} catch (Exception $e) {
                                			echo "Error: " . $e . " <br />\n";
                                			print_r($fRow);
                                			echo "<br />\n";
                                            print_r($query);
                                			echo "<br /><br />\n\n";
                                			$db->rollback();
                                			$bad = TRUE;
                                		}
                                		
                                        #$bad = TRUE;
                                        # for anything else, just put it in the comments
                                        break;
                                }
                            }
                        }
                    }
                }
                
                if ($bad) {
                    echo "--- ";
                    print_r($fRow);
                    echo "<br />\n";
                    echo "--- ";
                    print_r($result);
                    echo "<br />\n";
                    echo "<br />\n";
                }
            }
                        
            # Baby
            $bRow = array();
            $addComments = "";
            ## id
            ## family_id
            $bRow['family_id'] = $familyId;
            ## status_id <= inactive (1)
            $bRow['status_id'] = 1;
            ## date_of_entry <= 77
            $bRow['date_of_entry'] = $dateOfEntry;
            ## first_name <= 116
            if (!empty($data[116]))
                $bRow['first_name'] = $data[116];
            ## last_name <= 115
            if (!empty($data[115]))
                $bRow['last_name'] = $data[115];
            ## comments <= 99, 69 and 70 sibs info
            if (!empty($data[98]))
                $addComments = $addComments . $data[98] . "\n";
            if (!empty($data[69]))
                $addComments = $addComments . "SIBS: " . $data[69] . "\n";
            if (!empty($data[70]))
                $addComments = $addComments . "SIBS DOB: " . $data[70] . "\n";
            ## dob <= 122
            if (!empty($data[122])) {
                $bRow['dob'] = date("Y-m-d", strtotime($data[122]));
                
                $goodDob = TRUE;
            } else {
                $bRow['dob'] = "1950-01-01";    # need to give some default;
                $addComments = $addComments . "NO DATE OF BIRTH!\n";
                
                $goodDob = FALSE;
            }
            ## sex <= 71 (1=female, 2=male)
            if (!empty($data[71])) {
                if (stripos($data[71], 'female')!==FALSE)
                    $bRow['sex'] = 1;
                elseif (stripos($data[71], 'male')!==FALSE)
                    $bRow['sex'] = 2;
                elseif (stripos($data[71], '???')===FALSE)
                    echo "ERROR: bad sex field {$data[71]}<br />\n";
            }
            ## term <= 100 (40 weeks is full-term)
            if (!empty($data[100])) {
                if (stripos($data[100], 'yes')!==FALSE)
                    $bRow['term'] = 40;
                elseif (stripos($data[100], 'no')!==FALSE)
                    $bRow['term'] = 1;
                elseif (stripos($data[100], '???')===FALSE)
                    echo "ERROR: bad term field {$data[100]}<br />\n";
            }
            ## list_id <= 96 (but check 78 if it has a name unlike A, B, C then use this)...
            $listIds = array(
                "None" => 1,
                "A" => 2,
                "B" => 3,
                "C" => 4,
                "Bloom" => 5,
                "Keil" => 6,
                "Olson" => 7
            );
            
            // First List (has permanent stuff)
            $mainList = "None";
            $firstList = FALSE;
            if (!empty($data[78])) {
                if (stripos($data[78], "keil")!==FALSE)
                    $mainList = "Keil";
                elseif (stripos($data[78], "bloom")!==FALSE)
                    $mainList = "Bloom";
                elseif (stripos($data[78], "olson")!==FALSE)
                    $mainList = "Olson";
                elseif (stripos($data[78], "a")!==FALSE)
                    $firstList = "A";
                elseif (stripos($data[78], "b")!==FALSE)
                    $firstList = "B";
                elseif (stripos($data[78], "c")!==FALSE)
                    $firstList = "C";
            }
            
            // Second List
            if ($mainList == "None") {
                if (!empty($data[96])) {
                    if (stripos($data[96], "a")!==FALSE)
                        $mainList = "A";
                    elseif (stripos($data[96], "b")!==FALSE)
                        $mainList = "B";
                    elseif (stripos($data[96], "c")!==FALSE)
                        $mainList = "C";
                }
                
                // Still None
                if ($mainList == "None" && $firstList !== FALSE)
                    $mainList = $firstList;
            }
            
            // Save list id
            $bRow['list_id'] = $listIds[$mainList];
            
            # Save baby
            $duplicated = FALSE;
            if ($goodDob) {
                $key = array_search($bRow['dob'], $arr1);
                if ($key!==FALSE) {
                    if (!empty($bRow['last_name']) && $bRow['last_name'] == $arr2[$key]
                        && !empty($bRow['first_name']) && $bRow['first_name'] == $arr3[$key]) {
                        echo "DUPLICATE BABY: {$bRow['dob']} & {$bRow['last_name']}, {$bRow['first_name']}<br />\n";
                        echo "OLD: ";
                        print_r($arr4[$key]);
                        echo "<br />\n";
                        echo "CURRENT: ";
                        print_r($bRow);
                        echo "<br /><br />\n\n";
                        
                        $duplicated = TRUE;
                    }
                }
                
                array_push($arr4, $bRow);
                array_push($arr2, $bRow['last_name']);
                array_push($arr3, $bRow['first_name']);
                array_push($arr1, $bRow['dob']);
            }
            
            if ($duplicated)
                continue;
            
            try {
    			$db->beginTransaction();
    			$babyId = $bTbl->insert($bRow);
    			$db->commit();
    		} catch (Exception $e) {
    			echo "Baby Error: " . $e . " <br /><br />\n\n";
    			$db->rollback();
    			continue;
    		}
    		
    		# Baby Languages
            ## language_id <= 89 (need to search for the language and get the id)
            $blids = array();
            if (!empty($data[89])) {
                foreach ($languageIds as $languageName => $languageId) {
                    if (stripos($data[89], $languageName)!==FALSE)
                        array_push($blids, $languageId);
                }
                $blids = array_unique($blids);
            }
            
            if (!empty($blids)) {
                $keys = array_keys($blids);
                for ($i=0; $i < count($blids); $i++) { 
                    $lid = $blids[$keys[$i]];

                    $blRow = array(
                        'baby_id' => $babyId,
                        'language_id' => $lid,
                        'order' => $i + 1
                    );

                    try {
            			$db->beginTransaction();
            			$blTbl->insert($blRow);
            			$db->commit();
            		} catch (Exception $e) {
            			echo "Baby Language Error: " . $e . " <br />\n";
            			echo $data[89];
            			echo "<br />\n";
            			print_r($blRow);
            			echo "<br />\n";
            			print_r($blids);
            			echo "<br /><br />\n\n";
            			$db->rollback();
            		}
                }
            }
            
            #*/
            
            #echo nl2br($data[113]);
            #echo "<br /><br />\n\n";
            
        }
        
        exit();
	}
	
	function getStudiesAction() {
	    
	    set_time_limit(300);
	    
	    $handle = fopen("/Users/zarrar/Sites/database_exp.csv", "r");

        # Save study names
        $studies = array();
        
        # Different columns from csv
        $studyCols = array(65, 43, 39, 37, 35, 33, 31, 29, 27, 63, 61, 59, 57, 55, 53, 51, 49, 47, 45, 41);
        $studyDateCols = array(64, 42, 38, 36, 34, 32, 30, 28, 26, 62, 60, 58, 56, 54, 52, 50, 48, 46, 44, 40);
        
        $rowNum = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($rowNum === 1) {
                $rowNum++;
                continue;
            }

            $num = count($data);
            // Trim each column and print onto screen
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }

            foreach ($studyCols as $col) {
                array_push($studies, $data[$col]);
            }
            
            $rowNum++;
        }
        
        echo "<br />\n";
        $uStudies = array_unique($studies);
        print_r($uStudies);
        echo "<br />\n";
        print_r(count($uStudies));
        
        exit();
	    
	    
	}
	
	// Set the contact sources
	// add contact sources to table
	function addContactSourcesAction() {
	
	    $db = Zend_Registry::get('db');
		$csTbl = new ContactSource();
	
	    $handle = fopen("/Users/zarrar/Sites/database_exp.csv", "r");

        # 1. Setup contact_sources
        $newContactSources = array(
            1 => "Baby Lab Brochure/Flyer",
            2 => "Kid Lab Brochure/Flyer",
            3 => "Popular Press",
            4 => "Cold Calling",
            5 => "Craigslist",
            6 => "Mailing",
            7 => "Baby Lab Website - Contacted Us",
            8 => "Baby Lab Website - Filled out Form",
            9 => "Kid Lab Website - Contacted Us",
            10 => "Kid Lab Website - Filled out Form",
            11 => "Heard from Participating Parent",
            12 => "Arts and Ideas",
            13 => "Farmer's Market",
            14 => "Other Event"
        );

        foreach ($newContactSources as $v) {
            $csRow = array(
                'source' => $v,
                'to_use' => 1
            );
        
    		try {
    			$db->beginTransaction();
    			
    			// New contact source
    			$csTbl->insert($csRow);
			    echo "inserting: $v <br />";
			    			
    			$db->commit();
    		} catch (Exception $e) {
    			echo "Error: " . $e . " <br />";
    			$db->rollback();
    		}
        }
        
	    exit();
	}
	
	// 
	
	// Find the unique contact sources and spit it out as array
	function uniqueContactsAction() {
	    set_time_limit(300);
	    
	    $handle = fopen("/Users/zarrar/Sites/database_exp.csv", "r");

        # 1. Find unique contact_source_id combos
        $contactSources = array();

        $rowNum = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($rowNum === 1) {
                $rowNum++;
                continue;
            }

            $num = count($data);
            // Trim each column and print onto screen
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }

            // Get contact source id (save if not empty)
            if (!empty($data[76])) {
                echo "<p>$rowNum: $num fields in line <br />\n";
                echo "...has contact source: <b>$data[76]</b><br />\n";
                array_push($contactSources, $data[76]);
            }

            echo "</p>\n";

            $rowNum++;
        }

        $ucs = array_unique($contactSources);
        asort($ucs);

        print_r($ucs);
        
        exit();
	}
	
}
