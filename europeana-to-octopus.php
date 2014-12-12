<?php 
set_time_limit(0);
ini_set('display_errors','On');
ini_set("allow_url_fopen", true);
header('Content-Type: text/plain');
include_once('http_build_url.php');


// REPLACE THESE VALUES OR OVERWRITE THEM WITH A config.php FILE
$key = "&wskey=FILL IN YOUR EUROPEANA API KEY HERE";
$authorization = "Basic INCLUDE HTTP BASIC AUTH";
if(file_exists('config.php'))
    include 'config.php';

// Start with the first result that Europeana finds.
$index = 1;

// Call 96 results starting from index
$responds = call($index, $key);
$total = $responds["totalResults"]; 

// Let's not get let not waste the first 96 respondses
analyse($responds, $authorization);
$total - 96;

// loop over remaining respondses
while ($total > 0) {
	$index += 96;
	analyse(call($index, $key), $authorization);
	$total -= 96;
}

// Calls the API for all objects that have direct links, AND SEARCHES FOR MAARTEN AS A TEST SET 
function call ($index, $key) {
	$api = "http://europeana.eu/api/v2/search.json?";
	// FOR TESTING: Remove the query=octopus by query=*:* here to get to the 15 million objects, it now returns ~200 objects.
	$search = "&rows=96&profile=standard+portal+rich+facets&query=octopus&qf=provider_aggregation_edm_isShownBy:*+TYPE:IMAGE" ;
	$call = $api . $search . "&start=". $index. $key;
	return json_decode(file_get_contents($call), true);
}

// Analyses a serie of works in a Europeana API responds (items)
function analyse ($works, $authorization) {
	$items = $works['items'];
	foreach ($items as $item) {		
		toOctopus($item, $authorization);
	}
}

// Takes a Europeana Europea API result (profile=standard+portal+rich+facets), turns it into a POST request and sent it to Octi.
function toOctopus($data, $authorization) {
		// init
		$name = "";
		$creator = "";
		$rights = "";
		$description = "";
		$url = "";
		$basedOn = "";
		
		// Fill information if available, leave empty otherwise
		if (array_key_exists('title',$data)) { $name = $data['title'][0]; }
		if (array_key_exists('dcCreator',$data)) { $creator = $data['dcCreator'][0]; }
		if (array_key_exists('rights',$data)) { $rights = $data['rights'][0]; }
		if (array_key_exists('dcDescription',$data)) { $description = $data['dcDescription'][0]; }
		if (array_key_exists('guid',$data)) { $url = $data['guid']; }
		if (array_key_exists('edmIsShownAt',$data)) { $basedOn = $data['edmIsShownAt'][0]; }
		if ($url != "" && $basedOn != "" && filter_var($url, FILTER_VALIDATE_URL) && filter_var($basedOn, FILTER_VALIDATE_URL) ){
			$url = remove_query_from_url($url);
			$basedOn = europeana_redirect_to_url($basedOn);
			echo $basedOn . "\r\n"; 
			// Format information to JSON-collection data
			$data = json_encode(array("template"=>array("data" => array(
				array("name"=>"name", "value" => $name),
				array("name"=>"creator", "value" => $creator),
				array("name"=>"license", "value" => $rights),
				array("name"=>"description", "value" => $description),
				array("name"=>"url", "value" => $url),
				array("name"=>"isBasedOnUrl", "value" => $basedOn)
				))));
			

			// Create POST
			$url = "http://project-octopus.org/reviews/";
			$options = array(
				'http' => array(
				'header'=>"Accept: application/vnd.collection+json\r\n" .
						  "Content-Type: application/vnd.collection+json\r\n" .
						  "Cookie: \r\n" .
						  "Authorization: " . $authorization . "r\n", 
					'method'  => 'POST',
					'content' => $data
				)
			);
			$context  = stream_context_create($options);
		
			// Post information
			$result = file_get_contents($url, false, $context);
			
			// Should be doing POST validation or result validation here :)
		}
}

function europeana_redirect_to_url ($redirect) {
	$match = urldecode(preg_replace("/.*?shownAt=(.*)?&.*/", "$1", $redirect));
	$components = parse_url($match);
	if (array_key_exists('query',$components)) {
		$components['query'] = urlencode($components['query']);
	}
	$match = http_build_url($components);
	return $match;
}

function remove_query_from_url ($url) {
	return preg_replace('/\?.*/', '', $url);	
}
?>