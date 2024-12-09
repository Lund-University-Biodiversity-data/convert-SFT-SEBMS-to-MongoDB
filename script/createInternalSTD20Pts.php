<?php

$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
$server=DEFAULT_SERVER;
require "lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "specify the source : file (to be located in the excel folder) or db (sql)");
echo consoleMessage("info", "example : php script/createInternalSTD20pts.php file");

$debug=false;
$collection="internalSTD20Pts";

$nbadd=0;

$arr_source=array("file");
if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_source)) {
	echo consoleMessage("error", "First parameter SOURCE missing: ".implode("/", $arr_source));
}
else {

	$source=$argv[1];
	$arrPoints=array();

	if ($source=="db") {} // not implemented
	elseif ($source=="file") {
		$pathfile="script/excel/SFTpkt_PunktIDn_masterfile.xlsx";

		$fileRefused=false;
		$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($pathfile);
		//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
		$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
		$excelObj->setReadDataOnly(true);
		$spreadsheet = $excelObj->load($pathfile);
		$excelObjWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $inputFileType);

		$worksheet = $spreadsheet->getSheet(0);

		$firstRow=2;

		for ($iR=$firstRow;$worksheet->getCell('A'.$iR)->getValue()!=""; $iR++) {
			//echo $worksheet->getCell('A'.$iR)->getValue()."\n";
			$pointData=array();

			$pointData["id"]=$iR-1;
			
			$pointData["internalSiteId"]=$worksheet->getCell('A'.$iR)->getValue();
			$pointData["pointNumber"]=$worksheet->getCell('B'.$iR)->getValue();
			$pointData["pointName"]=$worksheet->getCell('C'.$iR)->getValue();
			$pointData["anonymousPointID"]=$worksheet->getCell('D'.$iR)->getValue();
			$pointData["countyName"]=$worksheet->getCell('E'.$iR)->getValue();
			$pointData["countySCB"]=$worksheet->getCell('F'.$iR)->getValue();
			$pointData["municipality"]=$worksheet->getCell('G'.$iR)->getValue();
			$pointData["StnRegOSId"]=$worksheet->getCell('H'.$iR)->getValue();
			$pointData["StnRegPPId"]=$worksheet->getCell('I'.$iR)->getValue();


			$arrPoints[]=$pointData;
		}
	}
	else {
		echo consoleMessage("error", "wrong source option");
	}

	if (count($arrPoints)>0) {
		$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created
		if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
		else echo consoleMessage("error", "No connection to mongoDb");


		try {
			// Create a Command Instance
			$dropCollection = new MongoDB\Driver\Command(["drop" => $collection]);

			// Execute the command on the database
			$cursor = $mng->executeCommand("ecodata", $dropCollection);

			echo consoleMessage("info", $collection." deleted !");
		} catch (MongoDB\Driver\Exception\Exception $e) {	   
			echo consoleMessage("warn", "Collection not existing ? Exception:", $e->getMessage());
		}

		foreach ($arrPoints as $topo) {
			$bulk = new MongoDB\Driver\BulkWrite;
			$_id1 = $bulk->insert($topo);

			$result = $mng->executeBulkWrite('ecodata.'.$collection, $bulk);

			if ($result->getInsertedCount()==1) $nbadd++;
			else {
				print_r($topo);
				echo consoleMessage("error", "can't add object");
				exit;
			}
		}
	}
}

echo consoleMessage("info", $nbadd." object(s) topokartan added to ".$collection);
echo consoleMessage("info", "script ends");

if (isset($arrPoints) && count($arrPoints)>0) {
	echo "mongodump -d ecodata -c internalSTD20Pts --gzip\n";
	echo "tar cvzf internalSTD20Pts.tar.gz dump/ecodata/\n";
	echo "scp internalSTD20Pts.tar.gz ubuntu@192.121.208.80:/home/ubuntu/\n";
	echo "rm -Rf dump/\n";
	echo "ssh-ecodata4\n";
	echo "tar xvf internalSTD20Pts.tar.gz\n";
	echo "cd dump/ecodata/\n";
	echo "gzip -d *.gz\n";
	echo "mongo ecodata\n";
	echo "db.internalSTD20Pts.drop()\n";
	echo "exit\n";
	echo "cd ..\n";
	echo "mongorestore -d ecodata ecodata/\n";
	echo "rm -Rf ecodata/\n";
}
/*
	$pointData["p1_rt90_o"]=$rtSites["p1_rt90_o"];

	$pointData["mitt_wgs84_lat"]=$rtSites["mitt_wgs84_lat"];
	$pointData["mitt_wgs84_lon"]=$rtSites["mitt_wgs84_lon"];
	$pointData["p1_wgs84_lat"]=$rtSites["p1_wgs84_lat"];
	$pointData["p1_wgs84_lon"]=$rtSites["p1_wgs84_lon"];
	$pointData["p1_sweref99_n"]=$rtSites["p1_sweref99_n"];
	$pointData["p1_sweref99_o"]=$rtSites["p1_sweref99_o"];
	$pointData["mitt_sweref99_n"]=$rtSites["mitt_sweref99_n"];
	$pointData["mitt_sweref99_o"]=$rtSites["mitt_sweref99_o"];
	*/