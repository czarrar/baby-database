<?php

ini_set("memory_limit","50M");
ini_set('auto_detect_line_endings', true);  # solves issue of reading files


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
	
	const HOME_DIR = "http://wynnlab01.psych.yale.edu";
	#const HOME_DIR = "/Users/zarrar/Sites";
	
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
        
        // Save baby ids
        $myFile = "babies_row2id.csv";
        $fh = fopen($myFile, 'w') or die("can't open file");
	    
	    $handle = fopen(self::HOME_DIR . "/database_exp2.csv", "r");
	    
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
    	        } elseif ($lenZip == 8) {
    	            $data[99] = $data[99] . ", " . $zip;
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
    		# remove any duplicate numbers
    		if (!empty($phones)) {
    		    $tmp = array();
    		    foreach ($phones as $i => $arr) {
    		        foreach ($arr as $key => $value) {
    		            if ($key == "phone")
        		            $tmp[$i] = $value;
    		        }
    		    }
    		    
    		    if (count($tmp)>1) {
    		        $uTmp = array_unique($tmp);
    		        if (count($uTmp) != count($tmp)) {
    		            $newPhones = array();
    		            foreach ($uTmp as $i => $arr)
    		                $newPhones[] = $phones[$i];
    		            $phones = $newPhones;
    		        }                    
    		    }    		    
    		}
    		
    		
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
#    	        print_r($query);
#    	        echo "<br>";
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
                                echo "<br />\n";
                                print_r($result);
                    			echo "<br /><br />\n\n";
                    			$db->rollback();
                    			exit();
                    		}
                        }
                    }
                }
    	    }
            # Duplicate
            else {
                if (count($result) > 1) {
                    echo "ERROR ERROR: found more than 1 duplicate<br />\n";
                    print_r($data);
                    echo "<br>";
                    print_r($result);
                    echo "<br><br>";
                }
                
                $row = $result[count($result)-1];
                $familyId = $row['family_id'];
                
                
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
    		
    		// Save babyId
    		fwrite($fh, $rowNum . "," . $babyId . "\n");
    		
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
        
        fclose($fh);
        
        exit();
	}
	
	
	function createStudiesAction() {
	    
	    set_time_limit(300);
	    
	    $handle = fopen(self::HOME_DIR . "/database_study_labs.csv", "r");
        echo self::HOME_DIR . "/database_study_labs.csv" . "<br>\n";
        
        $db = Zend_Registry::get('db');
        		
        $sTbl = new Study();
        $a = $sTbl->getAdapter();
        
        $rTbl = new Researcher();
        $lTbl = new Lab();
        
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
            
            print_r($data);
            echo "<br />";
            
            $study = $data[0];
            $lab = $data[1];
            $researcher = $data[2];
            
            try {
                // Begin transaction
			    $db->beginTransaction();
            
                // Get lab id
                $l = $lTbl->fetchRow($a->quoteInto("lab LIKE ?", $lab));
                if (count($l)!=1) {
                    echo "ERROR";
                    echo "<br />";
                    continue;
                } else {
                    $labId = $l->id;
                }
            
                // Get researcher id
                $select = $rTbl->select();
                $select->where("researcher LIKE ?", $researcher);
                $select->where("lab_id = ?", $labId);
                $r = $rTbl->fetchRow($select);
                if (count($r)>0)
                    $rId = $r->id;
                else {
                    $rId = $rTbl->insert(array(
                        'researcher'    =>  $researcher,
                        'lab_id'        =>  $labId
                    ));
                }
            
                // Add study
                $sTbl->insert(array(
                    'researcher_id'     =>  $rId,
                    'study'             =>  $study
                ));
                
                $db->commit();
            } catch(Exception $e) {
				$db->rollback();
				echo "ERROR: " . $e->getMessage() . "<br />";
			}
            
            echo "<br />";
            
            // Cols 1: Study, 2: Lab, 3: Researcher
#            array_push($studies, $data[0]);
#            array_push($researchers, $data[1] . " - " . $data[2]);
            
            $rowNum++;
        }
        
