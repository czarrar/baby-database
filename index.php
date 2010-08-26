<?php

ini_set('display_errors', 'On');
error_reporting(E_ERROR | E_PARSE);
#error_reporting(16);

header('Content-Type: text/html; charset=utf-8');
require_once 'application.php';

$zfApp = new ZfApplication();

$zfApp->setEnvironment('production');
$zfApp->bootstrap();
