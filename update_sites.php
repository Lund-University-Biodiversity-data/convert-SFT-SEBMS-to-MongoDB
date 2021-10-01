<?php
$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php update_sites.php std 2 debug");

$county=getCountyLanArray();
$province=getProvinceLskArray();

$debug=false;
$collection="site";

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust");
}
else {

	if (isset($argv[2]) && is_numeric($argv[2])) {
		$limit=$argv[2];
		echo consoleMessage("info", "Limit is set to ".$limit." site(s)");
	}
	$protocol=$argv[1];

	echo consoleMessage("info", "Protocol ".$protocol." => projectId: ".$commonFields[$protocol]["projectId"]);

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));



	// first, make sure that these fields exist for these sites, in the adminPropoerties section. And create them as empty if needed
	$arrayFieldsRequired=array();

	$filename_json='update_site_'.$database.'_'.$protocol.'_'.date("Y-m-d-His").'.js';
	$path='dump_json_sft_sebms/'.$database.'/'.$protocol."/".$filename_json;

	//common fields
	$arrayFieldsRequired[]="internalSiteId";
	$arrayFieldsRequired[]="lan";

	switch ($protocol) {
		case "std":
			$arrayFieldsRequired[]="lsk";
			$arrayFieldsRequired[]="bookingComment";
			$arrayFieldsRequired[]="paperSurveySubmitted";
			$arrayFieldsRequired[]="fjall104";
			$arrayFieldsRequired[]="fjall142";

			$arrSitesPsql=array();

			$qSites="select karta, fjall104, fjall142, lan, lsk
		         from
		         standardrutter_biotoper
		         order by karta
				";
			if (isset($limit) && $limit>0) $limit.=" LIMIT ".$limit;
			$rSites = pg_query($db_connection, $qSites);
			if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

			
			while ($rtSites = pg_fetch_array($rSites)) {

				foreach ($arrayFieldsRequired as $key) {
					$arrSitesPsql[$rtSites["karta"]][$key]="";
				}
				$arrSitesPsql[$rtSites["karta"]]["lan"]=$county[$rtSites["lan"]];
				$arrSitesPsql[$rtSites["karta"]]["lsk"]=$province[$rtSites["lsk"]];
				$arrSitesPsql[$rtSites["karta"]]["internalSiteId"]=$rtSites["karta"];
				if ($rtSites["fjall104"]=="t") $arrSitesPsql[$rtSites["karta"]]["fjall104"]=1;
				if ($rtSites["fjall142"]=="t") $arrSitesPsql[$rtSites["karta"]]["fjall142"]=1;
			}

			break;

		case "sommar":
		case "vinter":
			break;

		case "natt":
			$arrayFieldsRequired[]="bookingComment";
			$arrayFieldsRequired[]="paperSurveySubmitted";
			break;


		case "kust":

			$arrayFieldsRequired[]="bookingComment";
			$arrayFieldsRequired[]="summarySurveySubmitted";
			$arrayFieldsRequired[]="routetype";

			$arrSitesPsql=array();

			$qSites="select ruta, ruttyp, lan
		         from
		         kustfagel200_koordinater
		         order by ruta
				";
			if (isset($limit) && $limit>0) $limit.=" LIMIT ".$limit;
			$rSites = pg_query($db_connection, $qSites);
			if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

			
			while ($rtSites = pg_fetch_array($rSites)) {

				foreach ($arrayFieldsRequired as $key) {
					$arrSitesPsql[$rtSites["ruta"]][$key]="";
				}
				$arrSitesPsql[$rtSites["ruta"]]["lan"]=$county[$rtSites["lan"]];
				$arrSitesPsql[$rtSites["ruta"]]["routetype"]=$rtSites["ruttyp"];
				$arrSitesPsql[$rtSites["ruta"]]["internalSiteId"]=$rtSites["ruta"];
			}


			break;

		case "iwc":
			break;
	}


