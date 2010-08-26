<?php

class LoginController extends Zend_Controller_Action 
{

	function init()
	{
		$this->view->headerFile = 'header_login.phtml';
		$this->_redirector = $this->_helper->getHelper('Redirector');
	}
	
	protected function _getFormData($posts)
	{
		# Load classes and vars
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$f = new Zend_Filter_StripTags();
		$data = array();
		# Set form vars
		foreach ($posts as $element)
			$data[$element] = $f->filter($this->_request->getPost($element));
		# Return array of form vars
		return $data;
	}

 	public function indexAction()
	{
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
		
		// Get form properties using YAML
		include_once "spyc.php5";
		$config = Spyc::YAMLLoad(ROOT_DIR . '/app/etc/login_form.yaml');
		
		// Declare form
		$form = new Zend_Form($config["login"]);
		
		// Global filters: StringTrim and StripTags
		$form->setElementFilters(array('StringTrim', 'StripTags'));
		
		// Set error messages (ghetto hack, fix)
		$form->username->getValidator('Zend_Validate_NotEmpty')->setMessage('Please enter a username', 'isEmpty');
		$form->password->getValidator('Zend_Validate_NotEmpty')->setMessage('Please enter a password', 'isEmpty');
		
		// Set form decorators (basically don't want any decorators even default ones)
		$form->setElementDecorators(array(
			"ViewHelper",
		    'Errors',
			"Label",
			array("decorator" => array("nothing" => "HtmlTag"), "options" => array("tag" => "empty"))
		));
		$form->submit->setDecorators(array(
	       array(
	           'decorator' => 'ViewHelper',
	           'options' => array('helper' => 'formSubmit'))
	   	));
		
		if ($this->_request->isPost()) {
			// Check
			$isValid = $form->isValid($this->_request->getPost());
			// Get error messages
			$messages = $form->getMessages();
			// Get values
			$data = $form->getValues();
		
			// Proceed to authenticate if all looks good
			if ($isValid) {
				// setup Zend_Auth adapter for a database table  
                $db = Zend_Registry::get('db'); 
		#echo $db->query("SELECT
                $authAdapter = new Zend_Auth_Adapter_DbTable($db); 
                $authAdapter->setTableName('auth'); 
                $authAdapter->setIdentityColumn('username'); 
                $authAdapter->setCredentialColumn('password'); 
                 
                // Set the input credential values to authenticate against 
                $authAdapter->setIdentity($data['username']); 
                $authAdapter->setCredential(md5($data['password']));

		#echo md5($data['password']);
		#exit();

                // do the authentication  
                $auth = Zend_Auth::getInstance(); 
                $result = $auth->authenticate($authAdapter);

		// check validatity
                if ($result->isValid()) {
					# Get row (without password)
					$authRow = $authAdapter->getResultRowObject(null, "password");
					# Set privelages for user
					$privelageOptions = array("admin", "coordinator", "user", "guest");
					if (in_array($authRow->privileges, $privelageOptions)) {
						$_SESSION["user_privelages"] = $authRow->privileges;
						$_SESSION["lab_temp"] = $authRow->lab_id;
						$labName = $db->fetchCol("SELECT lab FROM labs WHERE id= ?", $authRow->lab_id);
						$_SESSION["lab_name"] = $labName[0];
					} else {
						$messages["general"][] = "Login failed. Privelage set in database ($authRow->privelages) is invalid!";
						break;
					}
						
					# Check ip first
					$config = Zend_Registry::get('config');
					if ($config->enableip == 'true' && $formData['username'] != 'admin') {
						$REMOTEIP= getenv('REMOTE_ADDR');
						Zend_Loader::loadClass('IpControl');
						$ipcontrol = new IpControl();
						$where = $ipcontrol->getDefaultAdapter()->quoteInto('allowedIp = ?', $REMOTEIP);
						$row = $ipcontrol->fetchRow($where);
						
						if ($row == NULL) {
							$messages["general"][] = 'Login from an invalid location.  Please use an authorized terminal to login or contact the Coordinator to authorize your workstation.';
							break;
						}
					}
                    // success: store database row to auth's storage 
                    // system. (Not the password though!) 
                    $auth->getStorage()->write($authRow); 
					$this->_redirector->goto('logger');
				} else {
					// Invalid login
					$messages["general"][] = "Login failed, incorrect username and/or password.";
				}
			}
		}
		
		// Set form and messages to view
		$this->view->form = $form;
		$this->view->messages = ($messages) ? $messages : array();		
	}

