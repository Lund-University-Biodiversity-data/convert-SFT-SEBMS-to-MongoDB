<?php
$database="SFT";
require "../lib/config.php";
require "../lib/functions.php";
//require_once "lib/PHPExcel/Classes/PHPExcel.php";
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php create_empty_sites.php natt");

$debug=false;

$arr_protocol=array("natt", "punkt");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: natt / punkt");
}
else {

	$protocol=$argv[1];	

	if ($protocol=="natt") {
		$arrMode=array("totalnatt", "list");

		if (!isset($argv[2]) || !in_array(trim($argv[2]), $arrMode)) {
			echo consoleMessage("error", "Natt mode parameter missing: ".implode(" / ", $arrMode));
			exit();
		} 
		else {
			if (isset($argv[3]) && $argv[3]=="debug") {
				$debug=true;
				echo consoleMessage("info", "DEBUG mode");
			}

			$mode=$argv[2];
		}
	}
	else {
		if (isset($argv[2]) && $argv[2]=="debug") {
			$debug=true;
			echo consoleMessage("info", "DEBUG mode");
		}

	}


	echo "****CONVERT ".$database." ".$protocol." to MongoDB JSON****\n";

	$array_psql_sites=array();

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	if ($protocol=="punkt" || $mode=="totalnatt") {


		switch($protocol) {
			case "natt":

				$qSites='SELECT DISTINCT kartatx FROM totalnatt;';

				$projectId=$commonFields["natt"]["projectId"];

				$fieldIdentifier = "kartatx";
				$fieldRouteName = "kartatx";
				$fieldLan = "kartatx";

				break;

			case "punkt":

				$fieldRouteName = "ruttnamn";
				$fieldIdentifier = "internalsiteid";
				$fieldLan = "lan";

				$qSites="select CONCAT(persnr, '-', rnr ) as internalsiteid, kartatx, ruttnamn, lan from (
					SELECT DISTINCT p.persnr, p.rnr, p.kartatx, p.ruttnamn, p.lan FROM punktrutter p, totalvinter_pkt v 
								WHERE p.persnr=v.persnr
								AND p.rnr=v.rnr
					union 
					SELECT DISTINCT p.persnr, p.rnr, p.kartatx, p.ruttnamn, p.lan FROM punktrutter p, totalsommar_pkt s 
								WHERE p.persnr=s.persnr
								AND p.rnr=s.rnr
								ORDER BY kartatx, persnr, rnr
								
							) as tot";

				$projectId=$commonFields["vinter"]["projectId"];
				
				break;
		}

		$rSites = pg_query($db_connection, $qSites);
		while ($rtSites = pg_fetch_array($rSites)) {
			$line=array();
			$line["kartatx"]=$rtSites["kartatx"];
			$line[$fieldIdentifier]=$rtSites[$fieldIdentifier];
			$line[$fieldRouteName]=$rtSites[$fieldRouteName];
			$line[$fieldLan]=$rtSites[$fieldLan];
			$array_psql_sites[]=$line;
		}

		echo consoleMessage("info", count($array_psql_sites)." site(s) found in psql"); 

		$array_mongo_sites=getArraySitesFromMongo ($protocol, $projectId);

		echo consoleMessage("info", count($array_mongo_sites)." site(s) found in mongoDB"); 


		$arrMissKartaTx=array();
		$arrMissIdentifier=array();
		$okSites=0;
		foreach ($array_psql_sites as $sqlSite) {
			if (!isset($array_mongo_sites[$sqlSite[$fieldIdentifier]])) {
				if (!in_array($sqlSite["kartatx"], $arrMissKartaTx) )
					$arrMissKartaTx[]=$sqlSite["kartatx"];

				$line=array();
				$line["kartatx"]=$sqlSite["kartatx"];
				$line[$fieldIdentifier]=$sqlSite[$fieldIdentifier];
				$line[$fieldRouteName]=$sqlSite[$fieldRouteName];
				$line[$fieldLan]=$sqlSite[$fieldLan];

				$arrMissIdentifier[]=$line;
				//echo "Missing ".$sqlSite."\n";
			}
			else {
				//echo $sqlSite[$fieldIdentifier]."\n";
				$okSites++;
			}
		}
		if ($debug) print_r($arrMissIdentifier);


		

		echo consoleMessage("info", $okSites."  site(s) found among ".count($array_psql_sites)." => ".number_format(($okSites*100/count($array_psql_sites)), 2)."%");
		echo consoleMessage("info", count($arrMissIdentifier)." missing site(s) among ".count($array_psql_sites)." => ".number_format((count($arrMissIdentifier)*100/count($array_psql_sites)), 2)."%");
	}
	else {

		$fieldIdentifier = "kartatx";
		$fieldRouteName = "kartatx";
		$fieldLan = "kartatx";

		$listKartaTxHardcoded=array("13CSO", "16CNV");
		
		$arrMissKartaTx=$listKartaTxHardcoded;
		foreach ($arrMissKartaTx as $kar) {
			$line=array();
			$line[$fieldIdentifier]=$kar;
			$line[$fieldRouteName]=$kar;
			$line[$fieldLan]=$kar;
			$arrMissIdentifier[]=$line;
		}
	}

	echo consoleMessage("info", count($arrMissKartaTx)."  distinct kartaTx missing");

	if (count($arrMissIdentifier)>0) {

		$json="[";

		$arrDetailsSites=array();
		$qDetailsSites="SELECT * FROM koordinater_mittpunkt_topokartan WHERE kartatx IN ('".implode("', '", $arrMissKartaTx)."')";
		//echo $qDetailsSites;
		$rDetailsSites = pg_query($db_connection, $qDetailsSites);
		while ($rtDetailsSites = pg_fetch_array($rDetailsSites)) {
			if (isset($arrDetailsSites[$rtDetailsSites["kartatx"]]))
				echo consoleMessage("error", "doublon for kartatx ".$rtDetailsSites["kartatx"]." in koordinater_mittpunkt_topokartan"); 

			$arrDetailsSites[$rtDetailsSites["kartatx"]]=$rtDetailsSites;
		}
		
		$nbJson=0;

		// date now
		$micro_date = microtime();
		$date_array = explode(" ",$micro_date);
		$micsec=number_format($date_array[0]*1000, 0, ".", "");
		$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
		if ($micsec==1000) $micsec=999;
		$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";

		foreach ($arrMissIdentifier as $dataMiss) {


			switch($protocol) {
				case "natt":
					$nameSite= $dataMiss["kartatx"].' - NATT';
				break;
				case "punkt":
					$nameSite= $dataMiss[$fieldIdentifier].' - '.$dataMiss[$fieldRouteName];
				break;

			}


			if (!isset($arrDetailsSites[$dataMiss["kartatx"]])) 
				echo consoleMessage("error", "No coordinates for kartatx: '".$rtDetailsSites["kartatx"]."' / ".$dataMiss[$fieldIdentifier]); 
			else {

				

				$json.='
	{
		"siteId" : "'.generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx").'",
		"name" : "'.$nameSite.'",
		"dateCreated" : ISODate("'.$date_now_tz.'"),
		"dateModified" : ISODate("'.$date_now_tz.'"),
		"status" : "active",
		"verificationStatus" : "godkänd",
		"type" : "",
		"kartaTx" : "'.$dataMiss["kartatx"].'",
		"area" : "0",
		"projects" : [
			"'.$commonFields[$protocol]["projectId"].'"
		],
		"geoIndex" : {
			"type" : "Point",
			"coordinates" : [
				'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"].',
				'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"].',
				6
			]
		},
		"extent" : {
			"geometry" : {
				"decimalLongitude" : '.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"].',
				"decimalLatitude" : '.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"].',
				"centre" : [
					'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"].',
					'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"].',
					6
				],
				"aream2" : 0,
				"type" : "Point",
				"coordinates" : [
					'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"].',
					'.$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"].',
					6
				],
				"areaKmSq" : 0
			},
			"source" : "Point"
		},
		"transectParts" : [],
		"lan" : "'.$dataMiss[$fieldLan].'",
		"adminProperties" : {
			"internalSiteId" : "'.$dataMiss[$fieldIdentifier].'"
		},
		"description" : ""
	}
				';
				$nbJson++;

				$json.=',';
			}
		}

		if ($json!="") {

			echo consoleMessage("info", $nbJson." json sites generated among ".count($arrMissIdentifier)." missing => ".number_format(($nbJson*100/count($arrMissIdentifier)), 2)."%");

			// remove the last comma
			$json[strlen($json)-1]=' ';
			$json.=']';

			$protocolFilename=$protocol;
			if ($protocol=="natt") $protocolFilename.=$mode;
			
			$path_json_result="json/".date("Y-m-d_His")."_".$protocolFilename."_emptysites.json";

			if ($fp = fopen($path_json_result, 'w')) {
				fwrite($fp, $json);
				fclose($fp);

				echo "scp ".$path_json_result." ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/sites-natt-punkt-coordinates/json/\n";
				echo 'mongoimport --db ecodata --collection site --jsonArray --file '.$path_json_result."\n";
			}
			else echo consoleMessage("error", "can't create file ".$path);
		}
	}
}
?>