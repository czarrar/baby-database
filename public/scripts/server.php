<?php

require_once 'HTML/AJAX/Server.php';
require_once 'HTML/AJAX/Action.php';

class Phone {
	function greenText($id, $text) {
		$response = new HTML_AJAX_Action();
		#$response->assignAttr($id,'style','color: green');
		$response->createNode($id,'div',array('innerHTML' => $text));
		return $response;
	}
}

$server = new HTML_AJAX_Server();
$server->registerClass(new Phone());
$server->handleRequest();
