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
echo consoleMessage("info", "php script/sftAnonymousSiteId.php std");

$tmpfname = "script/excel/sft_anonymized_sites.xlsx";

$arr_protocol=array("std");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	$protocol=$argv[1];

	try {

		$fileRefused=false;
		$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
		//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
		$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
		$excelObj->setReadDataOnly(true);
		$spreadsheet = $excelObj->load($tmpfname);
		$worksheet = $spreadsheet->getSheetByName($protocol);
		
	}
	catch (\Exception $exception) {
		$fileRefused=true;
		echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
		echo consoleMessage("error", "can't read Excel file ".$tmpfname);
	}


	if (!$fileRefused) {

		// get all the sites
		$arrSitesDetails=getArraySitesFromMongo($commonFields[$protocol]["projectId"], $server);
		$arrUniqueIds=array();

		$firstRow=2;
		$nbErrors=0;
		$nbOk=0;

		for ($iR=$firstRow;$worksheet->getCell('A'.$iR)->getValue()!=""; $iR++) {

			$internalSiteId=$worksheet->getCell('A'.$iR)->getValue();
			$anonymizedId=$worksheet->getCell('B'.$iR)->getValue();
			
			if (in_array($anonymizedId, $arrUniqueIds)) {
				echo consoleMessage("error", "DOUBLON for anonymizedId ".$anonymizedId.", already set to another route");
				$nbErrors++;
			} else {
				$arrUniqueIds[]=$anonymizedId;

				if (!isset($arrSitesDetails[$internalSiteId])) {
					echo consoleMessage("error", "No site data for ".$data["karta"]);
					$nbErrors++;
				}
				else {
				    $filter = [
				    	'adminProperties.internalSiteId' => $internalSiteId
				    ];
					$options = [];
					$query = new MongoDB\Driver\Query($filter, $options); 

					$rows = $mng->executeQuery("ecodata.site", $query);
					$rowsToArray = $rows->toArray();

					if (count($rowsToArray)==1) {

						if (isset($arrSitesDetails[$internalSiteId]["anonymizedId"]) && $arrSitesDetails[$internalSiteId]["anonymizedId"]!="" && $arrSitesDetails[$internalSiteId]["anonymizedId"]!=$anonymizedId) {
							echo consoleMessage("error", "anonymizedId already set for ".$internalSiteId.". Value in DDB : ".$arrSitesDetails[$internalSiteId]["anonymizedId"].". Value to set : ".$anonymizedId);
							$nbErrors++;
						}
						else {
						
							$bulk = new MongoDB\Driver\BulkWrite;

						    $options =  ['$set' => [
						    	'adminProperties.anonymizedId' => $anonymizedId
						    ]];

						    $updateOptions = ['multi' => false];
						    $bulk->update($filter, $options, $updateOptions); 
						    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

						    $nbOk++;
						}
					}
					else {
						echo consoleMessage("error", count($rowsToArray)." row(s) for ".$internalSiteId);
						$nbErrors++;
					}
				}





			}


		    


			if ($iR%100==0) echo consoleMessage("info", $iR." line(s) processed");


		}
		echo consoleMessage("info", $nbOk." line(s) OK.");
		echo consoleMessage("info", $nbErrors." line(s) ERRORED.");
		echo consoleMessage("info", "script ends after ".$iR." line(s) processed");

	}

}

