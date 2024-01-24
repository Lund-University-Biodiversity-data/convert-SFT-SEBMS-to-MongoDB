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
echo consoleMessage("info", "php script/importCoordinatesPunktSites.php [exec]");
echo consoleMessage("info", "use the option exec to add in database");


// get the existing sites from the database
$protocol="punkt";
$arrSitesDetails=getArraySitesFromMongo($commonFields[$protocol]["projectId"], $server);
echo consoleMessage("info", "1- Get all sites for protocol ".$protocol);
echo consoleMessage("info", count($arrSitesDetails)." site(s).");

$tmpfname = "script/excel/Punktruttskoordinater_240121_1.xlsx";

try {

	$fileRefused=false;
	$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
	//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
	$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
	$excelObj->setReadDataOnly(true);
	$spreadsheet = $excelObj->load($tmpfname);
	$excelObjWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $inputFileType);

	$worksheet = $spreadsheet->getSheet(0);
	
}
catch (\Exception $exception) {
	$fileRefused=true;
	echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
	echo consoleMessage("error", "can't read Excel file ".$tmpfname);
}

if (!$fileRefused) {


	$arrCoordToAdd=array();

	$firstRow=2;
	$nbErrors=0;
	$nbOk=0;

	echo consoleMessage("info", "2- read the excel file and fill in an array");

	for ($iR=$firstRow;$worksheet->getCell('A'.$iR)->getValue()!=""; $iR++) {

		// check to add only if we reach a punkt nummer = 20

		// recreate internalSiteId
		$internalSiteId=$worksheet->getCell('A'.$iR)->getValue()."-".$worksheet->getCell('B'.$iR)->getValue();
		$punktNummer=$worksheet->getCell('C'.$iR)->getValue();
		$latitude=$worksheet->getCell('D'.$iR)->getValue();
		$longitude=$worksheet->getCell('E'.$iR)->getValue();

		$arrCoordToAdd[$internalSiteId][$punktNummer]["lat"]=$latitude;
		$arrCoordToAdd[$internalSiteId][$punktNummer]["lon"]=$longitude;

	}
	echo consoleMessage("info", $iR." line(s) processed");

	echo consoleMessage("info", count($arrCoordToAdd)." different site(s) found in the excel file");

	echo consoleMessage("info", "3- analyze the data and create the json");

	$arrFinalJson=array();
	foreach ($arrCoordToAdd as $siteId => $coord) {
		// check if site exists

		if (!isset($arrSitesDetails[$siteId])) {
			echo consoleMessage("error", "No site data for ".$siteId);
			$nbErrors++;
		}
		elseif (count($arrSitesDetails[$siteId]["transectParts"])!=0) {
			echo consoleMessage("error", "Transect data already exists for site ".$siteId);
			$nbErrors++;
		}
		else {
			//$json="[";
			$json=array();

			foreach ($coord as $punktNummer => $latlon) {

				if (!is_numeric($punktNummer) || $punktNummer<0 || $punktNummer>20) {
					$fileRefused=true;
					echo consoleMessage("error", "Incorrect punktnummer#".$punktNummer." for site ".$siteId);	
				}
				if (trim($latlon["lon"])=="" || !is_numeric($latlon["lon"]) || trim($latlon["lat"])=="" || !is_numeric($latlon["lat"])) {
					$fileRefused=true;
					echo consoleMessage("error", "Missing lat/lon for punktnummer#".$punktNummer." and site ".$siteId);	
				}

				$json[]=[
					"internalName" => 'P'.$punktNummer,
					"name" => 'P'.$punktNummer,
					"geometry" => [
						"type" => "Point",
						"decimalLongitude" => $latlon["lon"],
						"decimalLatitude" => $latlon["lat"],
						"coordinates" => [$latlon["lon"], $latlon["lat"]]
					]
				];
				/*$json.='{
					"internalName" : "P'.$punktNummer.'",
					"name" : "P'.$punktNummer.'",
					"geometry" : {
						"type" : "Point",
						"decimalLongitude" : '.$latlon["lon"].',
						"decimalLatitude" : '.$latlon["lat"].',
						"coordinates" : [
							'.$latlon["lon"].',
							'.$latlon["lat"].',
						]
					}
				}';*/
				//if ($punktNummer!=20) $json.=",";
			}

			//$json.="]";

			if ($punktNummer!=20) {
				$fileRefused=true;
				echo consoleMessage("error", "No punktnummer#20 for site ".$siteId);
			}

			//echo $json;
			//exit();
			$arrFinalJson[$arrSitesDetails[$siteId]["locationID"]]=$json;
			//$arrFinalJson[$arrSitesDetails[$siteId]["locationID"]]="";

			$nbOk++;
		}

	}

	echo consoleMessage("info", $nbOk." line(s) OK.");
	echo consoleMessage("info", $nbErrors." line(s) ERRORED.");

	if ($nbErrors==0 && isset($argv[1]) && $argv[1]=="exec") {
		$nbAdd=0;
		foreach($arrFinalJson as $mongoSiteId => $dataCoord) {


			$bulk = new MongoDB\Driver\BulkWrite;
			$filter = [
		    	'siteId' => $mongoSiteId
		    ];
		    $options =  ['$set' => [
		    	'transectParts' => ($dataCoord)
		    ]];

		    $updateOptions = ['multi' => false];
		    $bulk->update($filter, $options, $updateOptions); 
		    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

		    $nbAdd++;
		}
		echo consoleMessage("info", $nbAdd." line(s) edited in ecodata.");
	}

}


echo consoleMessage("info", "script ends.");
