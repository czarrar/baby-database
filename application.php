<?php

class ZfApplication
{
    /**
     * The environment state of your current application
 	 * i.e. section to load from config file
     *
     * @var string
     */
    protected $_environment;

    /**
     * Sets the environment to load from configuration file
     *
     * @param string $environment - The environment to set
     * @return void
     */
    public function setEnvironment($environment)
    {
        $this->_environment = $environment;
    }

    /**
     * Returns the environment which is currently set
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->_environment;
    }

    /**
     * Convenience method to bootstrap the application
     *
     * @return mixed
     */
    public function bootstrap()
    {
        if (!$this->_environment) {
            throw new Exception('Please set the environment using ::setEnvironment');
        }

		//try {
			$frontController = $this->initialize();
			
			$this->setupRoutes($frontController);
        	$response = $this->dispatch($frontController);

			$this->render($response);
		//} catch(Exception $error) {
		//	echo nl2br($error);
		//}
    }

    /**
     * Initialization stage, loads configration files, sets up includes paths
     * and instantiazed the frontController
     *
     * @return Zend_Controller_Front
     */
    public function initialize()
    {
		// Set timezone
		date_default_timezone_set('America/New_York');
		
		// Save root directory for project
		define('ROOT_DIR', dirname(__FILE__));
		
        // Set the include path
        set_include_path(
        	ROOT_DIR . '/library'
            . PATH_SEPARATOR . ROOT_DIR . '/app/models'
            . PATH_SEPARATOR . get_include_path()
        );

		// temporary, take out
		require_once 'projax/projax.php';
		
		/* Zend_View */
        require_once 'Zend/View.php';

        /* Zend_Registry */
        require_once 'Zend/Registry.php';

        /* Zend_Config_ini */
        require_once 'Zend/Config/Ini.php';

        /* Zend_Controller_Front */
        require_once 'Zend/Controller/Front.php';

        /* Zend_Controller_Router_Rewrite */
        require_once 'Zend/Controller/Router/Rewrite.php';

		/* Zend_Controller_Action_HelperBroker */
        require_once 'Zend/Controller/Action/HelperBroker.php';

        /* Zend_Controller_Action_Helper_ViewRenderer */
        require_once 'Zend/Controller/Action/Helper/ViewRenderer.php';

        /* Zend_Loader and setup autoloading of classes */
		require_once "Zend/Loader.php";
		Zend_Loader::registerAutoload();
		
		/* Start session */
		Zend_Session::start();

        /*
         * Load the given stage from our configuration file,
         * and store it into the registry for later usage.
         */
        $config = new Zend_Config_Ini(ROOT_DIR . '/app/etc/config.ini', $this->getEnvironment());
        Zend_Registry::set('config', $config);

		/* Save root site path */
		define('SITE_ROOT', $config->site);

		/*
         * Load the database from options in config file,
		 * set the db as 'the default adapter' for Zend_Db_Table,
         * and store the db class into the registry for later usage.
         */
		$db = Zend_Db::factory($config->database->adapter, $config->database->config->toArray());
		Zend_Db_Table::setDefaultAdapter($db);
		Zend_Registry::set('db', $db);
		
		/*
         * Setup layout for whole site,
		 * using options (i.e. layout name, path, etc) from config file
         */
		$layout = Zend_Layout::startMvc($config->layout);
		
		/*
         * Create a *custom* view object with modified paths,
         * and store it into the registry for later usage.
         */
        $view = new Zend_View();
        $view->addHelperPath(ROOT_DIR . '/library/Zarrar/View/Helper', 'Zarrar_View_Helper');
        Zend_Registry::set('view', $view);

		// Add custom view object to the ViewRenderer
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer();
        $viewRenderer->setView($view);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        Zend_Controller_Action_HelperBroker::addHelper(new Zarrar_Controller_Action_Helper_FormCreate());
		Zend_Controller_Action_HelperBroker::addPrefix('Zarrar_Controller_Action_Helper');
		Zend_Controller_Action_HelperBroker::addPath(ROOT_DIR . '/library/Zarrar/Controller/Action/Helper', 'Zarrar_Controller_Action_Helper');

        /*
         * Create an instance of the frontcontroller, 
         * set plug-in where perform authenticatication + set default view variables,
         */
        $frontController = Zend_Controller_Front::getInstance();
		$frontController->registerPlugin(new ControllerPlugin());
        $frontController->throwExceptions((bool) $config->mvc->exceptions);

		// Point frontcontroller to appropriate controller directories
        foreach ($config->modules as $module => $folder) {
            $frontController->addControllerDirectory(ROOT_DIR . "/app/modules/$folder/controllers", $module);
        }

        return $frontController;
    }

    /**
     * Sets up the custom routes
     *
     * @param  object Zend_Controller_Front $frontController - The frontcontroller
     * @return object Zend_Controller_Router_Rewrite
     */
    public function setupRoutes(Zend_Controller_Front $frontController)
    {
        // Retrieve the router from the frontcontroller
        $router = $frontController->getRouter();

        /*
         * You can add routes here like so:
         * $router->addRoute(
         *    'home',
         *    new Zend_Controller_Router_Route('home', array(
         *        'controller' => 'index',
         *        'action'     => 'index'
         *    ))
         * );
         */

        return $router;
    }

    /**
     * Dispatches the request
     *
     * @param  object Zend_Controller_Front $frontController - The frontcontroller
     * @return object Zend_Controller_Response_Abstract
     */
    public function dispatch(Zend_Controller_Front $frontController)
    {
        // Return the response
        $frontController->returnResponse(true);
        $dispatch = $frontController->dispatch();

		return $dispatch;
    }

    /**
     * Renders the response
     *
     * @param  object Zend_Controller_Response_Abstract $response - The response object
     * @return void
     */
    public function render(Zend_Controller_Response_Abstract $response)
    {
        $response->sendHeaders();
        $response->outputBody();
    }
}
