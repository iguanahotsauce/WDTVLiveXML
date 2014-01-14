<?php
include_once ('getPosterAPI.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$getPoster = getPosterAPI::getInstance();

$exculude_ips = array(
	'65.121.85.2',
	'76.27.250.101'
);

$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

if(!in_array($ip, $exculude_ips)) {
	$getPoster->addPageView($ip, $user_agent);
}

$types = $getPoster->getTypes();
include('./templates/getShowInformation.html');