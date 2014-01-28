<?php
/*
	Show Cover Art API
	http://eve-helper.com/getPosterExample.php
	Alex Beebe (iguanahotsauce2009@gmail.com)
	Updated: 01/10/2014
*/

ini_set('memory_limit','1024M');
include_once('databaseConnection.php');
include_once('simple_html_dom.php');

class getPosterAPI {
	
	// Version
	private static $movieCoverArtAPIVersion = '0.1 Alpha';
	
	// Singleton instance.
	private static $instance;
	
	// This is the search URL for http://www.freecovers.net/
	private $freecoversSearchURL = 'http://freecovers.net/search.php?search=';
	
	// The array to store the closest match we can find
	public $best_match = array('percent'=>0,'key'=>null);
	
	// Search Types
	// For now just show TV Series and Movie
	public $types = array(
		//'All' => null,
		//'Anime DVD' => 22,
		//'Blu-Ray Movie' => 20,
		'Movie' => 1,
		//'HD-DVD Movie' => 21,
		//'Music CD' => 4,
		//'Music DVD' => 11,
		//'Other' => 10,
		//'PC Apps' => 6,
		//'PC Games' => 5,
		//'Playstation 3' => 13,
		//'PSP' => 15,
		//'Soundtrack' => 18,
		'TV Series' => 19,
		//'Wii' => 17,
		//'Xbox 360' => 12
	);
	
	public $widths = array(
		19 => array(
			'width' => 282,
			'height' => 400
			),
		20 => array(
			'width' => 344,
			'height' => 400
			),
		21 => array(
			'width' => 343,
			'height' => 400
		)	
	);
	
	// Dictionary to convert season number to complete season to check for a better match
	public $dictionary = array(
		1 => 'FIRST',
		2 => 'SECOND',
		3 => 'THIRD',
		4 => 'FOURTH',
		5 => 'FIFTH',
		6 => 'SIXTH',
		7 => 'SEVENT',
		8 => 'EIGTH',
		9 => 'NINTH',
		10 => 'TENTH',
		11 => 'ELEVENTH',
		12 => 'TWELFTH',
		13 => 'THIRTEENTH',
		14 => 'FOURTEENTH',
		15 => 'FIFTEENTH',
		16 => 'SIXTEENTH',
		17 => 'SEVENTEENTH',
		18 => 'EIGHTEENTH',
		19 => 'NINETEENTH',
		20 => 'TWENTIETH'
	);
	
	// Array for all the elements we want to remove from the HTML
	public $removeFromHTML = array(
	    '<font size="2">',
	    '</font>',
	    '<b>',
	    '</b>',
	    '<center>',
	    '</center>',
	    '[',
	    ']',
	);
	
	// Array for all of the elements that need to be removed when we are splitting the HTML into rows and putting it into an array
	public $removeFromRows = array(
		'<a href=',
		'</a></td>'
	);
	
	// We want to remove these strings from the name in order to find the best possible match
	public $removeFromName = array(
		'R0',
		'R1',
		'R2',
		'CUSTOM',
		'(',
		')'
	);
	
	public static function GetInstance(){

    	if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}
	
	public function addPageView($ip, $useragent) {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$databaseInfo = $databaseConnection->getDatabaseInfo();
		// Connect to the database with mysqli
		$db = new mysqli($databaseInfo['host'], $databaseInfo['username'], $databaseInfo['password'], $databaseInfo['db']);
		
		$locationData = $this->ipToLocation($ip);
		
		$ip = $db->real_escape_string($ip);
		$useragent = $db->real_escape_string($useragent);
		$country = $db->real_escape_string($locationData['countryCode']);
		$state = $db->real_escape_string($locationData['region']);
		$city = $db->real_escape_string($locationData['city']);
		$lat = $db->real_escape_string($locationData['latitude']);
		$lon = $db->real_escape_string($locationData['longitude']);
		
		// Insert the new user into the Users table
		$query = "
			INSERT INTO
				site_hits
			(
				ip,
				useragent,
				country,
				state,
				city,
				latitude,
				longitude
			)
			VALUES (
				'$ip',
				'$useragent',
				'$country',
				'$state',
				'$city',
				'$lat',
				'$lon'
			)
		";
		// Run the query
		$db->query($query);
	}
	
	public function ipToLocation($ip) {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$databaseInfo = $databaseConnection->getAPIKeys();

		$user = $databaseInfo['locatorhq']['user'];
		$key = $databaseInfo['locatorhq']['key'];
		
		$locationData = json_decode(file_get_contents('http://api.locatorhq.com/?key='.$key.'&user='.$user.'&format=json&ip='.$ip), true);
		
		return $locationData;
	}
	
