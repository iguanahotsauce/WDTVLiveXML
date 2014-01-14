<?php
include_once ('getPosterAPI.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$getPoster = getPosterAPI::getInstance();

if($_POST['name']) {
	$name = $_POST['name'];
	if($getPoster->checkName($name)) {
		if($_POST['type'] == 19) {
			$type = 'tv';
		}
		else {
			$type = 'movie';
		}
		$seasons = $getPoster->getSeasons($name, $type);
		if($seasons['seasons'] != 1) {
			$seasons['urlencode'] = urlencode($seasons['name'].' Season ');
		}
		else {
			$seasons['urlencode'] = urlencode($seasons['name']);
		}
		$seasons_json = json_encode($seasons);
		echo $seasons_json;
		exit;
	}
}