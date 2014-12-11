<?php 
set_time_limit(0);
ini_set('display_errors','On');
ini_set("allow_url_fopen", true);

// Start with the first result that Europeana finds.
$index = 1;

// Call 96 results starting from index
$responds = call($index);
$total = $responds["totalResults"]; 

// Let's not get let not waste the first 96 respondses
analyse($responds);
$total - 96;

// loop over remaining respondses
while ($total > 0) {
	$index += 96;
	analyse(call($index));
	$total -= 96;
}

// Calls the API for all objects that have direct links, AND SEARCHES FOR MAARTEN AS A TEST SET 
function call ($index) {
	// PUT YOUR API KEY HERE
	$key = "&wskey=FILL IN YOUR EUROPEANA API KEY HERE";
	$api = "http://europeana.eu/api/v2/search.json?";
	// FOR TESTING: Remove the +maarten here to get to the 15 million objects, it now returns ~3000 objects.
	$search = "&rows=96&profile=standard+portal+rich+facets&query=maarten&qf=provider_aggregation_edm_isShownBy:*+TYPE:IMAGE" ;
	$call = $api . $search . "&start=". $index. $key;
	return json_decode(file_get_contents($call), true);
}

// Analyses a serie of works in a Europeana API responds (items)
function analyse ($works) {
	$items = $works['items'];
	foreach ($items as $item) {		
		toOctopus($item);
	}
	
	// FOR TESTING: Exit after first 96
	exit;
}

// Takes a Europeana Europea API result (profile=standard+portal+rich+facets), turns it into a POST request and sent it to Octi.
function toOctopus($data) {
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
		if (array_key_exists('edmLandingPage',$data)) { $url = $data['edmLandingPage'][0]; }
		if (array_key_exists('edmIsShownAt',$data)) { $basedOn = $data['edmIsShownAt'][0]; }
			
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
					  "Authorization: Basic PLACE AUTHORISATION HEREr\n", 
				'method'  => 'POST',
				'content' => $data
			)
		);
		$context  = stream_context_create($options);
		
		// Post information
		$result = file_get_contents($url, false, $context);
		
		// Not doing any POST validation or result validation here :)
}		
?>