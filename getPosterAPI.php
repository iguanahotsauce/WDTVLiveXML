<?php
/*
	Show Cover Art API
	http://eve-helper.com/getPosterExample.php
	Alex Beebe (iguanahotsauce2009@gmail.com)
	Updated: 01/10/2014
*/

class getPosterAPI{
	
	// Version
	private static $movieCoverArtAPIVersion = '0.1 Alpha';
	
	// Singleton instance.
	private static $instance;
	
	// This is the search URL for http://www.freecovers.net/
	private $freecoversSearchURL = 'http://freecovers.net/search.php?search=';
	
	// The array to store the closest match we can find
	public $best_match = array('percent'=>0,'key'=>null);
	
	// Search Types
	public $types = array(
		'All' => null,
		'Anime DVD' => 22,
		'Blu-Ray Movie' => 20,
		'DVD Movie' => 1,
		'HD-DVD Movie' => 21,
		'Music CD' => 4,
		'Music DVD' => 11,
		'Other' => 10,
		'PC Apps' => 6,
		'PC Games' => 5,
		'Playstation 3' => 13,
		'PSP' => 15,
		'Soundtrack' => 18,
		'TV Series' => 19,
		'Wii' => 17,
		'Xbox 360' => 12
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
		$urls[] = 'http://www.freecovers.net/search.php?search='.urlencode($name).'&cat='.$type;
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
			}
		}
		
		return $alternative_name;
	}
	
	// Gets the HTML from the given URL with cURL
	public function getHTML($url) {
		// cURL the URL to get the HTML
		$page = $this->curl($url);
		// Call the scrape_between function to get rid of everything that's not between '<div id=\"maincontent\">' and '<td align=\"right\" valign=\"top\"></td>'
		// Then remove all the unwanted HTML from the start and the end of $page with substr
		$data = substr(substr(trim($this->scrape_between($page, "<div id=\"maincontent\">", "<td align=\"right\" valign=\"top\"></td>")), 430, -1), 0, -134);

	    return $data;
	}
	
	// Takes the HTML and converts it into an array, making each row into an element
	public function getRows($data) {
		/// Remove all the unneccessary tags from the HTML
		$data = str_replace($this->removeFromHTML, '', $data);
		// Explode on the '</tr>' tags so we can put each row into an array
		$rows = explode('</tr>', $data);
		$rowsArray = array();
		$i = 0;
		foreach($rows as &$row) {
			$row = trim(strstr($row, '>'));
			$row = substr($row, 1, -1).'>';
			$row = $this->removeFirstElement($row);
			// If $i % 4 == 0 then we are on a row that contains the name of the image
			// Remove the HTML from before the name then use rstrstr to get all the html before the first '<' which gives us the name
			if($i  % 4 == 0) {
				$row = $this->removeFirstElement($row);
				$row = $this->rstrstr($row,'<');
			}
			// If we're not looking a row with a name in it then add '<td>' to the front of the row becasue we stripped it off earlier
			else {
				$row = '<td>'.$row;
			}
			// If the row doesn't contain '<div class="coverDetailsContainer"' and the length is longer than 10 then insert that row into the array
			if(!strstr($row, '<div class="coverDetailsContainer"') && strlen($row) > 10) {
				$rowsArray[] = $row;
			}
		    $i++;
		}
		
		for($j=0;$j<count($rowsArray);$j++) {
			if($j % 2 == 1) {
				$rowsArray[$j] = $rowsArray[$j-1].$rowsArray[$j];
				unset($rowsArray[$j-1]);
			}
		}
		
		return $rowsArray;
	}
	
	// Takes the array of all of the rows and converts it into an array with the show name as the key. Each element contains the name and image link for each image
	public function createElementsArray($rowsArray) {
		$elementsArray = array();
		
		// Loop through each row and then get specific data from the row to create a new more specific array
		foreach($rowsArray as &$element) {
			// Explode each row on the '<td>' element so that we can pull out the specific information
			$row = explode('<td>',$element);
			// If the first element of the $row array is empty then move the element from $row[1] to $row[0] and unset $row[1]
			if($row[0] == '') {
				$row[0] = $row[1];
				unset($row[1]);
			}
			$covers = array();
			// Loop through each of the new row elements in order to find the name and the covers that are in that element
			foreach($row as &$row) {
				// Remove the '<a>' tags from each element because they are not needed
				$row = str_replace($this->removeFromRows,'',$row);
				// Explode each element on '>' to get the specific data
				$row = explode('>',$row);
				// If the length of the new $row array is one then that is the name of the show
				// Set the show name of the title array to this element
				if (count($row) == 1) {
					$title = array('Show Name'=>$row[0]);
				}
				// If the length of the new $row array is greater than one then this element contains all of the image links
				else {
					// Remove the first 31 characters because they are not needed
					$link = substr($row[0],31);
					// Explode on '/' so that we can get the pieces of the url that are needed to create the image url
					$link = explode('/',$link);
					// Unset the last element of the link array because it is not needed to build the url for the image
					unset($link[count($link)-1]);
					// Build the correct url that links directly to the image
					$row[0] = 'http://www.freecovers.net/preview/'.implode('/',$link).'/big.jpg';
					// Set the 'Name of Cover' and the 'Link' in the link array with the new information
					$link = array('Name of Cover'=>$row[1],'Link'=>$row[0]);
					// Set 'Covers' in the covers array to the link array
					$covers['Covers'] = $link;
				}
			}
			// Add each covers array to the elementsArray with the Show Name as the Key
			$elementsArray[$title['Show Name']] = $covers;
		}
		
		return $elementsArray;
	}
	
	// Gets the best match from the elements array
	public function getBestMatch($data, $name, $best_match) {
		// Get the array keys which are the names of the images
		$keys = array_keys($data);
		$i=0;
		
		// Loop through each element in the array so we can compare the user string to the name and find the best match
		foreach($data as $row) {
			// If there are parenthesis in the name then remove everything inside of them so that we can remove the parenthesis as well
	        $string = preg_replace('/\([^)]*\)/', '', $keys[$i]);
	        // Remove all of the Revision numbers and dates from the image name
	        $string = str_replace($this->removeFromName, '', $string);
	        // Use the function simalar_text to compare the user string with the image name in order to see how close of a match it is
	        similar_text(strtoupper($string), strtoupper($name), $percent);
	        $front_cover = false;
	        // Loop through each of the covers and check to make sure that the current element we are looking at contains a cover named 'Front'
	        if(isset($data[$keys[$i]]['Covers'])) {
		        foreach($data[$keys[$i]]['Covers'] as $cover) {
		            if(strtoupper($cover) == 'FRONT') {
		                $front_cover = true;
		            }
		        }
	        }
	        // If the percent match for the current element is higher than the percent match for the stored element and the current element contains a cover named 'Front'
	        // then replace the old data in $best_match with the new data because this is a better match
	        if($percent > $best_match['percent'] && $front_cover) {
	            $best_match['percent'] = $percent;
	            $best_match['key'] = $keys[$i];
	            $best_match['url'] = $data[$keys[$i]]['Covers']['Link'];
	        }
		    $i++;
		}
		
		return $best_match;
	}
	
	// Takes a cover art image and crops it to be only the front of the cover art
	public function createImage($url, $name) {
		// Create the path name for the destination image
		$dest_image = 'images/'.str_replace(' ', '_', $name).'.jpg';
		// Use the imagecreatefromjepg() function to set the $orig_img variable to the image
		$org_img = imagecreatefromjpeg($url);
		// Get the starting point for the image based off of the width of the image so that the cropped image size is 282px X 400px
		$start_x = imagesx($org_img)-282; 
		// Create the new image with the correct dimensions
		$image = imagecreatetruecolor('282','400');
		// Crop the old image to the new size and copy it to the new image
		imagecopy($image,$org_img, 0, 0, $start_x, 0, 282, 400);
		// Create a jpeg file from the new image
		imagejpeg($image,$dest_image,90);
		// Destroy $image because it is no longer needed
		imagedestroy($image);
		
		return $dest_image;
	}
	
	// Site Scraper by Jacob Ward
	// Defining the basic scraping function
	public function scrape_between($data, $start, $end){
	    $data = stristr($data, $start); // Stripping all data from before $start
	    $data = substr($data, strlen($start));  // Stripping $start
	    $stop = stripos($data, $end);   // Getting the position of the $end of the data to scrape
	    $data = substr($data, 0, $stop);    // Stripping all data from after and including the $end of the data to scrape
	    return $data;   // Returning the scraped data from the function
	}
	
	public function removeFirstElement($element) {
		$data = strstr($element, '>');
		$data = substr($data, 1);
		return $data;
	}
	
	public function curl($url) {
	    // Assigning cURL options to an array
	    $options = Array(
	        CURLOPT_RETURNTRANSFER => TRUE,  // Setting cURL's option to return the webpage data
	        CURLOPT_FOLLOWLOCATION => TRUE,  // Setting cURL to follow 'location' HTTP headers
	        CURLOPT_AUTOREFERER => TRUE, // Automatically set the referer where following 'location' HTTP headers
	        CURLOPT_CONNECTTIMEOUT => 120,   // Setting the amount of time (in seconds) before the request times out
	        CURLOPT_TIMEOUT => 120,  // Setting the maximum amount of time for cURL to execute queries
	        CURLOPT_MAXREDIRS => 10, // Setting the maximum number of redirections to follow
	        CURLOPT_USERAGENT => "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1a2pre) Gecko/2008073000 Shredder/3.0a2pre ThunderBrowse/3.2.1.8",  // Setting the useragent
	        CURLOPT_URL => $url, // Setting cURL's URL option with the $url variable passed into the function
	    );
	     
	    $ch = curl_init();  // Initialising cURL 
	    curl_setopt_array($ch, $options);   // Setting cURL's options using the previously assigned array data in $options
	    $data = curl_exec($ch); // Executing the cURL request and assigning the returned data to the $data variable
	    curl_close($ch);    // Closing cURL 
	    return $data;   // Returning the data from the function 
	}
	
}