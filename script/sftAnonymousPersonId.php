<?php

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/sftAnonymousPersonId.php sft [exec]");
echo consoleMessage("info", "first try without exec to check if everything is ok");

$tmpfname = "script/excel/sft_anonymized_persons.xlsx";

function addAnonymizedPersonIdInDb($mng, $internalPersonId, $anonymizedId) {

	$bulk = new MongoDB\Driver\BulkWrite;

    $filter = [
    	'internalPersonId' => $internalPersonId
    ];

    $options =  ['$set' => [
    	'anonymizedId' => $anonymizedId
    ]];

    $updateOptions = ['multi' => false];
    $bulk->update($filter, $options, $updateOptions); 
    $result = $mng->executeBulkWrite('ecodata.person', $bulk);


}

$arr_hub=array("sft");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_hub)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_hub));
}
else {

	if (isset($argv[2]) && $argv[2]=="exec") {
		$exec=true;
	}
	else $exec=false;

	$hub=$argv[1];


    $filter = [
    	
    ];
	$options = [
		'sort' => [ "anonymizedId" => -1 ],
		'limit' => 1
	];
	$query = new MongoDB\Driver\Query($filter, $options); 

	$rows = $mng->executeQuery("ecodata.person", $query);
	foreach ($rows as $row){
		echo "MAX:".$row->anonymizedId;
		$maxAnonymizedId=$row->anonymizedId;
	}
	echo consoleMessage("info", $maxAnonymizedId." is the maxAnonymizedId.");

	if (is_numeric($maxAnonymizedId)) {

		// get all the persons from sft

    $filter = [
    	"hub" => $hub,
    	'$or' => [
    		['anonymizedId' => ""],
    		['anonymizedId' => [ '$exists' => false ]]
    	]
    ];
		$options = [];
		$query = new MongoDB\Driver\Query($filter, $options); 

		$rows = $mng->executeQuery("ecodata.person", $query);

		$newAnonymizedId=$maxAnonymizedId;
		foreach ($rows as $row){
			$newAnonymizedId++;
			echo consoleMessage("info", "New anonymizedId is ".$newAnonymizedId." for ".$row->firstName." ".$row->lastName);

			if ($exec && $row->internalPersonId!="") {
				addAnonymizedPersonIdInDb($mng, $row->internalPersonId, $newAnonymizedId);
			}


		}

	}
}

echo consoleMessage("info", "script ends.");
