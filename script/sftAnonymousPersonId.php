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
echo consoleMessage("info", "php script/sftAnonymousPersonId.php std [newIds]");
echo consoleMessage("info", "use the option newIds to add newIds to the file and the database for missing ones");

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

$arr_protocol=array("std");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	if (isset($argv[2]) && $argv[2]=="newIds") {
		$modeScript="newIds";
	}

	$protocol=$argv[1];

	try {

		$fileRefused=false;
		$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
		//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
		$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
		$excelObj->setReadDataOnly(true);
		$spreadsheet = $excelObj->load($tmpfname);
		$excelObjWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $inputFileType);

		$worksheet = $spreadsheet->getSheetByName($protocol);
		
	}
	catch (\Exception $exception) {
		$fileRefused=true;
		echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
		echo consoleMessage("error", "can't read Excel file ".$tmpfname);
	}


	if (!$fileRefused) {


		// 1- read the excel file, add the anonymizedId to the database
		echo consoleMessage("info", "1- read the excel file, add the anonymizedId to the database");

		// get all the persons
		$arrPersonsDetails=getArrayPersonsFromMongo($commonFields[$protocol]["projectId"], $server);
		$arrUniqueIds=array();
		$arrUniquePersons=array();

		$firstRow=2;
		$nbErrors=0;
		$nbOk=0;

		$maxAnonymizedId=0;

		for ($iR=$firstRow;$worksheet->getCell('A'.$iR)->getValue()!=""; $iR++) {

			$internalPersonId=$worksheet->getCell('A'.$iR)->getValue();
			$anonymizedId=$worksheet->getCell('B'.$iR)->getValue();
			
			if (is_numeric($anonymizedId) && $anonymizedId>$maxAnonymizedId)
				$maxAnonymizedId=$anonymizedId;

			if (in_array($anonymizedId, $arrUniqueIds)) {
				echo consoleMessage("error", "DOUBLON for anonymizedId ".$anonymizedId.", already set to another person");
				$nbErrors++;
			} else {
				$arrUniqueIds[]=$anonymizedId;
				$arrUniquePersons[]=$internalPersonId;

				if (!isset($arrPersonsDetails[$internalPersonId])) {
					echo consoleMessage("error", "No person data for ".$internalPersonId);
					$nbErrors++;
				}
				else {
				    $filter = [
				    	'internalPersonId' => $internalPersonId
				    ];
					$options = [];
					$query = new MongoDB\Driver\Query($filter, $options); 

					$rows = $mng->executeQuery("ecodata.person", $query);
					$rowsToArray = $rows->toArray();

					if (count($rowsToArray)==1) {

						if (isset($arrPersonsDetails[$internalPersonId]["anonymizedId"]) && $arrPersonsDetails[$internalPersonId]["anonymizedId"]!="" && $arrPersonsDetails[$internalPersonId]["anonymizedId"]!=$anonymizedId) {
							echo consoleMessage("error", "anonymizedId already set for ".$internalPersonId.". Value in DDB : ".$arrPersonsDetails[$internalPersonId]["anonymizedId"].". Value to set : ".$anonymizedId);
							$nbErrors++;
						}
						else {
						//echo "set anonymized : ".$anonymizedId."\n";

							addAnonymizedPersonIdInDb($mng, $internalPersonId, $anonymizedId);
							
						    $nbOk++;
						}
					}
					else {
						echo consoleMessage("error", count($rowsToArray)." row(s) for ".$internalPersonId);
						$nbErrors++;
					}
				}





			}


		    


			//if ($iR%100==0) echo consoleMessage("info", $iR." line(s) processed");


		}
		echo consoleMessage("info", $maxAnonymizedId." is the maxAnonymizedId.");


		echo consoleMessage("info", "2- check if persons have not been found in the file");

		echo consoleMessage("info", count($arrPersonsDetails)." person(s) in the database for protocol ".$protocol);
		echo consoleMessage("info", count($arrUniquePersons)." person(s) in the file");

		foreach($arrPersonsDetails as $iSI => $dataPerson) {
			if (!in_array($iSI, $arrUniquePersons)) {

				if (isset($modeScript) && $modeScript=="newIds") {
					$maxAnonymizedId++;
					echo consoleMessage("info", $maxAnonymizedId." is the new anonymizedId for ".$iSI);

					$worksheet->getCell('A'.$iR)->setValue($iSI);
					$worksheet->getCell('B'.$iR)->setValue($maxAnonymizedId);
					$worksheet->getCell('C'.$iR)->setValue(date("Y-m-d_H:i:s")." updated by script sftAnonymousPersonId");
					$fileUpdated=true;

					$iR++;

					addAnonymizedPersonIdInDb($mng, $iSI, $maxAnonymizedId);
					
				    $nbOk++;

				}
				else {
					echo consoleMessage("error", $iSI." from database is not present in the excel file");
					$nbErrors++;
				}

			}
		}


		if ($fileUpdated) {
			echo consoleMessage("info", "Save the file ".$tmpfname);

			$excelObjWriter->save($tmpfname);
		}


		echo consoleMessage("info", $nbOk." line(s) OK.");
		echo consoleMessage("info", $nbErrors." line(s) ERRORED.");

		echo consoleMessage("info", "script ends after ".$iR." line(s) processed");

	}

}

echo consoleMessage("info", "script ends.");
