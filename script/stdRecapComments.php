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
echo consoleMessage("info", "php script/stdRecapComments.php");

$tmpfname = "script/excel/Inventerarkommentarer.Standardrutter-1996-2020.Sissel.xlsx";

try {

	$fileRefused=false;
	$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
	//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
	$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
	$excelObj->setReadDataOnly(true);
	$spreadsheet = $excelObj->load($tmpfname);
	$worksheet = $spreadsheet->getSheet(0);
	
}
catch (\Exception $exception) {
	$fileRefused=true;
	echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
	echo consoleMessage("error", "can't read Excel file ".$tmpfname);
}


if (!$fileRefused) {

	// get all the sites
	$arrSitesDetails=getArraySitesFromMongo($commonFields["std"]["projectId"], $server);


	$firstRow=2;

	for ($iR=$firstRow;$worksheet->getCell('A'.$iR)->getValue()!=""; $iR++) {


		$data=array();

		$data["karta"]=$worksheet->getCell('A'.$iR)->getValue();
		$data["yr"]=$worksheet->getCell('B'.$iR)->getValue();
		$data["gps"]=$worksheet->getCell('C'.$iR)->getValue();
		$data["praktisk"]=$worksheet->getCell('D'.$iR)->getValue();
		$data["bird"]=$worksheet->getCell('E'.$iR)->getValue();

		//print_r($data);

		//if ($data["karta"]=="09B7H") {


			if (!isset($arrSitesDetails[$data["karta"]])) {
				echo consoleMessage("error", "No site data for ".$data["karta"]);
			}
			else {
				//$filter = [];

			    $regexYr = new MongoDB\BSON\Regex ( $data["yr"] );


			    $filter = [
			    	'data.location' => $arrSitesDetails[$data["karta"]]["locationID"],
			    	'data.surveyDate' => ['$regex' => $regexYr ]
			    ];
				$options = [];
				$query = new MongoDB\Driver\Query($filter, $options); 

				$rows = $mng->executeQuery("ecodata.output", $query);
				$rowsToArray = $rows->toArray();

				if (count($rowsToArray)==1) {
					$newEventsRemarks=$data["praktisk"];
					$newComments=$data["bird"];
					
					$bulk = new MongoDB\Driver\BulkWrite;

				    $options =  ['$set' => [
				    	'data.eventRemarks' => $newEventsRemarks,
				    	'data.comments' => $newComments
				    ]];

				    $updateOptions = ['multi' => false];
				    $bulk->update($filter, $options, $updateOptions); 
				    $result = $mng->executeBulkWrite('ecodata.output', $bulk);

				}
				else {
					echo consoleMessage("error", count($rowsToArray)." row(s) for ".$data["karta"]."/".$data["yr"]);
				}
			}


		//}




	    


		if ($iR%100==0) echo consoleMessage("info", $iR." line(s) processed");


	}

	echo consoleMessage("info", "script ends after ".$iR." line(s) processed");
}