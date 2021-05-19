<?php
$database="SFT";
require "lib/config.php";
require "lib/functions.php";
echo consoleMessage("info", "Script start.");

$mng = new MongoDB\Driver\Manager($mongoConnection["url"]); // Driver Object created

$arr_persons=getArrayPersonsFromMongo();

//print_r($arr_persons);

$listProjects=array($commonFields["punkt"]["projectId"]);

$arrayOwner=array();

$nbSitesOwned=0;

foreach ($listProjects as $projectId) {

	$array_mongo_sites=getArraySitesFromMongo ("punkt", $projectId);
	echo consoleMessage("info", count($array_mongo_sites)." sites in project.");


	foreach ($array_mongo_sites as $internalSiteId => $dataSite) {
		//echo $internalSiteId." => ".$dataSite["locationID"]."\n";

		$persnr=substr($internalSiteId, 0, strlen($internalSiteId)-3);

		if (!isset($arr_persons[$persnr]["personId"])) {
			echo consoleMessage("error", "No person in MongoDB with persnr ".$persnr);

			exit();
		}
		else {
			//echo "SET ".$arr_persons[$persnr]["personId"]." owner of ".$dataSite["locationID"]."\n";

			$bulk = new MongoDB\Driver\BulkWrite;
		    //$filter = [];
		    $filter = ['siteId' => $dataSite["locationID"]];
		    //print_r($filter);
		    $options =  ['$set' => ['owner' => $arr_persons[$persnr]["personId"]]];
		    $updateOptions = [];
		    $bulk->update($filter, $options, $updateOptions); 
		    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

		    $arrayOwner[$arr_persons[$persnr]["personId"]]["newsites"][]=$dataSite["locationID"];
		    $arrayOwner[$arr_persons[$persnr]["personId"]]["existingsites"]=$arr_persons[$persnr]["ownedSites"];

		    $nbSitesOwned++;
		}
		
	}

	echo consoleMessage("info", $nbSitesOwned." sites updated with an ownerId. ".number_format($nbSitesOwned*100/count($array_mongo_sites), 2)."%");

}



foreach ($arrayOwner as $owner => $data) {
	if ($data["existingsites"]!="") {

		foreach($data["newsites"] as $site) {
			if (!in_array($site, $data["existingsites"]))
				$data["existingsites"][]=$site;
			else {
				//echo "DEJAAAAAAAA PRESEEEEEEEEEEEEEEEEENT\n";
			}
		}

		$newListOwnedSites=$data["existingsites"];

	}
	else {
		$newListOwnedSites=$data["newsites"];
	}

	$bulk = new MongoDB\Driver\BulkWrite;
    //$filter = [];
    $filter = ['personId' => $owner];
    $options =  ['$set' => ['ownedSites' => $newListOwnedSites]];
    $updateOptions = [];
    $bulk->update($filter, $options, $updateOptions); 
    $result = $mng->executeBulkWrite('ecodata.person', $bulk);
}

echo consoleMessage("info", count($arrayOwner)." persons with field ownedSites updated.");


echo consoleMessage("info", "Script ends.");

?>
