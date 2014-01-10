<?php
include_once ('getPosterAPI.php');

$getPoster = getPosterAPI::getInstance();

if($_POST) {
	$name = $_POST['show_name'];
	$type = $_POST['type'];
	
	$urls = $getPoster->getURLs($name, $type);
	$bestMatchArray = array('percent'=>0,'key'=>null);
	
	foreach($urls as $url) {
		$html = $getPoster->getHTML($urls[0]);
		$rows = $getPoster->getRows($html);
		$elements = $getPoster->createElementsArray($rows);
		$best_match = $getPoster->getBestMatch($elements, $name, $bestMatchArray);
		$bestMatchArray['percent'] = $best_match['percent'];
		$bestMatchArray['key'] = $best_match['key'];
		$bestMatchArray['url'] = $best_match['url'];
	}
	
	$image = $getPoster->createImage($bestMatchArray['url'], $name);
	
	include('templates/displayPosterResults.html');
}
else {
	$types = $getPoster->getTypes();
	include('templates/getShowInformation.html');
}