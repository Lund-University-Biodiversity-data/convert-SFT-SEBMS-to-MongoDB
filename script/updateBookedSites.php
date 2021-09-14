<?php
$database="SFT";
$server="PROD";
require dirname(__FILE__)."/../lib/config.php";
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/updateBookedSites.php std");

$arr_protocol=array("std", "natt");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	$protocol=$argv[1];
	$projectId = $commonFields[$protocol]["projectId"];

	// default personId in case the person is missing in the database
	// Mathieu Blanchet's id
	$defaultPersonId = "35af2711-e759-4da9-b0f4-bdd615790289";

	// get all the sites
	$array_mongo_sites=getArraySitesFromMongo ($protocol, $projectId);
	echo consoleMessage("info", count($array_mongo_sites)." sites in project.");

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	$bulk = new MongoDB\Driver\BulkWrite;
    //$filter = [];
    $filter = ['projects' => $projectId];
    $options =  ['$set' => ['bookedBy' => ""]];
    $updateOptions = ['multi' => true];
    $bulk->update($filter, $options, $updateOptions); 
    $result = $mng->executeBulkWrite('ecodata.site', $bulk);
    echo consoleMessage("info", $result->getModifiedCount()." site(s) updated in project ".$protocol);


	$nbSitesBooked=0;
	$nbPersonModified=0;
	// clean all the existing bookedSites
	foreach ($array_mongo_sites as $internalSiteId => $dataSite) {

		// check if someone has already booked it. Remove it if necessary
		$bulk = new MongoDB\Driver\BulkWrite;
	    //$filter = [];
	    $filter = ['bookedSites' => $dataSite["locationID"]];
	    $options =  ['$pull' => ['bookedSites' => $dataSite["locationID"]] ];
	    $updateOptions = ['multi' => false];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.person', $bulk);

	    if (isset($result->nModified) && $result->nModified>0) {
	    	$nbSitesBooked++;
	    	$nbPersonModified++;
		    echo consoleMessage("info", $result->getModifiedCount()." person(s) updated to remove bookedSite ".$dataSite["locationID"]);
	    }

	}

	echo consoleMessage("info", $nbPersonModified. ' person(s) cleaned for a total of '.$nbSitesBooked." site(s) in the project ".$protocol);

	// date now
	$micro_date = microtime();
	$date_array = explode(" ",$micro_date);
	$micsec=number_format($date_array[0]*1000, 0, ".", "");
	$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
	if ($micsec==1000) $micsec=999;
	$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";
	//echo "Date: $date_now_tz\n";

	$nbPersonsToCreate=0;
	$nbUnknownPerson=0;
	$arr_json_person='[';
	$nbSitesBooked=0;
	$nbSitesNotCreated=0;
	$arrSitesNotCreated=array();

	// get SQL bookings
	$fieldKarta="karta";
	$fieldKarta.=($protocol=="natt" ? "tx" :"");

	$qBooking="SELECT * FROM bokning".$protocol. "
				WHERE bokad='JA'
				ORDER BY ".strtolower($fieldKarta);

	$rBooking = pg_query($db_connection, $qBooking);

	$nbSitesToEdit=pg_num_rows($rBooking);
	echo consoleMessage("info", $nbSitesToEdit. ' site(s) booked to be edited');

	if (!$rBooking) die("QUERY:" . consoleMessage("error", pg_last_error()));
	while ($rtBooking = pg_fetch_array($rBooking)) {

	    $okPerson=true;

	    if (trim($rtBooking["persnr"])!="") {

	    	$filter = ['internalPersonId' => $rtBooking["persnr"]];
		    $options = [];
		    $query = new MongoDB\Driver\Query($filter, $options); 
		    $rows = $mng->executeQuery("ecodata.person", $query);

		    $person=$rows->toArray();

		    if (count($person)!=1) {
		    	echo consoleMessage("error", "Unknown person in MongoDb with persnr  ".$rtBooking["persnr"]);
		    	$nbUnknownPerson++;
		    	$okPerson=false;
		    }
		    else {
		    	$personIdFinal=$person[0]->personId;
		    	$internalPersonIdFinal=$person[0]->internalPersonId;
		    }

	    }
	    else {
			$okPerson=false;
	    	$nbPersonsToCreate++;

	    	$personId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

	    	$arr_json_person.='{
				"dateCreated" : ISODate("'.$date_now_tz.'"),
				"lastUpdated" : ISODate("'.$date_now_tz.'"),
				"personId" : "'.$personId.'",
				"firstName" : "'.$rtBooking["fornamn"].'",
				"lastName" : "'.$rtBooking["efternamn"].'",
				"gender" : "",
				"birthDate" : "",
				"email" : "'.$rtBooking["epost"].'",
				"phoneNum" : "",
				"mobileNum" : "",
				"address1" : "",
				"address2" : "",
				"postCode" : "",
				"town" : "",
				"projects": [ 
					"'.$commonFields["std"]["projectId"].'", 
					"'.$commonFields["natt"]["projectId"].'", 
					"'.$commonFields["kust"]["projectId"].'", 
					"'.$commonFields["punkt"]["projectId"].'" 
				],
				"hub" : "sft",
				"internalPersonId" : "TO-BE-CREATED"
			},';

	    	echo consoleMessage("error", "No person found in mongo with name ".$rtBooking["fornamn"]." ".$rtBooking["efternamn"]);

	    	$okPerson=true;
	    	$personIdFinal=$defaultPersonId;
		    $internalPersonIdFinal="850419-9";
		}

	    if ($okPerson) {
	    	
	    	if (isset($array_mongo_sites[$rtBooking[$fieldKarta]])) {
	    		// update the site
		    	$bulk = new MongoDB\Driver\BulkWrite;
			    //$filter = [];
			    $filter = ['siteId' => $array_mongo_sites[$rtBooking[$fieldKarta]]["locationID"]];
			    $options =  ['$set' => ['bookedBy' => $personIdFinal]];
			    $updateOptions = ['multi' => false];
			    $bulk->update($filter, $options, $updateOptions); 
			    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

				if ($result->getModifiedCount()!=1) 
					echo consoleMessage("error", $result->getModifiedCount()." site(s) updated to add bookedBy ".$internalPersonIdFinal."-".$personIdFinal);

				// update the person
				$bulk = new MongoDB\Driver\BulkWrite;
			    //$filter = [];
			    $filter = ['personId' => $personIdFinal];
			    $options =  ['$push' => ['bookedSites' => $array_mongo_sites[$rtBooking[$fieldKarta]]["locationID"] ] ];
			    $updateOptions = ['multi' => false];
			    $bulk->update($filter, $options, $updateOptions); 
			    $result = $mng->executeBulkWrite('ecodata.person', $bulk);

				if ($result->getModifiedCount()==1) $nbSitesBooked++;
				else echo consoleMessage("error", $result->getModifiedCount()." site(s) updated to add bookedBy ".$internalPersonIdFinal."-".$personIdFinal);
	    	}
	    	else {
	    		echo consoleMessage("error", "Unknown site with ".$fieldKarta." => ".$rtBooking[$fieldKarta]);
	    		$nbSitesNotCreated++;
	    		$arrSitesNotCreated[]=$rtBooking[$fieldKarta];
	    	}
	    	
	    }

	}


	echo consoleMessage("info", $nbPersonsToCreate." person(s) to be created with the json file => linked to Mathieu instead");
	echo consoleMessage("info", $nbUnknownPerson." unknown person(s) in Mongo, impossible to link the site");
	echo consoleMessage("info", $nbSitesBooked." site(s) booked (including ".$nbPersonsToCreate." linked to Mathieu)");
	echo consoleMessage("info", $nbSitesNotCreated.' site(s) not created : "'.implode('", "', $arrSitesNotCreated).'"');
	echo consoleMessage("info", $nbSitesToEdit." site(s) supposed to be edited VERSUS booked+sitenotcreated+unknownperson (".($nbSitesBooked+$nbSitesNotCreated+$nbUnknownPerson).")");

	echo consoleMessage("info", "Script ends.");
}
?>
