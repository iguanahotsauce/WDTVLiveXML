<?php
include_once ('getPosterAPI.php');

$getPoster = getPosterAPI::getInstance();

if($_POST) {

	$name = $_POST['show_name'];
	$type = $_POST['type'];


	$ip = $_SERVER['REMOTE_ADDR'];
	$user_agent = $_SERVER['HTTP_USER_AGENT'];
	
	$addUser = array(
		'ip' => $ip,
		'useragent' => $user_agent,
		'search_string' => $name
	);
	
	$getPoster->addUser($addUser);

	$hashArray = $getPoster->createHash($name);

	foreach($hashArray as $hash) {
		$cache_results = $getPoster->checkCache($hash);

		if($cache_results['name'] != null) {
			$bestMatchArray = array(
				'percent' => $cache_results['percent'],
				'key' => $cache_results['name'],
				'image' => $cache_results['image'],
				'url' => $cache_results['url']
				);
			break;
		}
	}
	if(!isset($bestMatchArray)) {

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
		$bestMatchArray['image'] = 'images/'.$image.'.jpg';

		$data = array(
			'hash' => $hashArray[0],
			'best_match_name' => $bestMatchArray['key'],
			'best_match_url' => $bestMatchArray['url'],
			'image_name' => $image,
			'useragent' => $user_agent,
			'ip' => $ip,
			'type' => $type,
			'percent' => $bestMatchArray['percent']
			);

		$getPoster->addData($data);
	}
	
	include('templates/displayPosterResults.html');
}
else {
	$types = $getPoster->getTypes();
	include('templates/getShowInformation.html');
}