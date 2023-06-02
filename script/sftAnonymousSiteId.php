<?php

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

// sudo apt-get install php-mongodb
$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/sftAnonymousSiteId.php std [exec]");
echo consoleMessage("info", "first try without exec to check if everything is ok");

function addAnonymizedSiteIdInDb($mng, $siteId, $anonymizedId) {

	$bulk = new MongoDB\Driver\BulkWrite;

    $filter = [
    	'siteId' => $siteId
    ];

    $options =  ['$set' => [
    	'adminProperties.anonymizedId' => $anonymizedId
    ]];

    $updateOptions = ['multi' => false];
    $bulk->update($filter, $options, $updateOptions); 
    $result = $mng->executeBulkWrite('ecodata.site', $bulk);


}

$arr_protocol=array("std", "punkt");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	if (isset($argv[2]) && $argv[2]=="exec") {
		$exec=true;
	}
	else $exec=false;

	$protocol=$argv[1];

	$maxAnonymizedId=0;
	$nbEdited=0;

	$projectId=$commonFields[$protocol]["projectId"];

    $filter = [
    	"projects" => $projectId
    ];
	$options = [
		'sort' => [ "adminProperties.anonymizedId" => -1 ],
		'limit' => 1
	];
	$query = new MongoDB\Driver\Query($filter, $options); 

	$rows = $mng->executeQuery("ecodata.site", $query);
	foreach ($rows as $row){
		if (isset($row->adminProperties->anonymizedId)) {
			//echo "MAX:".$row->adminProperties->anonymizedId;
			$maxAnonymizedId=$row->adminProperties->anonymizedId;
		}
	}
	echo consoleMessage("info", $maxAnonymizedId." is the maxAnonymizedId for project ".$projectId);

	if (is_numeric($maxAnonymizedId)) {

		// get all the sites for sft projects

	    $filter = [
	    	"projects" => $projectId,
	        "verificationStatus" => "godkänd",
	        '$or' => [
	            ["adminProperties.anonymizedId" => ['$exists' => false]],
	            ["adminProperties.anonymizedId" => ""],
	    	]
	    ];
		$options = [];
		$query = new MongoDB\Driver\Query($filter, $options); 

		$rows = $mng->executeQuery("ecodata.site", $query);

		$newAnonymizedId=$maxAnonymizedId;
		foreach ($rows as $row){
			$newAnonymizedId++;
			echo consoleMessage("info", "New anonymizedId is ".$newAnonymizedId." for ".$row->name);

			if ($exec && $row->siteId!="") {
				addAnonymizedSiteIdInDb($mng, $row->siteId, $newAnonymizedId);
				$nbEdited++;
			}


		}

		echo consoleMessage("info", $nbEdited." site(s) edited in the database for project ".$projectId);

	}
}

echo consoleMessage("info", "script ends.");