/*	if (in_array("fjall104", $arrayFieldsRequired)) {

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

	}*/

	$mng = new MongoDB\Driver\Manager(); 
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

	// get all the sites from project
	$filter = ['projects' => $commonFields[$protocol]["projectId"]];
	if (isset($limit) && $limit>0) 
		$options = ['limit' => $limit];
    else $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 
    $rows = $mng->executeQuery("ecodata.".$collection, $query);

    $cmdJs="";

    foreach ($rows as $row){

    	switch ($protocol) {
			case "std":
				if (!isset($row->karta)) {
					echo consoleMessage("error", "No karta for ".$row->siteId); 
					//exit;
					$internalSiteId=$row->adminProperties->internalSiteId;
				}
				else {
					$internalSiteId=$row->karta;
				}
				break;

			case "natt":
				if (!isset($row->kartaTx)) {
					echo consoleMessage("error", "No kartaTx for ".$row->siteId); 
					//exit;
					if (!isset($row->adminProperties->internalSiteId) || $row->adminProperties->internalSiteId=="") {
						echo consoleMessage("error", "No internalSiteId for ".$row->siteId); 
						//exit;
						$internalSiteId="NO";
					}
					else {
						$internalSiteId=$row->adminProperties->internalSiteId;
					}
					
				}
				else {
					//echo "KartaTx".$row->kartaTx;
					$internalSiteId=$row->kartaTx;
				}
				break;

			case "kust":
				if (!isset($row->name)) {
					echo consoleMessage("error", "No name for ".$row->siteId); 
					//exit;
					if (!isset($row->adminProperties->internalSiteId) || $row->adminProperties->internalSiteId=="") {
						echo consoleMessage("error", "No internalSiteId for ".$row->siteId); 
						//exit;
						$internalSiteId="NO";
					}
					else {
						$internalSiteId=$row->adminProperties->internalSiteId;
					}
					
				}
				else {
					//echo "KartaTx".$row->kartaTx;
					$internalSiteId=$row->name;
				}
				break;	

		}
    	
    	// A FINIR
    	// check the existence of the field
    	// if it exists : DON'T TOUCH, MAYBE ALREADY EDITED ON PURPOSE !
    	// if it does not exist => fill it with SQL value
		$cmdJs.='db.'.$collection.'.update({"siteId" : "'.$row->siteId.'"}, {$set : {
	';
		switch ($protocol) {
			case "std":

				foreach ($arrayFieldsRequired as $key) {
					if (isset($arrSitesPsql[$internalSiteId][$key])) $val=$arrSitesPsql[$internalSiteId][$key];
					else $val="";

					$cmdJs.='"adminProperties.'.$key.'" : "'.$val.'",';
				}

				break;

			case "natt": 
				foreach ($arrayFieldsRequired as $key) {
					if ($key=="internalSiteId") {
						$val=$internalSiteId;
					}
					elseif (isset($arrSitesPsql[$internalSiteId][$key])) $val=$arrSitesPsql[$internalSiteId][$key];
					else $val="";

					$cmdJs.='"adminProperties.'.$key.'" : "'.$val.'",';
				}

				break;

			case "kust": 
				
				foreach ($arrayFieldsRequired as $key) {
					if (isset($arrSitesPsql[$internalSiteId][$key])) $val=$arrSitesPsql[$internalSiteId][$key];
					else $val="";

					$cmdJs.='"adminProperties.'.$key.'" : "'.$val.'",';
				}

				break;
			
		}
		$cmdJs[strlen($cmdJs)-1]=' ';
		$cmdJs.='}});
';


    }


	// unset all the fields
    $unset='db.'.$collection.'.updateMany({"projects" : "'.$commonFields[$protocol]["projectId"].'"}, {$unset : {';

	$unset.='"lan":1,';
    switch ($protocol) {
		case "std":
			$unset.='"lsk":1, "karta":1,';

			break;
		case "natt":
			$unset.='"kartaTx":1,';

		case "kust":
			$unset.=',';
			break;	

	}
	$unset[strlen($unset)-1]=' ';
	$unset.='}});
';

	$cmdJs.=$unset;
	//print_r($unset);

	if ($fp = fopen($path, 'w')) {
		fwrite($fp, $cmdJs);
		fclose($fp);
		echo consoleMessage("info", "File ".$path." created");

		$cmd='mongo ecodata < '.$path;
		echo consoleMessage("info", "Command : ".$cmd);
	}
	else echo consoleMessage("error", "can't create file ".$path);





/*
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
	    $options =  ['$set' => ['adminProperties.Fjall104' => true]];
	    $updateOptions = ['multi' => true];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

print_r($result);

		$bulk = new MongoDB\Driver\BulkWrite;
	    //$filter = [];
	    $filter = ['projects' => $commonFields[$protocol]["projectId"], "karta" => ['$in' => $arr142]];
	    print_r($filter);
	    $options =  ['$set' => ['adminProperties.Fjall142' => true]];
	    $updateOptions = ['multi' => true];
	    $bulk->update($filter, $options, $updateOptions); 
	    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

print_r($result);

		break;


		case "natt":

		break;
	}
*/




}