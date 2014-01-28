<?php
include_once ('getPosterAPI.php');

$getPoster = getPosterAPI::getInstance();

$exculude_ips = array(
	'65.121.85.2',
	'76.27.250.101'
);

$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

if(isset($_POST) && !empty($_POST)) {
	array_unshift($_POST, $ip);
	file_put_contents('who_da_fuk_is_this.log', print_r($_POST , true), FILE_APPEND);
}
if(isset($_GET) && !empty($_GET)) {
	array_unshift($_GET, $ip);
	file_put_contents('who_da_fuk_is_this.log', print_r($_GET , true), FILE_APPEND);
}

//if(!in_array($ip, $exculude_ips)) {
	$getPoster->addPageView($ip, $user_agent);
//}

$types = $getPoster->getTypes();
include('./templates/getShowInformation.html');