	public function loggerAction()
	{
		// Get time
		$time_now = date("H:i");
	
		// Check if logintime session has already been set
		if (isset($_SESSION['logintime']))
			$this->_redirector->goto('index', 'index');
			
		// Ghetto fix for formText problem
		$this->view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/HelperFix', 'Zarrar_View_HelperFix');
		
		// Get form properties using YAML
		include_once "spyc.php5";
		$config = Spyc::YAMLLoad(ROOT_DIR . '/app/etc/form_logger.yaml');
		
		// Declare form
		$form = new Zend_Form($config["logger"]);
		
		// Global filters: StringTrim and StripTags
		$form->setElementFilters(array('StringTrim', 'StripTags'));
		
		// Set error messages (ghetto hack, fix)
		$form->caller_id->getValidator('Zend_Validate_NotEmpty')->setMessage('Please select your name from the caller list!', 'isEmpty');
		
		// Set form decorators (basically don't want any decorators even default ones)
		$form->setElementDecorators(array(
			"ViewHelper",
		    'Errors',
			"Label",
			array("decorator" => array("nothing" => "HtmlTag"), "options" => array("tag" => "empty"))
		));
		$form->submit->setDecorators(array(
	       array(
	           'decorator' => 'ViewHelper',
	           'options' => array('helper' => 'formSubmit'))
	   	));
	
		/* Set select options for select caller_id field */
		$labId = $_SESSION["lab_temp"];
		// Get db adapter
		$db = Zend_Registry::get('db');
		// Build query, get callers from a specific lab unless admin or coordinator
		if ($_SESSION["user_privelages"] == "admin" or $_SESSION["user_privelages"] == "coordinator")
			$query = "SELECT id, name FROM callers WHERE name != 'NONE' and to_use = 1";
		else
			$query = "SELECT id, name FROM callers WHERE lab_id = ? and name != 'NONE' and to_use = 1";
		// Execute query
		$stmt = $db->query($query, $labId);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		// Rearrange results as key=>value pair where key = id and value = name
		$callerOptions = array();
		foreach ($rows as $row) {
			$key = $row["id"];
			$value = $row["name"];
			$callerOptions[$key] = $value;
		}
		// Set list options
		$form->caller_id->setMultiOptions($callerOptions);
		
		// Check posted data, if any
		if ($this->_request->isPost()) {
			// Check
			$isValid = $form->isValid($this->_request->getPost());
			// Get error messages
			$messages = $form->getMessages();
			// Get values
			$data = $form->getValues();
	
			// Proceed to authenticate if all looks good
			if ($isValid) {
				// Get login table
				$login = new Logins();
			
				// Get the date
				$cd = getdate();
				$date_now = $cd['mon']."/".$cd['mday']."/".$cd['year'];

				// Record logintime and callers id into session vars
				$_SESSION['logintime'] = $date_now . $time_now . $data['caller_id'];
				$_SESSION['caller_id'] = $data['caller_id'];

				// Insert caller data into login table
				$data = array(
					'caller_id'	=> $data['caller_id'],
					'time'		=> $time_now,
					'sid'		=> $_SESSION['logintime'],
					'logdate'	=> new Zend_Db_Expr('CURDATE()')
				);
				$login->insert($data);
				
				// Delete session data
				unset($_SESSION["lab_temp"]);
				
				// Redirect to index page
				$this->_redirector->goto('', '');
			}
		}
		
		// Set view vars
		$this->view->form = $form;
		$this->view->time_now = $time_now;
	}
	
	public function logoutAction()
	{
		// Check if caller has logged entry
		if (empty($_SESSION['logintime']))
			$this->_redirector->goto('logger');
		
		// Get the auth class
		$auth = Zend_Auth::getInstance();
		$loginTbl = new Logins();
		// Get row with callers unique session id and record time logged out
		$where = $loginTbl->getDefaultAdapter()->quoteInto('sid = ?', $_SESSION['logintime']);
		$loginTbl->update(array("outTime" => new Zend_Db_Expr('NOW()')), $where);
		
		// Clear auth ids and all session vars
		$auth->clearIdentity();
		session_destroy();
		
		$this->_redirector->goto('index', 'login');
	}
}
