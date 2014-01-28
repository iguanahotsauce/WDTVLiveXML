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

if($_POST['getData']) {
	$name = $_POST['start_name'];
	$type = $_POST['type'];
	
	$addUser = array(
		'ip' => $ip,
		'useragent' => $user_agent,
		'search_string' => $name,
		'type' => $type
	);
	
	if(!in_array($ip, $exculude_ips)) {
		$getPoster->addUser($addUser);
	}
	

	$hashArray = $getPoster->createHash($name);

	foreach($hashArray as $hash) {
		$cache_results = $getPoster->checkCache($hash);

		if($cache_results['name'] != null) {
			$bestMatchArray = array(
				'percent' => $cache_results['percent'],
				'key' => $cache_results['name'],
				'image' => $cache_results['image'],
				'url' => $cache_results['url'],
				'type' => $cache_results['category']
				);
			break;
		}
	}

	if(!isset($bestMatchArray)) {

		$urls = $getPoster->getURLs($name, $type);
			
		// For right now just check the first URL so the load times don't increase
		//foreach($urls as $url) {
			$elements = $getPoster->getElements($urls[0]);
			$best_match = $getPoster->getBestMatch($elements, $name);
			$bestMatchArray['percent'] = $best_match['percent'];
			$bestMatchArray['key'] = $best_match['name'];
			$bestMatchArray['url'] = $best_match['url'];
			$bestMatchArray['type'] = $best_match['category'];
		//}

		$image = $getPoster->createImage($bestMatchArray['url'], $name, $best_match['category']);
		$bestMatchArray['image'] = 'images/'.$image.'.jpg';
		

		$data = array(
			'hash' => $hashArray[0],
			'best_match_name' => $bestMatchArray['key'],
			'best_match_url' => $bestMatchArray['url'],
			'image_name' => $image,
			'useragent' => $user_agent,
			'ip' => $ip,
			'type' => $type,
			'percent' => $bestMatchArray['percent'],
			'category' => $bestMatchArray['type']
		);

		$getPoster->addData($data);
	}
	if(strtoupper($bestMatchArray['type']) == 'DVD MOVIE') {
		$bestMatchArray['season_number'] = 0;
	}
	else {
		$bestMatchArray['season_number'] = $best_match['season_number'];
	}
	
	$json = json_encode($bestMatchArray);
	echo $json;
	exit;
}