<?php
$database="SFT";
require "../lib/config.php";
require "../lib/functions.php";
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";


echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php create_empty_sites.php natt totalnatt");


$arr_protocol=array("natt", "punkt", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: natt / punkt / iwc");
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

			$mode=$argv[2];
		}
	}
	else {

	}


	echo "****CONVERT ".$database." ".$protocol." to MongoDB JSON****\n";

	$array_psql_sites=array();

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	echo consoleMessage("info", "connected to ".$DB["database"]);

	if ($protocol=="punkt" || $protocol=="iwc" || $mode=="totalnatt") {


		switch($protocol) {
			case "natt":

				$qSites='SELECT DISTINCT kartatx FROM totalnatt;';

				$projectId=$commonFields["natt"]["projectId"];

				$fieldIdentifier = "kartatx";
				$fieldRouteName = "kartatx";
				$fieldLan = "lan";

				break;

			case "iwc":

				/*$qSites=' SELECT DISTINCT J.site as internalsiteid, K.lokalnamn, K.lan 
							FROM total_iwc_januari J, iwc_koordinater K
							WHERE J.site=K.site 
							UNION 
							SELECT DISTINCT S.site as internalsiteid, K.lokalnamn, K.lan 
							FROM total_iwc_september S, iwc_koordinater K
							WHERE S.site=K.site 
							ORDER BY internalsiteid';*/
				$qSites=' SELECT site as internalsiteid, lokalnamn, lan, goose, helcom_sub, ln_karta, ki, ev, area
							FROM iwc_koordinater';

				$fieldIdentifier = "internalsiteid";
				$fieldRouteName = "lokalnamn";
				$fieldLan = "lan";
				
				$projectId=$commonFields["iwc"]["projectId"];

				break;

			case "punkt":

				$fieldRouteName = "ruttnamn";
				$fieldIdentifier = "internalsiteid";
				$fieldLan = "lan";

				$qSites="select CONCAT(persnr, '-', rnr ) as internalsiteid, kartatx, ruttnamn, lan 
					from (
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

			if ($protocol!="iwc"){
				$line["kartatx"]=$rtSites["kartatx"];
			}
			else {
				$specificAdmin=array();
				$specificAdmin["goose"]=$rtSites["goose"];
				$specificAdmin["helcom_sub"]=$rtSites["helcom_sub"];
				$specificAdmin["ln_karta"]=$rtSites["ln_karta"];
				$specificAdmin["ki"]=$rtSites["ki"];
				$specificAdmin["ev"]=$rtSites["ev"];
				$specificAdmin["area"]=$rtSites["area"];

				$line["adminSpecific"]=$specificAdmin;
			}

			$line[$fieldIdentifier]=$rtSites[$fieldIdentifier];
			$line[$fieldRouteName]=$rtSites[$fieldRouteName];
			$line[$fieldLan]=$rtSites[$fieldLan];
			$array_psql_sites[]=$line;
		}

		echo consoleMessage("info", count($array_psql_sites)." site(s) found in psql"); 

		$array_mongo_sites=getArraySitesFromMongo ($projectId, DEFAULT_SERVER);

		echo consoleMessage("info", count($array_mongo_sites)." site(s) found in mongoDB"); 


		$arrMissCodeSite=array();
		$arrMissIdentifier=array();
		$okSites=0;
		foreach ($array_psql_sites as $sqlSite) {
			if (!isset($array_mongo_sites[$sqlSite[$fieldIdentifier]])) {
				if ($protocol=="iwc")
					$arrMissCodeSite[]=$sqlSite[$fieldIdentifier];
				elseif (!in_array($sqlSite["kartatx"], $arrMissCodeSite) )
					$arrMissCodeSite[]=$sqlSite["kartatx"];

				$line=array();

				if ($protocol!="iwc"){
					$line["kartatx"]=$sqlSite["kartatx"];
				}
				else {
					$line["adminSpecific"]=$sqlSite["adminSpecific"];
				}

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
		

		

		echo consoleMessage("info", $okSites."  site(s) found among ".count($array_psql_sites)." => ".number_format(($okSites*100/count($array_psql_sites)), 2)."%");
		echo consoleMessage("info", count($arrMissIdentifier)." missing site(s) among ".count($array_psql_sites)." => ".number_format((count($arrMissIdentifier)*100/count($array_psql_sites)), 2)."%");
	}
	elseif ($protocol=="natt" && $mode=="list") {

		if (!isset($argv[3])) {
			echo consoleMessage("error", "Argument missing with list codes like 88NBG#56HYT#90kKOI");
			exit();
		}
		else {
			$explList=explode("#", $argv[3]);
		}

		foreach ($explList as $site) {
			if (strlen($site)!=5) {
				echo consoleMessage("error", "Wrong site code, must be 5 caracters : ".$site);
				exit();
			}
		}
		$fieldIdentifier = "kartatx";
		$fieldRouteName = "kartatx";
		$fieldLan = "lan";

		$listKartaTxHardcoded=$explList;
		
		$arrMissCodeSite=$listKartaTxHardcoded;
		foreach ($arrMissCodeSite as $kar) {
			$line=array();
			$line[$fieldIdentifier]=$kar;
			$line[$fieldRouteName]=$kar;
			$line[$fieldLan]="";
			$arrMissIdentifier[]=$line;
		}
	}
	else {
		echo consoleMessage("error", "wrong protocol/mode => stop ".$protocol."/".$mode);
		exit();
	}
	echo consoleMessage("info", count($arrMissCodeSite)." distinct kartaTx missing");

	if (count($arrMissIdentifier)>0) {

		$json="[";

		$arrDetailsSites=array();

		if ($protocol=="iwc") {
			$qDetailsSites="SELECT * FROM iwc_koordinater WHERE site IN ('".implode("', '", $arrMissCodeSite)."')";
			//echo $qDetailsSites;
			$rDetailsSites = pg_query($db_connection, $qDetailsSites);
			while ($rtDetailsSites = pg_fetch_array($rDetailsSites)) {
				$arrDetailsSites[$rtDetailsSites["site"]]=$rtDetailsSites;
			}

		}
		else {
			$qDetailsSites="SELECT * FROM koordinater_mittpunkt_topokartan WHERE kartatx IN ('".implode("', '", $arrMissCodeSite)."')";
			//echo $qDetailsSites;
			$rDetailsSites = pg_query($db_connection, $qDetailsSites);
			while ($rtDetailsSites = pg_fetch_array($rDetailsSites)) {
				if (isset($arrDetailsSites[$rtDetailsSites["kartatx"]]))
					echo consoleMessage("error", "doublon for kartatx ".$rtDetailsSites["kartatx"]." in koordinater_mittpunkt_topokartan"); 

				$arrDetailsSites[$rtDetailsSites["kartatx"]]=$rtDetailsSites;
			}

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

			$okAdd=true;
			$adminPropertiesSpecific="";

			switch($protocol) {
				case "natt":
					if (!isset($arrDetailsSites[$dataMiss["kartatx"]])) {
						echo consoleMessage("error", "No data for ".$dataMiss["kartatx"]);
						exit(); 
					}
					$longitude=$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"];
					$latitude=$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"];
					$nameSite= $dataMiss["kartatx"].' - NATT';

					$identif="kartatx";

					$adminPropertiesSpecific.=',
			"bookingComment": "",
			"paperSurveySubmitted": ""';

				break;
				case "punkt":
					$longitude=$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lon"];
					$latitude=$arrDetailsSites[$dataMiss["kartatx"]]["wgs84_lat"];
					$nameSite= $dataMiss[$fieldIdentifier].' - '.$dataMiss[$fieldRouteName];

					$identif="kartatx";
				break;
				case "iwc":

					$identif="internalsiteid";

					if (trim($arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lon"])=="" || trim($arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lat"])=="") {
						echo consoleMessage("error", "Coordinates missing for ".$dataMiss[$identif].": ".$arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lon"]." / ".$arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lat"]); 

						$okAdd=false;

					}
					$longitude=$arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lon"];
					$latitude=$arrDetailsSites[$dataMiss[$identif]]["mitt_wgs84_lat"];
					$nameSite=$dataMiss["lokalnamn"].' - IWC';

					$adminPropertiesSpecific="";
					foreach($dataMiss["adminSpecific"] as $keySpecific => $valueSpecific){
						$adminPropertiesSpecific.=',
							"'.$keySpecific.'": "'.$valueSpecific.'"';
					}

				break;

			}


			if (!$okAdd || !isset($arrDetailsSites[$dataMiss[$identif]])) {
				echo consoleMessage("error", "No coordinates for ".$dataMiss[$identif]." / ".$dataMiss[$fieldIdentifier]); 
			}
			else {

				// "kartaTx" : "'.$dataMiss["kartatx"].'",

				$json.='
	{
		"siteId" : "'.generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx").'",
		"name" : "'.$nameSite.'",
		"dateCreated" : ISODate("'.$date_now_tz.'"),
		"dateModified" : ISODate("'.$date_now_tz.'"),
		"status" : "active",
		"verificationStatus" : "godkänd",
		"type" : "",
		"area" : "0",
		"projects" : [
			"'.$commonFields[$protocol]["projectId"].'"
		],
		"geoIndex" : {
			"type" : "Point",
			"coordinates" : [
				'.$longitude.',
				'.$latitude.',
				6
			]
		},
		"extent" : {
			"geometry" : {
				"decimalLongitude" : '.$longitude.',
				"decimalLatitude" : '.$latitude.',
				"centre" : [
					'.$longitude.',
					'.$latitude.',
					6
				],
				"aream2" : 0,
				"type" : "Point",
				"coordinates" : [
					'.$longitude.',
					'.$latitude.',
					6
				],
				"areaKmSq" : 0
			},
			"source" : "Point"
		},
		"transectParts" : [],
		"adminProperties" : {
			"internalSiteId" : "'.$dataMiss[$fieldIdentifier].'",
			"lan" : "'.$dataMiss[$fieldLan].'"'.
			$adminPropertiesSpecific.'
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

				echo "scp ".$path_json_result." radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/sites-natt-punkt-iwc-coordinates/json/\n";
				echo "scp ".$path_json_result." ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/sites-natt-punkt-iwc-coordinates/json/\n";
				echo 'mongoimport --db ecodata --collection site --jsonArray --file '.$path_json_result."\n";


				echo "DON'T FORGET OT ADD THE SITEid TO THE PROJECTACTIVITYid\n";

				echo 'db.projectActivity.update({projectActivityId:"'.$commonFields[$protocol]["projectActivityId"].'"}, {"$push":{sites : SITEID}})'."\n";
			}
			else echo consoleMessage("error", "can't create file ".$path);
		}
	}
}
?>