	public function addUser($data) {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$databaseInfo = $databaseConnection->getDatabaseInfo();
		// Connect to the database with mysqli
		$db = new mysqli($databaseInfo['host'], $databaseInfo['username'], $databaseInfo['password'], $databaseInfo['db']);
		
		$ip = $db->real_escape_string($data['ip']);
		$useragent = $db->real_escape_string($data['useragent']);
		$search_string = $db->real_escape_string($data['search_string']);
		$type = $db->real_escape_string($data['type']);

		// Insert the new user into the Users table
		$query = "
			INSERT INTO
				users
			(
				ip,
				useragent,
				search_string,
				type
			)
			VALUES (
				'$ip',
				'$useragent',
				'$search_string',
				'$type'
			)
		";
		// Run the query
		$db->query($query);
	}
	
	// Creates an md5 hash of the search string and the alternative search string so they can be stored in a database for caching purposes
	public function createHash($name) {
		// Create a hash for the alternative name as well to check and see if that hash is already stored in the database
		$alternative_name = $this->getAlternativeName($name);
		// Replace everything but numbers and letters
		// Set the name to uppercase
		$name = strtoupper(preg_replace('/[^A-Za-z0-9]/','',$name));
		// Create a array with an md5 hash for both the name and the alternate name
		$hashArray = array(
			md5($name),
			md5($alternative_name)
		);
		
		return $hashArray;
	}

	// Checks the database to see if the requested hash has been stored already
	public function checkCache($hash) {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$databaseInfo = $databaseConnection->getDatabaseInfo();
		// Connect to the database with mysqli
		$db = new mysqli($databaseInfo['host'], $databaseInfo['username'], $databaseInfo['password'], $databaseInfo['db']);

		$hash = $db->real_escape_string($hash);
		// Query the database for the given hash to see if there is any stored data
		$query = "
			SELECT
				*
			FROM
				search_results
			WHERE
				hash = '$hash'
			";
		// Get the results of the query
		$results = $db->query($query);
		// Get the number of rows from the results of the query so we can check to see if there was a match
		$row = $results->num_rows;

		$cached_data = array();
		// If the query did return a row from the databse then set the cached_data array
		if($row != 0) {
			// Get the returned data from the query
			$info = $results->fetch_array();
			// Set the information in the cached_data array
			$cached_data = array(
				'name' => $info['best_match_name'],
				'image' => 'images/'.$info['image_name'].'.jpg',
				'url' => $info['best_match_url'],
				'percent' => $info['percent'],
				'category' => $info['category']
			);
		}
		else {
			// If the query did not return anything from the database then we need to set 'name' in the cached_data array to null
			// so that we know we need to get the data from the site
			$cached_data = array('name' => null);
		}

		return $cached_data;	
	}


	// Adds the result data to the database if it doesn't already exist for the search string
	public function addData($data) {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$databaseInfo = $databaseConnection->getDatabaseInfo();
		// Connect to the database with mysqli
		$db = new mysqli($databaseInfo['host'], $databaseInfo['username'], $databaseInfo['password'], $databaseInfo['db']);

		$hash = $db->real_escape_string($data['hash']);
		$name = $db->real_escape_string($data['best_match_name']);
		$url = $db->real_escape_string($data['best_match_url']);
		$image_name = $db->real_escape_string($data['image_name']);
		$useragent = $db->real_escape_string($data['useragent']);
		$ip = $db->real_escape_string($data['ip']);
		$type = $db->real_escape_string($data['type']);
		$percent = $db->real_escape_string($data['percent']);
		$category = $db->real_escape_string($data['category']);

		// Insert the new hash into the search_results table
		$query = "
			INSERT INTO
				search_results
			(
				hash,
				best_match_name,
				best_match_url,
				image_name,
				insert_useragent,
				insert_ip,
				type,
				percent,
				category
			)
			VALUES (
				'$hash',
				'$name',
				'$url',
				'$image_name',
				'$useragent',
				'$ip',
				'$type',
				'$percent',
				'$category'
			)
		";
		// Run the query
		$db->query($query);
	}
	
