<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/removeLastSpaceSite.php iwc");


$arr_protocol=array("iwc", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	$protocol=$argv[1];
	$projectId = $commonFields[$protocol]["projectId"];

	// get all the sites
	$array_mongo_sites=getArraySitesFromMongo ($projectId, $server);
	echo consoleMessage("info", count($array_mongo_sites)." sites in project.");

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

	$arrSiteToEdit=array();

	$nbSitesChecked=0;
	$nbSiteModified=0;
	// clean all the existing bookedSites
	foreach ($array_mongo_sites as $internalSiteId => $dataSite) {

		$nbSitesChecked++;

		//echo $dataSite["name"];
		if (strlen($dataSite["name"])!=strlen(trim($dataSite["name"]))) {
			$newSiteName=trim($dataSite["name"]);
		    echo consoleMessage("info", "length ".strlen($dataSite["name"])." instead of ".strlen($newSiteName).". => new site name : ".$newSiteName);

			$bulk = new MongoDB\Driver\BulkWrite;
		    $filter = ['siteId' => $dataSite["locationID"]];
		    $options =  ['$set' => ['name' => $newSiteName ]];
		    $updateOptions = ['multi' => false];
		    $bulk->update($filter, $options, $updateOptions); 
		    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

		    if ($result->getModifiedCount()==1) {
		    	$nbSiteModified++;
			    echo consoleMessage("info", "site ".$dataSite["locationID"]." updated to ".$newSiteName);
		    }
		    else {
			    echo consoleMessage("error", $result->getModifiedCount()."not updated: ".$dataSite["locationID"]);
		    }


		}

	}

	echo consoleMessage("info", $nbSitesChecked. " site(s) checked");
	echo consoleMessage("info", $nbSiteModified. " site(s) modified");

}
echo consoleMessage("info", "Script ends.");

?>
