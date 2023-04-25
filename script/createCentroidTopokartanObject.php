<?php

$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
$server=DEFAULT_SERVER;
require "lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "example : php script/createCentroidTopokartanObject.php");


$debug=false;
$collection="internalCentroidTopokartan";


$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

$qSites='SELECT * from koordinater_mittpunkt_topokartan ';
$rSites = pg_query($db_connection, $qSites);
if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));


$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created
if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
else echo consoleMessage("error", "No connection to mongoDb");


$cmdJs="";

$arrTopo=array();
$incId=0;
while ($rtSites = pg_fetch_array($rSites)) {

	//$topokartan=$rtSites;
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

$nbadd=0;
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


echo consoleMessage("info", $nbadd." object(s) topokartan added to ".$collection);
echo consoleMessage("info", "script ends");
/*

*/



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