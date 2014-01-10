<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$types = array(
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

if($_POST) {
	$dictionary = array(
		1 => 'first',
		2 => 'second',
		3 => 'third',
		4 => 'fourth',
		5 => 'fifth',
		6 => 'sixth',
		7 => 'seventh',
		8 => 'eigth',
		9 => 'ninth',
		10 => 'tenth',
		11 => 'eleventh',
		12 => 'twelfth',
		13 => 'thirteenth',
		14 => 'fourteenth',
		15 => 'fifteenth',
		16 => 'sixteenth',
		17 => 'seventeenth',
		18 => 'eighteenth',
		19 => 'nineteenth',
		20 => 'twentyieth'
	);
	$scrape_urls = array();
	$get_type = $_POST['type'];
	$search_string = strtoupper($_POST['show_name']);
	$scrape_urls[] = 'http://www.freecovers.net/search.php?search='.str_replace(' ','+',$search_string).'&cat='.$get_type;
	if(strstr($search_string, 'SEASON')) {
		if (preg_match('#\bSEASON (\d+)#', $search_string, $matches)) {
		    $matches = (integer)str_replace('SEASON','',$matches[0]);
		    $season_name = $dictionary[$matches];
		    $scrape_urls[] = 'http://www.freecovers.net/search.php?search='.str_replace(' ','+',strtoupper(str_replace('SEASON '.$matches, 'THE COMPLETE '.$season_name.' SEASON', $search_string))).'&cat='.$get_type;
		}
	}
	else if(strstr($search_string, 'SERIES')) {
		if (preg_match('#\bSERIES (\d+)#', $search_string, $matches)) {
		    $matches = (integer)str_replace('SERIES','',$matches[0]);
		    $season_name = $dictionary[$matches];
		    $scrape_urls[] = 'http://www.freecovers.net/search.php?search='.str_replace(' ','+',strtoupper(str_replace('SERIES '.$matches, 'THE COMPLETE '.$season_name.' SERIES', $search_string))).'&cat='.$get_type;
		}
	}
	
	// Defining the basic scraping function
	function scrape_between($data, $start, $end){
	    $data = stristr($data, $start); // Stripping all data from before $start
	    $data = substr($data, strlen($start));  // Stripping $start
	    $stop = stripos($data, $end);   // Getting the position of the $end of the data to scrape
	    $data = substr($data, 0, $stop);    // Stripping all data from after and including the $end of the data to scrape
	    return $data;   // Returning the scraped data from the function
	}
	function rstrstr($haystack,$needle, $start=0) {
	    return substr($haystack, $start,strpos($haystack, $needle));
	}
	function removeFirstElement($element) {
		$data = strstr($element, '>');
		$data = substr($data, 1);
		return $data;
	}
	function curl($url) {
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
	
	$remove = array(
	    '<font size="2">',
	    '</font>',
	    '<b>',
	    '</b>',
	    '<center>',
	    '</center>',
	    '[',
	    ']',
	);
	$replace = array(
		'<a href=',
		'</a></td>'
	);
	$best_match = array('percent'=>0,'key'=>null);
	foreach($scrape_urls as $url) {
		$scraped_page = curl($url);
		$scraped_data = substr(substr(trim(scrape_between($scraped_page, "<div id=\"maincontent\">", "<td align=\"right\" valign=\"top\"></td>")), 430, -1), 0, -134);
		$scraped_data = str_replace($remove, '', $scraped_data);
		$rows = explode('</tr>', $scraped_data);
		$tr = array();
		$inner = array();
		$i = 0;
		foreach($rows as &$row) {
			$row = trim(strstr($row, '>'));
			$row = substr($row, 1, -1).'>';
			$row = removeFirstElement($row);
			if($i  % 4 == 0) {
				$row = removeFirstElement($row);
				$row = rstrstr($row,'<');
			}
			else {
				$row = '<td>'.$row;
			}
			if(!strstr($row, '<div class="coverDetailsContainer"') && strlen($row) > 10) {
				$inner[] = $row;
			}
		    $i++;
		}
		$j = 0;
		for($j=0;$j<count($inner);$j++) {
			if($j % 2 == 1) {
				$inner[$j] = $inner[$j-1].$inner[$j];
				unset($inner[$j-1]);
			}
		}
		$outer = array();
		foreach($inner as &$element) {
			$row = explode('<td>',$element);
			if($row[0] == '') {
				$row[0] = $row[1];
				unset($row[1]);
			}
			$covers = array();
			foreach($row as &$row) {
				$row = str_replace($replace,'',$row);
				$row = explode('>',$row);
				if (count($row) == 1) {
					$title = array('Show Name'=>$row[0]);
				}
				else {
					$link = substr($row[0],31);
					$link = explode('/',$link);
					unset($link[count($link)-1]);
					$row[0] = 'http://www.freecovers.net/preview/'.implode('/',$link).'/big.jpg';
					$link = array('Name of Cover'=>$row[1],'Link'=>$row[0]);
					$covers['Covers'] = $link;
				}
				
			}
			$outer[$title['Show Name']] = $covers;
		}
		
		$keys = array_keys($outer);
		
		$i=0;
		$remove = array(
		'R0',
		'R1',
		'R2',
		'CUSTOM',
		'(',
		')'
		    );
		foreach($outer as $row) {
	        $string = preg_replace('/\([^)]*\)/', '', $keys[$i]);
	        $string = str_replace($remove, '', $string);
	        similar_text(strtoupper($string), strtoupper($search_string), $percent);
	        $front_cover = false;
	        if(isset($outer[$keys[$i]]['Covers'])) {
		        foreach($outer[$keys[$i]]['Covers'] as $cover) {
		            if(strtoupper($cover) == 'FRONT') {
		                $front_cover = true;
		            }
		        }
	        }
	        if($percent > $best_match['percent'] && $front_cover) {
	            $best_match['percent'] = $percent;
	            $best_match['key'] = $keys[$i];
	            $best_match['url'] = $outer[$keys[$i]]['Covers']['Link'];
	        }
		    $i++;
		}
	}
	$image = $best_match['url']; // the image to crop
	$dest_image = 'images/'.str_replace(' ', '_', $search_string).'.jpg'; // make sure the directory is writeable
	
	$org_img = imagecreatefromjpeg($image);
	$width = imagesx($org_img)-282; 
	$img = imagecreatetruecolor('282','400');
	$ims = getimagesize($image);
	imagecopy($img,$org_img, 0, 0, $width, 0, 282, 400);
	imagejpeg($img,$dest_image,90);
	imagedestroy($img);
}

include('templates/poster.html');