<?php
$database="SFT";
$server="PROD";
require dirname(__FILE__)."/../lib/config.php";
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/updateOwnedSitesFromIntranet.php");

$arrayOwner=array();

$nbSitesOwned=0;


$arrSitesOwners=array();

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

echo consoleMessage("info", "connection to ".$mongoConnection[$server]);


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

echo consoleMessage("info", "Script ends.");

?>
