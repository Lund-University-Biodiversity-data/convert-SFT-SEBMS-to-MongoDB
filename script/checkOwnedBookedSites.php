<?php
// script that checks if there are unknow sites (already deleted) in the fields ownedSItes/bookedSites of the persons
// can fix the problem by adding the exec parameter

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/checkOwnedBookedSites.php [exec]");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

// get all the persons
$filter = ["hub" => "sft"];
$options = [];
$query = new MongoDB\Driver\Query($filter, $options); 
$rows = $mng->executeQuery("ecodata.person", $query);
$rowsPersons=$rows->toArray();

$persNoOwnedSites=0;
$persNoBookedSites=0;
$arrSitesRecap=array();
echo consoleMessage("info", count($rowsPersons)." person(s) found in hub sft");

foreach($rowsPersons as $person) {
	//echo $site." "; exit();

	if (property_exists($person, "ownedSites")) {
		foreach ($person->ownedSites as $oSite) {
			if (!isset($arrSitesRecap[$oSite])) {
				$arrSitesRecap[$oSite]["toDelete"]=false;
				$arrSitesRecap[$oSite]["items"]=array();
			}
			//echo $oSite."\n";

			$item=array();
			$item["personId"]=$person->personId;
			$item["field"]="ownedSites";
			$arrSitesRecap[$oSite]["items"][]=$item;
		}
	}
	else {
		//echo consoleMessage("warning", "No method ownedSites for person ".$person->personId." - ".$person->firstName." - ".$person->lastName);
		$persNoOwnedSites++;
	}

	if (property_exists($person, "bookedSites")) {
		foreach ($person->bookedSites as $oSite) {
			if (!isset($arrSitesRecap[$oSite])) {
				$arrSitesRecap[$oSite]["toDelete"]=false;
				$arrSitesRecap[$oSite]["items"]=array();
			}
			//echo $oSite."\n";

			$item=array();
			$item["personId"]=$person->personId;
			$item["field"]="bookedSites";
			$arrSitesRecap[$oSite]["items"][]=$item;
		}
	}
	else {
		//echo consoleMessage("warning", "No method ownedSites for person ".$person->personId." - ".$person->firstName." - ".$person->lastName);
		$persNoBookedSites++;
	}

}


echo consoleMessage("info", $persNoOwnedSites." person(s) without property ownedSites (".number_format(100*$persNoOwnedSites/count($rowsPersons), 2)."%)");
echo consoleMessage("info", $persNoBookedSites." person(s) without property bookedSites (".number_format(100*$persNoBookedSites/count($rowsPersons), 2)."%)");

echo consoleMessage("info", count($arrSitesRecap)." distinct site(s) to check");

$eltToDelete=0;
foreach($arrSitesRecap as $siteId => $dataRecap) {

	// check if the site exists
	$filter = ["siteId" => $siteId];
	$options = [];
	$query = new MongoDB\Driver\Query($filter, $options); 
	$rows = $mng->executeQuery("ecodata.site", $query);
	$rowSite=$rows->toArray();

	if (count($rowSite)==0) {

		$arrSitesRecap[$siteId]["toDelete"]=true;
		$eltToDelete++;
		//echo consoleMessage("info", "To delete : site ".$siteId);

	}

}

echo consoleMessage("info", $eltToDelete." items to delete site(s) to check");


$nbModifs=0;
if (isset($argv[1]) && $argv[1]=="exec" && $eltToDelete>0) {

	foreach($arrSitesRecap as $siteId => $dataRecap) {

		if ($dataRecap["toDelete"]) {
			foreach($dataRecap["items"] as $item) {

				
				//echo "toDelete : ".$siteId." / ".$item["personId"]." / ".$item["field"]."\n";
				$nbModifs++;
				
				$bulk = new MongoDB\Driver\BulkWrite;
			  //$filter = [];
			  $filter = ['personId' => $item["personId"]];
			  //print_r($filter);
			  $options =  ['$pull' => [ $item["field"] =>  $siteId ] ];
			  $updateOptions = [];
			  $bulk->update($filter, $options, $updateOptions); 
			  $result = $mng->executeBulkWrite('ecodata.person', $bulk);

			}

		}

	}

	echo consoleMessage("info", $nbModifs." items updated");


}
else {
	echo consoleMessage("info", "You can fix the database by running the command exec");

}

echo consoleMessage("info", "Script ends.");

?>
