<?php
$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";
$server=DEFAULT_SERVER;
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "php sites-natt-punkt-iwc-coordinates/update_sites.php std 2 ");


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

	$unsetAction=true;

	switch ($protocol) {
		case "std":

			$arrayFieldsRequired[]="internalSiteId";
			$arrayFieldsRequired[]="lan";
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
				$arrSitesPsql[$rtSites["karta"]]["lan"]=$rtSites["lan"];
				$arrSitesPsql[$rtSites["karta"]]["lsk"]=$rtSites["lsk"];
				$arrSitesPsql[$rtSites["karta"]]["internalSiteId"]=$rtSites["karta"];
				if ($rtSites["fjall104"]=="t") $arrSitesPsql[$rtSites["karta"]]["fjall104"]=1;
				else $arrSitesPsql[$rtSites["karta"]]["fjall104"]=0;
				if ($rtSites["fjall142"]=="t") $arrSitesPsql[$rtSites["karta"]]["fjall142"]=1;
				else  $arrSitesPsql[$rtSites["karta"]]["fjall142"]=0;
			}

			break;

		case "sommar":
		case "vinter":
			$arrayFieldsRequired[]="internalSiteId";
			$arrayFieldsRequired[]="lan";
			$arrayFieldsRequired[]="kartaTx";
			$arrayFieldsRequired[]="sevin";
			$arrayFieldsRequired[]="start";

			$arrSitesPsql=array();

			$qSites="select persnr, rnr, kartatx, lan, senvin, start
		         from
		         punktrutter
		         order by kartatx
				";
			if (isset($limit) && $limit>0) $limit.=" LIMIT ".$limit;
			$rSites = pg_query($db_connection, $qSites);
			if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

			
			while ($rtSites = pg_fetch_array($rSites)) {

				foreach ($arrayFieldsRequired as $key) {
					$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]][$key]="";
				}
				$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]]["kartaTx"]=$rtSites["kartatx"];
				$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]]["internalSiteId"]=$rtSites["persnr"]."-".$rtSites["rnr"];
				$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]]["senvin"]=$rtSites["senvin"];
				$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]]["start"]=$rtSites["start"];
				$arrSitesPsql[$rtSites["persnr"]."-".$rtSites["rnr"]]["lan"]=$rtSites["lan"];
			}


			break;

		case "natt":
			$arrayFieldsRequired[]="internalSiteId";
			$arrayFieldsRequired[]="lan";
			$arrayFieldsRequired[]="bookingComment";
			$arrayFieldsRequired[]="paperSurveySubmitted";
			break;


		case "kust":

			// only the NEW fields
			//$arrayFieldsRequired[]="internalSiteId";
			//$arrayFieldsRequired[]="lan";
			//$arrayFieldsRequired[]="bookingComment";
			//$arrayFieldsRequired[]="summarySurveySubmitted";
			//$arrayFieldsRequired[]="routetype";
			$arrayFieldsRequired[]="area_m2";
			$arrayFieldsRequired[]="mitt_5x5_wgs84_lat";
			$arrayFieldsRequired[]="mitt_5x5_wgs84_lon";

			$arrSitesPsql=array();

			$qSites="select ruta, ruttyp, lan, area_m2, mitt_5x5_wgs84_lat, mitt_5x5_wgs84_lon
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
				// only the NEW fields
				//$arrSitesPsql[$rtSites["ruta"]]["lan"]=$rtSites["lan"];
				//$arrSitesPsql[$rtSites["ruta"]]["routetype"]=$rtSites["ruttyp"];
				//$arrSitesPsql[$rtSites["ruta"]]["internalSiteId"]=$rtSites["ruta"];
				$arrSitesPsql[$rtSites["ruta"]]["area_m2"]=$rtSites["area_m2"];
				$arrSitesPsql[$rtSites["ruta"]]["mitt_5x5_wgs84_lat"]=$rtSites["mitt_5x5_wgs84_lat"];
				$arrSitesPsql[$rtSites["ruta"]]["mitt_5x5_wgs84_lon"]=$rtSites["mitt_5x5_wgs84_lon"];
			}


			//$unset=true;
			$unsetAction=false;

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

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); 
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    else echo consoleMessage("error", "No connection to mongoDb");

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
    		case "sommar":
    		case "vinter":

    			if (!isset($row->adminProperties->internalSiteId) || $row->adminProperties->internalSiteId=="") {
					echo consoleMessage("error", "No internalSiteId for ".$row->siteId); 
					//exit;
					$internalSiteId="NO";
				}
				else {
					$internalSiteId=$row->adminProperties->internalSiteId;
				}


    			break;
			
			case "std":
				

				if (!isset($row->karta)) {
					if (!isset($row->adminProperties->internalSiteId) || $row->adminProperties->internalSiteId=="") {
						echo consoleMessage("error", "No karta/internalSiteId for ".$row->siteId); 
						//exit;
						$internalSiteId="NO";
					}
					else {
						//echo consoleMessage("error", "No karta for ".$row->siteId); 
						//exit;
						$internalSiteId=$row->adminProperties->internalSiteId;
					}
				}
				else {
					$internalSiteId=$row->karta;
				}
				break;

			case "natt":
				if (!isset($row->kartaTx)) {
					//exit;
					if (!isset($row->adminProperties->internalSiteId) || $row->adminProperties->internalSiteId=="") {
						echo consoleMessage("error", "No kartatx/internalSiteId for ".$row->siteId); 
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
			case "sommar":
			case "vinter":
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

    if ($unsetAction) {
		// unset all the fields
	    $unset='db.'.$collection.'.updateMany({"projects" : "'.$commonFields[$protocol]["projectId"].'"}, {$unset : {';

		$unset.='"lan":1,';
	    switch ($protocol) {
			case "std":
				$unset.='"lsk":1, "karta":1,';

				break;
			case "sommar":
			case "vinter":
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
    }

	//print_r($unset);

	if ($fp = fopen($path, 'w')) {
		fwrite($fp, $cmdJs);
		fclose($fp);
		echo consoleMessage("info", "File ".$path." created");

		$cmd='mongo ecodata < '.$path;
		echo consoleMessage("info", "Command : ".$cmd);

		$scp='scp '.$path.' radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/'.$path;
		echo consoleMessage("info", "Command scp DEV : ".$scp);
		$scp='scp '.$path.' ubuntu@'.$IP_PROD.':/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/'.$path;
		echo consoleMessage("info", "Command scp PROD : ".$scp);
	}
	else echo consoleMessage("error", "can't create file ".$path);





/*

db.site.find({projects:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c", "adminProperties.fjall104":{$exists:true}, "adminProperties.fjall104":{$ne:"1"}}).count()

db.site.updateMany({
	projects:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c", 
	"adminProperties.fjall104":{$exists:true}, 
	"adminProperties.fjall104":{$ne:"1"}
},
{$set: {
	"adminProperties.fjall104": "0"
}}
)

db.site.find({projects:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c", "adminProperties.fjall142":{$exists:true}, "adminProperties.fjall142":{$ne:"1"}}).count()

db.site.updateMany({
	projects:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c", 
	"adminProperties.fjall142":{$exists:true}, 
	"adminProperties.fjall142":{$ne:"1"}
},
{$set: {
	"adminProperties.fjall142": "0"
}}
)
*/

}