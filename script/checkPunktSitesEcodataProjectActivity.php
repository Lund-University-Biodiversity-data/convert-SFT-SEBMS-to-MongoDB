<?php
// script that checks if the vinter sites are in the summer list, and vice-versa
// can fix the problem by adding the exec parameter
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/checkPunktSitesEcodataProjectActivity.php sommar [exec]");

$arr_protocol=array("sommar", "vinter");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {


/* check the number of sites in one projectActivity

db.projectActivity.aggregate([
      {
        '$match':{
          name:"Vinterpunktrutt"
        }
      },
      {
        '$addFields': {
          'size': {
            '$size': '$sites'
          }
        }
      }, {
        '$group': {
          '_id': null, 
          'sites_count': {
            '$sum': '$size'
          }
        }
      }
    ])


  */
	$protocol=$argv[1];
	$projectId = $commonFields[$protocol]["projectId"];

	// get all the sites
	$array_mongo_sites=getArraySitesFromMongo ($projectId, $server);
	echo consoleMessage("info", count($array_mongo_sites)." sites in project.");

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

	$siteIdToAdd=array();

	if ($protocol=="sommar") {
		$projectOrigin="Sommarpunktrutt";
		$projectSearch="Vinterpunktrutt";
	} elseif ($protocol=="vinter") {
		$projectOrigin="Vinterpunktrutt";
		$projectSearch="Sommarpunktrutt";
	}
    $filter = ["name"=> $projectOrigin];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 
    $rows = $mng->executeQuery("ecodata.projectActivity", $query);
    $rowsSites=$rows->toArray();

    if (count($rowsSites)==1) {
	    foreach($rowsSites[0]->sites as $site) {
	    	//echo $site." "; exit();

	    	$filter = ["name"=> $projectSearch, "sites"=>$site];
		    $options = [];
		    $query = new MongoDB\Driver\Query($filter, $options); 
		    $rows = $mng->executeQuery("ecodata.projectActivity", $query);
		    $rowSiteFound=$rows->toArray();

		   
		    if (count($rowSiteFound)==0) {
		    	$siteIdToAdd[]=$site;
		    	echo "site not found in ".$projectSearch." : ".$site."\n";
		    }
	    }
    }

	echo consoleMessage("info", count($siteIdToAdd)." site(s) found in ".$projectOrigin." not found in ".$projectSearch);

    if (isset($argv[2]) && $argv[2]=="exec" && count($siteIdToAdd)>0) {
    	$bulk = new MongoDB\Driver\BulkWrite;
	    //$filter = [];
	    $filter = ['name' => $projectSearch];
	    //print_r($filter);
	    $options =  ['$push' => ['sites' => ['$each' => $siteIdToAdd ] ] ];
	    $updateOptions = [];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.projectActivity', $bulk);

		echo consoleMessage("info", "Array pushed");

    }
    else {
		echo consoleMessage("info", "You can push these elements in the database by running the command exec");

    }

	echo consoleMessage("info", "Script ends.");
}
?>