	public function getSeasons($name, $type = 'tv') {
		// Create a new databaseConnection Instance in order to get the database connection info
		$databaseConnection = databaseConnection::getInstance();
		// Get the databse connection info
		$APIKeys = $databaseConnection->getAPIKeys();
		// Get a json object with the results of the search from the moviedb
		$json = file_get_contents('https://api.themoviedb.org/3/search/'.$type.'?api_key='.$APIKeys['moviedb'].'&query='.urlencode($name));
		// Get the variables from the json object
		$search_array = get_object_vars(json_decode($json));
		// Get the ID of the show from the html object
		$id = get_object_vars($search_array['results'][0]);
		// Set the ID variable
		$id = $id['id'];
		$second_url = 'https://api.themoviedb.org/3/'.$type.'/'.$id.'?api_key='.$APIKeys['moviedb'];
		// Get another json object form the moviedb using the id to get the specific show information
		$json = file_get_contents('https://api.themoviedb.org/3/'.$type.'/'.$id.'?api_key='.$APIKeys['moviedb']);
		// Put the results into a php array
		$show_array = get_object_vars(json_decode($json));
		// Count the number of seasons the show has
		if(isset($show_array['seasons'])) {
			$seasons = count($show_array['seasons']);
		}
		else {
			// If 'seasons' does not exist in the movie then it is a movie
			$seasons = 0;
		}
		// Set the name variable to the returned name to make sure it is 100% correct
		if($type == 'movie') {
			// If we are searching for a movie the name comes back as title instead of name
			$name = $show_array['title'];
		}
		else {
			$name = $show_array['name'];
		}
		// Set a return array with the name and the number of seasons
		$return_values = array(
			'name' => $name,
			'seasons' => $seasons,
			'type' => $type
		);
		
		return $return_values;
	}
	
	// Checks to see if a name contains 'Series' or 'Season'
	public function checkName($name) {
		$get_seasons = true;
		// Do a strstr on the name to see if it contains 'Season' or 'Series' and set the get_seasons variable to false if it does
		if(strstr($name, 'SEASON') || strstr($name, 'SERIES')) {
			$get_seasons = false;
		}

		return $get_seasons;
	}
	
	// This returns they types array so that it can be used in the demo
	public function getTypes() {
		return $this->types;
	}
	
	// Returns the data before the given $needle, reverse of strstr() 
	public function rstrstr($haystack,$needle, $start=0) {
	    return substr($haystack, $start,strpos($haystack, $needle));
	}

	// Gets the URLs that we need to cURL
	public function getURLs($name, $type) {
		$urls = array();
		$name = strtoupper($name);
		// Get the alternative name so we can add the URL for that name as well
		$alternative_name = $this->getAlternativeName($name);
		//Add the URLs to the array so we can get the HTML from them
		$urls[] = 'http://www.freecovers.net/search.php?search='.urlencode($name);//.'&cat='.$type;
		$urls[] = 'http://www.freecovers.net/search.php?search='.urlencode($alternative_name).'&cat='.$type;
		
		// Return the array with all of the URLs
		return $urls;
	}
	
	// Provides an alternative name that can be searched for in order to possibly find a better match
	// Example: Breaking Bad Season 1 returns Breaking Bad The Complete First Season
	// Example: Breaking Bad The Complete First Season returns Breaking Bad Season 1
	public function getAlternativeName($name) {
		// Make the name uppercase just to make sure there are no problems with case sensitivity
		$name = strtoupper($name);
		// Check if 'Season' is in the name so we can make the alternate name 'The Complete Nth Season'
		if(strstr($name, 'SEASON')) {
			// If the name does contain 'Season' then use preg_match to get the season number
			if (preg_match('#\bSEASON (\d+)#', $name, $number)) {
			    $number = (integer)str_replace('SEASON','',$number[0]);
			    // Get the alternate format for the number from the dictionaries array
			    // Example: 1 becomes First
			    $alternative_format = $this->dictionary[$number];
			    // Use str_replace on the original name to create the new alternative name with 'The Complete Nth Season'
			    $alternative_name = str_replace('SEASON '.$number, 'THE COMPLETE '.$alternative_format.' SEASON', $name);
			}
			else {
				$alternative_name = $name;
			}
		}
		// Check if 'Series' is in the name so that we can make the alternate name 'The Complete Nth Series'
		else if(strstr($name, 'SERIES')) {
			// If the name does contain 'Series' then use preg_match to get the series number
			if (preg_match('#\bSERIES (\d+)#', $name, $number)) {
			    $number = (integer)str_replace('SERIES','',$number[0]);
			    // Get the alternate format for the number from the dictionaries array
			    // Example: 1 becomes First
			    $alternative_format = $this->dictionary[$number];
			    // Use str_replace on the original name to create the new alternative name with "The Complete Nth Series'
			    $alternative_name = str_replace('SERIES '.$number, 'THE COMPLETE '.$alternative_format.' SERIES', $number);
			}
			else {
				$alternative_name = $name;
			}
		}
		// Check if 'The Complete' is in the name so that we can make the alternative name
		else if(strstr($name, 'THE COMPLETE')) {
			// Check if 'Season' is in the name so that we can make the alternative name 'Season N'
			if(strstr($name, 'SEASON')) {
				// If the name does contain 'Season' then use preg_match to get the season number
				if (preg_match('#\bTHE COMPLETE (\d))#', $name, $number)) {
					$number = str_replace(array('THE COMPLETE','SEASON'), '', $number[0]);
					// Get the number format for the number from the dictionaries array
					// Example: First becomes 1
					$alternative_format = array_search($number, $this->dictionary);
					// Use str_replace on the original name to create the new alternatice name with 'Season N'
					$alternative_name = 'SEASON '.$alternative_format;
				}
				else {
					$alternative_name = $name;
				}
			}
			// Check if 'Series' is in the name so that we can make the alternative name 'Series N'
			else if(strstr($name, 'SERIES')) {
				// If the name does contain 'Series' then use preg_match to get the series number
				if (preg_match('#\bTHE COMPLETE (\d))#', $name, $number)) {
					$number = str_replace(array('THE COMPLETE','SERIES'), '', $number[0]);
					// Get the number format for the number from the dictionaries array
					// Example: First becomes 1
					$alternative_format = array_search($number, $this->dictionary);
					// Use str_replace on the original name to create the new alternative name with 'Series N'
					$alternative_name = 'SERIES '.$alternative_format;
				}
				else {
					$alternative_name = $name;
				}
			}
			else {
				$alternative_name = $name;
			}
		}
		else {
			$alternative_name = $name;
		}
		
		$alternative_name = str_replace(' ', '', $alternative_name);

		return $alternative_name;
	}
	
