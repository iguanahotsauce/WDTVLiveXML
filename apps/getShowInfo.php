<?php
include_once ('getPosterAPI.php');

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
		if(strtoupper($seasons['type']) == 'TV') {
			if($seasons['seasons'] != 1) {
				$seasons['urlencode'] = urlencode($seasons['name'].' Season ');
			}
			else {
				$seasons['urlencode'] = urlencode($seasons['name']);
			}
		}
		$seasons['selected_button'] = $_POST['selected_button'];
		$seasons_json = json_encode($seasons);
		echo $seasons_json;
		exit;
	}
}