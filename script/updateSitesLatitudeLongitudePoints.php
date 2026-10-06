<?php
// script that check if all the sites created by user have a latitude/longitude specified for each point (bug fixed in biocollect the 6th oktober 2026)
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/updateSitesLatitudeLongitudePoints.php [exec]");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$collection = "ecodata.site";
$siteIdToAdd=array();

$filter = [
	"transectParts" => [ 
		'$elemMatch' => [ 
			"geometry.type" => "Point", 
			'$or' => [ 
				["geometry.decimalLatitude" => ""], 
				["geometry.decimalLongitude" => ""] 
			] 
		] 
	],
	"verificationStatus"=>"godkänd", 
	"status"=>"active"
];
$options = [];
$query = new MongoDB\Driver\Query($filter, $options); 
$rowsSites = $mng->executeQuery($collection, $query);
$rowsSitesArr=$rowsSites->toArray();

$nbUpdate=0;
$nbSitesWithProblems = count($rowsSitesArr); 
echo consoleMessage( "info", $nbSitesWithProblems." site(s) found to fix in ".$collection ); 

if ($nbSitesWithProblems > 0) {

	foreach ($rowsSitesArr as $site) { 
		$transectParts = []; 

		foreach ($site->transectParts as $transectPart) { 
			// Only fix Point transectParts with coordinates and 
			// missing decimal latitude/longitude. 
			if ( isset($transectPart->geometry) && 
				isset($transectPart->geometry->type) && 
				$transectPart->geometry->type == "Point" && 
				isset($transectPart->geometry->coordinates) && 
				count($transectPart->geometry->coordinates) >= 2 && 
				( !isset($transectPart->geometry->decimalLatitude) || $transectPart->geometry->decimalLatitude === "" || !isset($transectPart->geometry->decimalLongitude) || $transectPart->geometry->decimalLongitude === "" ) 
			) {

				$longitude = $transectPart->geometry->coordinates[0]; 
				$latitude = $transectPart->geometry->coordinates[1]; 

				echo consoleMessage( "info", $site->siteId. " - ".$transectPart->name. " : setting longitude=".$longitude. ", latitude=".$latitude ); 
				// Fix the decimal values from the GeoJSON coordinates. 
				$transectPart->geometry->decimalLongitude = $longitude; 
				$transectPart->geometry->decimalLatitude = $latitude; 
			}

			// Keep every transectPart, whether it needed fixing or not. 
			$transectParts[] = $transectPart;
		}

		// Change the database only if the argument exec is specified. 
		if (isset($argv[1]) && $argv[1] == "exec") { 
			$bulk = new MongoDB\Driver\BulkWrite; 
			$filterUpdate = [ 'siteId' => $site->siteId ]; 
			$optionsUpdate = [ '$set' => [ 'transectParts' => $transectParts ] ]; 
			$updateOptions = []; 
			$bulk->update( $filterUpdate, $optionsUpdate, $updateOptions ); 
			$result = $mng->executeBulkWrite( $collection, $bulk ); 
			echo consoleMessage( "info", $site->siteId." updated." ); 
			$nbUpdate++; 
		}
	}
	   
}

echo consoleMessage("info", $nbUpdate." site(s) updated");

echo consoleMessage("info", "Script ends.");

?>