	public function GetHTMLObject ($url) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $url,
		    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.08) Gecko/20100914 Firefox/3.6.10',
		));
	
		$resp = curl_exec($curl);
	
		curl_close($curl);
	
		return str_get_html($resp);
	}
	
	function getElements($url) {
		
		$html = $this->GetHTMLObject($url);
		
		$table = $html->find('div#maincontent p table tbody tr');
		
		$elements = array();
		$i = 0;
		foreach($table as $idx => $t) {
			if($idx == 0) continue;
			
			if($idx > 0) {
				if($i % 4 == 0) {
					$name = $t->children(0)->children(0)->plaintext;
					$category = $t->children(2)->children(0)->plaintext;
					$links_array = array();
					foreach($t->children(3)->children(0)->find('a') as $row) {
						$link_name = $row->plaintext;
						$href = $row->href;
						
						$links_array[$link_name] = $href;
					}
					$elements[$name] = array(
						'Category' => $category,
						'Links' => $links_array
					);
				}
				$i++;
			}
		}
		
		return $elements;
	}
	
	// Gets the best match from the elements array
	public function getBestMatch($data, $name, $best_matches = array(), $percent_match = 90, $custom = false) {
		// Get the array keys which are the names of the images
		$keys = array_keys($data);
		$name = strtoupper(preg_replace('/[^A-Za-z0-9]/','',$name));
		$i=0;
		$sampleArray = array();
		$name_season_number = preg_replace('/[^0-9]/', '', $name);

		// Loop through each element in the array so we can compare the user string to the name and find the best match
 		foreach($data as $row) {
 			$custom_check = false;
 			// If there are parenthesis in the name then remove everything inside of them so that we can remove the parenthesis as well
 	        $string = strtoupper(preg_replace('/\([^)]*\)/', '', $keys[$i]));
 			if(strstr($string, 'CUSTOM')) {
 				$string = str_replace('CUSTOM','',$string);
 				$custom_check = true;
 			}
 	        // Remove all of the Revision numbers and dates from the image name
 	        $string = strtoupper(preg_replace('/[^A-Za-z0-9]/','',$string));
 	        
 			$revision_number = substr($string, -1);
 			$string = substr($string, 0, -2);
 			$season_number = preg_replace('/[^0-9]/', '', $string);
 			$season_match = ($name_season_number == $season_number) ? true : false;
	        // Use the function simalar_text to compare the user string with the image name in order to see how close of a match it is
 	        similar_text($string, $name, $percent);
 	        $front_cover = false;
 	        // Loop through each of the covers and check to make sure that the current element we are looking at contains a cover named 'Front'
 	        if(isset($data[$keys[$i]]['Links']['Front'])) {
 		    	$front_cover = true;
 	        }
 			// If custom is set to true then there is a different criteria for the match then if it is set to false
 	        if($custom == true) {
 		        if($percent > $percent_match && $front_cover && $season_match) {
 			       	$best_matches[] = array('name' => $keys[$i], 'percent' => $percent, 'revision' => $revision_number, 'category' => $data[$keys[$i]]['Category'], 'url' => $data[$keys[$i]]['Links']['Front'], 'season_number' => $season_number);
 		        }
 	        }
 	        else {
 		        if($percent > $percent_match && $front_cover && !$custom_check && $season_match) {
 			       	$best_matches[] = array('name' => $keys[$i], 'percent' => $percent, 'revision' => $revision_number, 'category' => $data[$keys[$i]]['Category'], 'url' => $data[$keys[$i]]['Links']['Front'], 'season_number' => $season_number);
 		        }
 		    }
 		    $i++;
 		}
		
		// Check to see if there is a match, if not then call the function again with different parameters
 		if(!isset($best_matches) || count($best_matches) == 0) {
 			if($percent_match > 80) {
 		 		$best_matches = $this->getBestMatch($data, $name, $best_matches, 80, false);
 		 	}
 		 	if(count($best_matches) == 0){
 		 		$best_matches = $this->getBestMatch($data, $name, $best_matches, 90, true);
 		 	}
 		}

 		// Set percent equal to 0 so the first element in the array overwrites the current $best_match value
 		$best_match = array('percent' => 0);
 		$perfect_match = array();
 		// Loop through each pf the best matches to find the one with the highest percent match
 		foreach($best_matches as $match) {
	 		if($match['percent'] > $best_match['percent']) {
		 		$best_match = $match;
	 		}
	 		// If there are multiple matches that are a 100% match then add them to the perfect_match array
	 		else if($match['percent'] == 100) {
		 		$perfect_match[] = $match;
	 		}
 		}	
 		// If the count of the perfect match array != 0 then that means there are multiple perfect matches
 		if(count($perfect_match) != 0) {
 			// Add the best match to the perfect_match array because it is a perfect match
 			$perfect_match[] = $best_match;
 			// Set the highest revision to 0 so the first element in the perfect_match array overwrites it
 			$highest_revision = 0;
 			// Loop through each of the elements in the perfect match array to find the element with the highest revision number
 			foreach($perfect_match as $match) {
	 			if($match['revision'] > $highest_revision) {
		 			$highest_revision = $match['revision'];
	 			}
 			}
 			$final_matches = array();
 			// Loop through the perfect match array again and add all of the elements that have the highest revision to the final_matches array
 			foreach($perfect_match as $match) {
	 			if($match['revision'] == $highest_revision) {
		 			$final_matches[] = $match;
	 			}
 			}
 			// Loop through the final_matches array to see if one of the elements is not a Blu-Ray
 			if(count($final_matches) > 1) {
 				$best_match = array();
	 			foreach($final_matches as $match) {
		 			if(strtoupper($match['category']) == 'TV SERIES') {
			 			$best_match = $match;
			 			break;
		 			}
	 			}
	 			if(count($best_match) == 0) {
		 			$best_match = $final_matches[0];
	 			}
 			}
 		}

 		// Create the image url
 		$url = explode('/view',$best_match['url']);
 		$url = explode('/', $url[1]);
 		$best_match['url'] = 'http://www.freecovers.net/preview/'.$url[1].'/'.$url[2].'/big.jpg';

		return $best_match;
		
	}
	
	// Takes a cover art image and crops it to be only the front of the cover art
	public function createImage($url, $name, $type = 'DVD') {
		// If the image is of a Blu-Ray cover then it is wider than a normal DVD cover so set the width accordingly
		if($type == 'Blu-Ray Movie') {
			$width = 344;
		}
		else {
			$width = 282;
		}
		// Remove all characters besides letters and numbers
		$image_name = strtoupper(preg_replace('/[^A-Za-z0-9]/','',$name));
		// Create the location for the new image
		$dest_image = '../images/'.$image_name.'.jpg';
		// Use the imagecreatefromjepg() function to set the $orig_img variable to the image
		$org_img = imagecreatefromjpeg($url);
		// Get the starting point for the image based off of the width of the image so that the cropped image size is 282px X 400px
		$start_x = imagesx($org_img)-$width; 
		// Create the new image with the correct dimensions
		$image = imagecreatetruecolor($width,'400');
		// Crop the old image to the new size and copy it to the new image
		imagecopy($image,$org_img, 0, 0, $start_x, 0, $width, 400);
		// Create a jpeg file from the new image
		imagejpeg($image,$dest_image,90);
		// Destroy $image because it is no longer needed
		imagedestroy($image);
		
		return $image_name;
	}
	
	public function removeFirstElement($element) {
		$data = strstr($element, '>');
		$data = substr($data, 1);
		return $data;
	}
}