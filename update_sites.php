<?php
$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php update_sites.php std 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust");
}
else {
	$protocol=$argv[1];

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));


	switch ($protocol) {

		case "std":

		$qSites="select karta, fjall104, fjall142
         from
         standardrutter_biotoper
         order by karta
		";
		$rSites = pg_query($db_connection, $qSites);
		if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

		$arr104=array();
		$arr142=array();
		while ($rtSites = pg_fetch_array($rSites)) {
			if ($rtSites["fjall104"]=="t") $arr104[]=$rtSites["karta"];
			if ($rtSites["fjall142"]=="t") $arr142[]=$rtSites["karta"];
		}

		$mng = new MongoDB\Driver\Manager(); 
	    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

		$bulk = new MongoDB\Driver\BulkWrite;
	    //$filter = [];
	    $filter = ['projects' => $commonFields[$protocol]["projectId"], "karta" => ['$in' => $arr104]];
	    print_r($filter);
	    $options =  ['$set' => ['Fjall104' => true]];
	    $updateOptions = ['multi' => true];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

print_r($result);

		$bulk = new MongoDB\Driver\BulkWrite;
	    //$filter = [];
	    $filter = ['projects' => $commonFields[$protocol]["projectId"], "karta" => ['$in' => $arr142]];
	    print_r($filter);
	    $options =  ['$set' => ['Fjall142' => true]];
	    $updateOptions = ['multi' => true];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

print_r($result);

		break;
	}





}