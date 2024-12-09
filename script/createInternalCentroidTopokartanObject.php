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
echo consoleMessage("info", "example : php script/createInternalCentroidTopokartanObject.php file");

$debug=false;
$collection="internalCentroidTopokartan";

$nbadd=0;

$arr_source=array("file", "db");
if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_source)) {
	echo consoleMessage("error", "First parameter SOURCE missing: ".implode("/", $arr_source));
}
else {

	$source=$argv[1];
	$arrTopo=array();

	if ($source=="db") {

		$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

		$qSites='SELECT * from koordinater_mittpunkt_topokartan ';
		$rSites = pg_query($db_connection, $qSites);
		if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

		$cmdJs="";

		$incId=0;
		while ($rtSites = pg_fetch_array($rSites)) {

			//$topokartan=$rtSites;
			$topokartan=array();

			$incId++;

			$topokartan["id"]=$incId;
			
			$topokartan["karta"]=$rtSites["karta"];
			$topokartan["kartatx"]=$rtSites["kartatx"];
			$topokartan["rt90n"]=$rtSites["rt90n"];
			$topokartan["rt90o"]=$rtSites["rt90o"];
			$topokartan["wgs84_lat"]=$rtSites["wgs84_lat"];
			$topokartan["wgs84_lon"]=$rtSites["wgs84_lon"];
			$topokartan["sweref99_n"]=$rtSites["sweref99_n"];
			$topokartan["sweref99_o"]=$rtSites["sweref99_o"];

			$arrTopo[]=$topokartan;
		}

	}
	elseif ($source=="file") {
		$pathfile="script/excel/Punktrutter_LänKommun_20241203.xlsx";

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
			$topokartan=array();

			$topokartan["id"]=$iR-1;
			
			$topokartan["karta"]=$worksheet->getCell('A'.$iR)->getValue();
			$topokartan["kartatx"]=$worksheet->getCell('B'.$iR)->getValue();
			$topokartan["rt90n"]=$worksheet->getCell('C'.$iR)->getValue();
			$topokartan["rt90o"]=$worksheet->getCell('D'.$iR)->getValue();

			if (trim($worksheet->getCell('E'.$iR)->getValue())!="" && trim($worksheet->getCell('E'.$iR)->getValue())!=0) 
				$topokartan["wgs84_lat"]=$worksheet->getCell('E'.$iR)->getValue();
			else
				$topokartan["wgs84_lat"]=number_format($worksheet->getCell('G'.$iR)->getValue(), 5, ".", "");

			if (trim($worksheet->getCell('F'.$iR)->getValue())!="" && trim($worksheet->getCell('F'.$iR)->getValue())!=0)
				$topokartan["wgs84_lon"]=$worksheet->getCell('F'.$iR)->getValue();
			else
				$topokartan["wgs84_lon"]=number_format($worksheet->getCell('H'.$iR)->getValue(), 5, ".", "");	

			if (trim($worksheet->getCell('I'.$iR)->getValue())!="" && trim($worksheet->getCell('I'.$iR)->getValue())!=0)
				$topokartan["sweref99_n"]=$worksheet->getCell('I'.$iR)->getValue();
			else
				$topokartan["sweref99_n"]=intval(number_format($worksheet->getCell('K'.$iR)->getValue(), 0, ".", ""));	

			if (trim($worksheet->getCell('J'.$iR)->getValue())!="" && trim($worksheet->getCell('J'.$iR)->getValue())!=0)
				$topokartan["sweref99_o"]=$worksheet->getCell('J'.$iR)->getValue();
			else
				$topokartan["sweref99_o"]=intval(number_format($worksheet->getCell('L'.$iR)->getValue(), 0, ".", ""));

			$topokartan["county"]=$worksheet->getCell('M'.$iR)->getValue();

			$arrTopo[]=$topokartan;
		}
	}
	else {
		echo consoleMessage("error", "wrong source option");
	}

	if (count($arrTopo)>0) {
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

		foreach ($arrTopo as $topo) {
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

if (isset($arrTopo) && count($arrTopo)>0) {

	echo "mongodump -d ecodata -c internalCentroidTopokartan --gzip\n";
	echo "tar cvzf internalCentroidTopokartan.tar.gz dump/ecodata/\n";
	echo "scp internalCentroidTopokartan.tar.gz ubuntu@192.121.208.80:/home/ubuntu/\n";
	echo "rm -Rf dump/\n";
	echo "ssh-ecodata4\n";
	echo "tar xvf internalCentroidTopokartan.tar.gz\n";
	echo "cd dump/ecodata/\n";
	echo "gzip -d *.gz\n";
	echo "mongo ecodata\n";
	echo "db.internalCentroidTopokartan.drop()\n";
	echo "exit\n";
	echo "cd ..\n";
	echo "mongorestore -d ecodata ecodata/\n";
	echo "rm -Rf ecodata/\n";
}
/*
	$topokartan["p1_rt90_o"]=$rtSites["p1_rt90_o"];

	$topokartan["mitt_wgs84_lat"]=$rtSites["mitt_wgs84_lat"];
	$topokartan["mitt_wgs84_lon"]=$rtSites["mitt_wgs84_lon"];
	$topokartan["p1_wgs84_lat"]=$rtSites["p1_wgs84_lat"];
	$topokartan["p1_wgs84_lon"]=$rtSites["p1_wgs84_lon"];
	$topokartan["p1_sweref99_n"]=$rtSites["p1_sweref99_n"];
	$topokartan["p1_sweref99_o"]=$rtSites["p1_sweref99_o"];
	$topokartan["mitt_sweref99_n"]=$rtSites["mitt_sweref99_n"];
	$topokartan["mitt_sweref99_o"]=$rtSites["mitt_sweref99_o"];
	*/