#        echo "<br />\n";
#        $uStudies = array_unique($studies);
#        print_r($uStudies);
#        echo "<br />\n";
#        echo "<br />\n";
#        $uResearchers = array_unique($researchers);
#        print_r($uResearchers);
        
        exit();  
	}
	
	protected function repString($value, $n) {
	    $arr = array();
	    for ($i=0; $i < $n; $i++)
	       $arr[] = $value;
	    return $arr;
	}
	
	protected function isDate( $Str ) {
      $Stamp = strtotime( $Str );
      $Month = date( 'm', $Stamp );
      $Day   = date( 'd', $Stamp );
      $Year  = date( 'Y', $Stamp );

      return checkdate( $Month, $Day, $Year );
    }
    
    function testAction() {
        echo date('Y-m-d', strtotime("7/22/05"));
        echo "<br />";
        echo date('Y-m-d', strtotime("2/7/05"));
        echo "<br />";
        exit();
    }
	
	
	function addStudyHistoryAction() {
	    
	    set_time_limit(300);
	    
	    $db = Zend_Registry::get('db');
	    
	    $sTbl = new Study();
	    $rTbl = new Researcher();
	    $lTbl = new Lab();
	    $shTbl = new StudyHistory();
        $a = $sTbl->getAdapter();
	    
	    // Get old to new study relationship
	    $handle = fopen(self::HOME_DIR . "/database_studies.csv", "r");
	    $old2new = array();
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
            
            // Get studies (check if exist)
            $tmp = explode("/", $data[1]);
            $labs = explode("/", $data[2]);
            $researchers = explode("/", $data[3]);
            $studies = array();
            for ($i=0; $i < count($tmp); $i++) { 
                $study = $tmp[$i];
                if ($study == "NOTHING") {
                    $studies[] = "NOTHING";
                    continue;
                } else if (empty($study)) {
                    continue;
                }
                
                // Check if study exists
                $sRow = $sTbl->fetchRow($a->quoteInto("study = ?", $study));
                
                // If not, then add study
                if (count($sRow) < 1) {
                    if (empty($researchers[0]) || empty($labs[0])) {
                        $researchers = array("None");
                        $labs = array("None");
                    }
                
                    if ((count($tmp) != count($researchers)) || (count($researchers) != count($labs))) {
                        if (count($researchers) == 1)
                            $researchers = $this->repString($researchers[0], count($tmp));
                        if (count($labs) == 1)
                            $labs = $this->repString($labs[0], count($tmp));
                    }
                        
                    
                    if ((count($tmp) != count($researchers)) || (count($researchers) != count($labs))) {
                        echo "ERROR {$rowNum}: inconsistent count <br />";
                        print_r($data);
                        echo "<br /><br />";
                        continue;
                    }
                    
                    // Add study
                    $researcher = $researchers[$i];
                    $lab = $labs[$i];
                    try {
        			    $db->beginTransaction();
        			    
        			    // Get lab id
                        $l = $lTbl->fetchRow($a->quoteInto("lab LIKE ?", $lab));
                        if (count($l)!=1)
                            throw new Exception("lab {$lab} not found");
                        else
                            $labId = $l->id;
        			    
                        // Get researcher id
                        $select = $rTbl->select();
                        $select->where("researcher LIKE ?", $researcher);
                        $select->where("lab_id = ?", $labId);
                        $r = $rTbl->fetchRow($select);
                        if (count($r)>0)
                            $rId = $r->id;
                        else {
                            $rId = $rTbl->insert(array(
                                'researcher'    =>  $researcher,
                                'lab_id'        =>  $labId,
                                'to_use'        => 0
                            ));
                            echo "Will add researcher: {$researcher} and {$lab}<br />";
                        }        			    
        			    
        			    // Add study
                        $sTbl->insert(array(
                            'researcher_id'     => $rId,
                            'study'             => $study,
                            'to_use'            => 0
                        ));
        			    
    			        $db->commit();
    			        
    			        echo "ADDED: {$study} - {$researcher} - {$lab}<br /><br />";
                    } catch(Exception $e) {
        				$db->rollback();
        				echo "ERROR: " . $e->getMessage() . "<br />";
        				echo $study . " - " . $researcher . " - " . $lab . "<br />";
        				echo "<br />";
        				continue;
        			}
                }
                
                // Study is good
                $studies[] = $sRow->id;
            }
            
            $old2new[strtolower($data[0])] = $studies;
                      
            #foreach ($studyCols as $col) {
            #    array_push($studies, $data[$col]);
            #}
            
            $rowNum++;
        }
        
        
        // Get excel row to baby id link
        $row2id = array();
        $handle = fopen("babies_row2id.csv", "r");
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Trim each column and print onto screen
            $num = count($data);
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }
            
            // Save relationship
            $row2id[$data[0]-1] = $data[1];
        }
        
        // Ok now add study history

        $handle = fopen(self::HOME_DIR . "/database_exp2.csv", "r");
                
        # Different columns from csv
        $studyCols = array(65, 43, 39, 37, 35, 33, 31, 29, 27, 63, 61, 59, 57, 55, 53, 51, 49, 47, 45, 41);
        $studyDateCols = array(64, 42, 38, 36, 34, 32, 30, 28, 26, 62, 60, 58, 56, 54, 52, 50, 48, 46, 44, 40);
        $nStudyCols = count($studyCols);
        
        $rowNum = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($rowNum === 0) {
                $rowNum++;
                continue;
            } else {
                $rowNum++;
            }

            $num = count($data);
            // Trim each column and print onto screen
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }
            
            # Check if row number exists
            if (!array_key_exists($rowNum, $row2id)) {
                echo "ERROR: row {$rowNum} not found<br />";
                print_r($data);
                echo "<br /><br />";
                continue;
            }
            
            // FOR STUDY HISTORY
            
            
            # Get baby id
            $babyId = $row2id[$rowNum];
            
            echo $babyId . " - " . $rowNum . "<br>";
            print_r($row2id);
            exit();
            
            # Outcome Id
            $outcomeId = 1;
            
            # Get study names and dates
            $studies = array();
            $dates = array();
            for ($i=0; $i < $nStudyCols; $i++) {
                $study = strtolower($data[$studyCols[$i]]);
                $appt = $data[$studyDateCols[$i]];
                
                if (!empty($study)) {
                    // Find study in old2new list
                    if (!array_key_exists($study, $old2new)) {
                        echo "ERROR ERROR {$rowNum}: study '{$study}' was not found in old2new<br />";
                        continue;
                    }
                    
                    // Exclude any study with a nothing relationship
                    if ($old2new[$study][0] == "NOTHING")
                        continue;
                    
                    // Set appt if empty
                    if (empty($appt))
                        $appt = "1950-01-01";
                    
                    // Check that appt is a valid date
                    if (!$this->isDate($appt)) {
                        echo "ERROR ERROR {$rowNum}: study '{$study}' does not have a valid date '{$appt}'<br />";
                        continue;
                    }
                    // Convert to a valid date format
                    else {
                        $appt = date('Y-m-d', strtotime($appt));
                    }
                    
                   // Get study ids
                   foreach ($old2new[$study] as $sId) {
                       $studies[] = $sId;
                       $dates[] = $appt;
                   }
                }
            }
            
            
            # Add study histories
            for ($i=0; $i < count($studies); $i++) { 
                $studyId = $studies[$i];
                $appointment = $dates[$i];
                
                if (empty($studyId))
                    continue;
                
                // will need babyid, studies, dates, outcomeId
                try {
                    $shTbl->insert(array(
                        "baby_id"           => $babyId,
                        "study_id"          => $studyId,
                        "appointment"       => $appointment,
                        "study_outcome_id"  => $outcomeId
                    ));
                } catch(Exception $e) {
    				echo "ERROR {$rowNum}: " . $e->getMessage() . "<br />";
    				echo $studyId . " - " . $appointment . " - " . $babyId . "<br />";
#    				echo "<br />";
    				print_r($studies);
    				echo "<br />";
    				continue;
    			}
            }
        }
        
        exit();
	    
	    
	}
	
	function addContactHistoryAction() {
	
	    set_time_limit(600);
	    
	    $db = Zend_Registry::get('db');
	    
	    $chTbl = new ContactHistory();
        $a = $chTbl->getAdapter();
	    
	    // Figure out the row to id situation
        $row2id = array();
        $handle = fopen("babies_row2id.csv", "r");
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Trim each column and print onto screen
            $num = count($data);
            for ($c=0; $c < $num; $c++) { 
                $data[$c] = trim($data[$c]);
            }

            // Save relationship
            $row2id[$data[0]-1] = $data[1]; # took out the -1
        }
        
        // Do the contact history
        $lines = file(self::HOME_DIR . "/database_exp2.htm");

        $nTr = 0;
        $nTh = 0;
        $nTd = 0;
        $inTr = FALSE;
        $nRow = 1;

        $clines = array(114, 127);

        # Caller ID => Unknown
        $callerId = 1;

        foreach ($lines as $line) {
            $nRow++;

            // Start
            if (strpos($line, "<TR>")!==FALSE) {
                $inTr = TRUE;
                $nTr++;
                continue;
            }
            // End
            if (strpos($line, "</TR>")!==FALSE) {
                $inTr = FALSE;
                $nTh = 0;
                $nTd = 0;
                continue;
            }

            if ($inTr && $nTr > 1) {
                if (stripos($line, "<td>")!==FALSE)
                    $nTd++;

                // Got a contact log lines
                if ($nTd == $clines[0] || $nTd == $clines[1]) {
                    // Check if row even exists
                    if (!array_key_exists($nTr, $row2id)) {
                        echo "ERROR: row {$nTr} not found<br />";
                        print_r($line);
                        echo "<br /><br />\n\n";
                        continue;
                    }

                    // Prepare line
                    $line = str_replace("<TD>", "", $line);
                    $line = str_replace("</TD>", "", $line);
                    $line = trim($line);

                    // Check if have anything
                    if (empty($line) || $line == "<BR>")
                        continue;

                    // Split what you do have
                    $items = explode("<BR>", $line);

                    // Shout it out
                    echo "CONTACT:<br />\n";
                    print_r($items);
                    echo "\n<br /><br />\n";

                    // babyId
                    $babyId = $row2id[$nTr];
                    
                    echo $babyId . " - " . $nTr . "<br>";
                    print_r($row2id);
                    exit();

                    // Loop through contact info and add to db
                    # need get babyId, callerId, DATETIME
                    $k = -1;
                    $year = NULL;
                    foreach ($items as $item) {
                        $item = trim($item);
                        $k++;

                        // First get the call date
                        $testDate = str_split(substr($item, 0, 13));
                        $endPt = 10;
                        $nSlashes = 0;
                        for ($i=0; $i < count($testDate); $i++) {
                            if ($testDate[$i] == " " || $testDate[$i] == "-") {
                                if ($testDate[$i+1] == "-")
                                    $endPt = $i + 2;
                                else
                                    $endPt = $i + 1;
                                break;
                            } elseif ($testDate[$i] != "/" && $testDate[$i] != "." && !ctype_digit($testDate[$i])) {
                                $endPt = $i;
                                break;
                            }
                            
                            if ($testDate[$i] == "/" || $testDate[$i] == ".")
                                $nSlashes++;
                        }
                        # parse date
                        $callDate = substr($item, 0, $i);
                        $callDate = str_replace(".", "/", $callDate);
                        
                        if ($nSlashes === 1 || $endPt < 6) {
                            if (empty($year))
                                $callDate = $callDate . "/1950";
                            else
                                $callDate = $callDate . "/${year}";
                        }
                        
                        if ($nSlashes === 0 || !$this->isDate($callDate)) {
                            $callDate = NULL;
                            echo "{$nRow}: no calldate {$callDate} for {$item} using {$i} and {$endPt}<br />\n";
                            $endPt = 0;
                        } else {
                            $callDate = date('Y-m-d', strtotime($callDate));
                            $year = date('Y', strtotime($callDate));
                        }

                        # get remaining item as comment
                        $item = substr($item, $endPt);
                        $comments = trim($item);
                        
#                        echo $items[$k] . "<br />";
#                        echo "$callDate - $item - $comments" . "<br />";
#                        echo "<br>";
                        
                        # add to db!
                        #
                        
                        if (empty($callDate) || empty($comments))
                            continue;
                        
                        try {
                            $toInsert = array(
                                "attempt"           => $chTbl->getAttemptNo($babyId),
                                "baby_id"           => $babyId,
                                "caller_id"         => $callerId
                            );
                            if (!empty($callDate))
                                $toInsert["DATETIME"] = $callDate;
                            if (!empty($comments))
                                $toInsert["comments"] = $comments;
                            $chTbl->insert($toInsert);
                        } catch(Exception $e) {
            				echo "ERROR {$nRow}: " . $e->getMessage() . "<br />";
                            echo "baby - {$babyId}; call - {$callDate}<br />\n";
                            echo "comments: {$comments}<br /><br />\n";
            				continue;
            			}
                    }
                }
            }
        }
        
        exit();
	    
	}
	
	// Set the contact sources
	// add contact sources to table
	function addContactSourcesAction() {
	
	    $db = Zend_Registry::get('db');
		$csTbl = new ContactSource();
	
	    $handle = fopen(self::HOME_DIR . "/database_exp2.csv", "r");

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
	    
	    $handle = fopen(self::HOME_DIR . "/database_exp2.csv", "r");

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
