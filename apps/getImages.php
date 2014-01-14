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

		//$getPoster->addData($data);
	 }
	$bestMatchArray['season_number'] = $_POST['season_number'];
	
	$json = json_encode($bestMatchArray);
	echo $json;
	exit;
}