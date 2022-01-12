<?php
$database="SFT";
$server="PROD";
require dirname(__FILE__)."/../lib/config.php";
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/updateOwnedSites.php");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$arr_persons=getArrayPersonsFromMongo();

//print_r($arr_persons);


$arrayOwner=array();

$nbSitesOwned=0;


$arrSitesOwners=array();

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

if ($mng) {


	// get all the sites
    $filter = [];
    $filter = ["dataOrigin" => "scriptSitePunktIntranet", "verificationStatus"=>"godkänd", "status"=>"active"];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 
    $rows = $mng->executeQuery("ecodata.site", $query);

    foreach ($rows as $row){

    	if (isset($row->owner) && trim($row->owner)!=0) {
	    	$arrSitesOwners[$row->siteId]=$row->owner;
    	}
	}

	echo consoleMessage("info", count($arrSitesOwners)." sites with owners.");


	// get the persons with owned sites

	$filter = [];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 
    $rows = $mng->executeQuery("ecodata.person", $query);

    foreach ($rows as $row){

    	$arrayOwner[$row->personId]=(isset($row->ownedSites) ? $row->ownedSites : array());
	}
	echo consoleMessage("info", count($arrayOwner)." persons with field ownedSites obtained.");



	// now we compare the owner field with ownedSites


	foreach ($arrSitesOwners as $siteId => $owner) {

		echo "site $siteId owned by $owner \n";

		if (!isset($arrayOwner[$owner])) {
			echo consoleMessage("error", "No person with id $owner in the array");

		}
		else {
			if (in_array($siteId, $arrayOwner[$owner])) {
				echo "ok\n";
			}
			else {

				echo consoleMessage("info", "Add personId $owner for site $siteId");

				$bulk = new MongoDB\Driver\BulkWrite;
			    //$filter = [];
			    $filter = ['personId' => $owner];
			    $options =  ['$push' => ['ownedSites' => $siteId]];
			    $updateOptions = [];
			    $bulk->update($filter, $options, $updateOptions); 
			    $result = $mng->executeBulkWrite('ecodata.person', $bulk);

			    $nbSitesOwned++;
			}
		}

	}

	echo consoleMessage("info", $nbSitesOwned." sites updated with a new owner.");


}


/*
foreach ($listProjects as $projectId) {

	$array_mongo_sites=getArraySitesFromMongo ("punkt", $projectId);
	echo consoleMessage("info", count($array_mongo_sites)." sites in project.");


	foreach ($array_mongo_sites as $internalSiteId => $dataSite) {
		//echo $internalSiteId." => ".$dataSite["locationID"]."\n";

		$persnr=substr($internalSiteId, 0, strlen($internalSiteId)-3);

		if (!isset($arr_persons[$persnr]["personId"])) {
			echo consoleMessage("error", "No person in MongoDB with persnr ".$persnr);

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

*/
echo consoleMessage("info", "Script ends.");

?>
