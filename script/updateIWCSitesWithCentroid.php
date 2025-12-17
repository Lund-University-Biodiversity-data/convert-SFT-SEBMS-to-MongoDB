<?php
// script that et all the IWC sites without centroid and add it
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/updateIWCSitesWithCentroid.php [exec]");

$protocol="iwc";
$projectId = $commonFields[$protocol]["projectId"];

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$siteIdToAdd=array();

$filter = [
	"projects"=> $projectId,
	"transectParts" => [], 
	"verificationStatus"=>"godkänd", 
	"status"=>"active",
	"siteId" => "9946cdaa-7745-0b78-8631-108e351c8e7f"
];
$options = [];
$query = new MongoDB\Driver\Query($filter, $options); 
$rowsSites = $mng->executeQuery("ecodata.site", $query);
$rowsSitesArr=$rowsSites->toArray();

$nbUpdate=0;
echo consoleMessage("info", count($rowsSitesArr)." site(s) in project.");

if (count($rowsSitesArr)>0) {
    foreach($rowsSitesArr as $site) {
    	$transectParts=array([
    		"displayProperties" => array(
    			"habitat" => [],
    			"detail" => [],
    		),
    		"name" => "Punkt 1",
    		"length" => null,
    		"description" => "",
    		"geometry" => array(
    			"coordinates" => array(
    				$site->extent->geometry->decimalLongitude,
    				$site->extent->geometry->decimalLatitude,
    			),
    			"decimalLongitude" => $site->extent->geometry->decimalLongitude,
    			"decimalLatitude" => $site->extent->geometry->decimalLatitude,
    			"type" => "Point"
    		),
    		"type" => "",
    		"transectPartId" => generate_uniqId_format(),
    	]);
    	print_r($transectParts);

    	if (isset($argv[1]) && $argv[1]=="exec") {
			$bulk = new MongoDB\Driver\BulkWrite;
		    //$filter = [];
		    $filter = ['siteId' => $site->siteId];
		    //print_r($filter);
		    $options =  ['$set' => ['transectParts' => $transectParts ]];
		    $updateOptions = [];
		    $bulk->update($filter, $options, $updateOptions); 
		    $result = $mng->executeBulkWrite('ecodata.site', $bulk);
    		
    		echo "updated :".$site->siteId;

		    $nbUpdate++;
    	}
    }
   
}

echo consoleMessage("info", $nbUpdate." site(s) updated");

echo consoleMessage("info", "Script ends.");

?>
