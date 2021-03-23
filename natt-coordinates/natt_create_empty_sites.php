<?php
$database="SFT";
require "../lib/config.php";
require "../lib/functions.php";
//require_once "lib/PHPExcel/Classes/PHPExcel.php";
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php natt_create_empty_sites.php");

$debug=false;

if (isset($argv[1]) && $argv[1]=="debug") {
	$debug=true;
	echo consoleMessage("info", "DEBUG mode");
}


echo "****CONVERT ".$database." ".$protocol." to MongoDB JSON****\n";

$array_psql_sites=array();

$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

$qSites='SELECT DISTINCT kartatx FROM totalnatt;';
$rSites = pg_query($db_connection, $qSites);
while ($rtSites = pg_fetch_array($rSites)) {
	$array_psql_sites[]=$rtSites["kartatx"];
}

echo consoleMessage("info", count($array_psql_sites)." site(s) found in psql"); 

$array_mongo_sites=getArraySitesFromMongo ("natt", $commonFields["natt"]["projectId"]);

echo consoleMessage("info", count($array_mongo_sites)." site(s) found in mongoDB"); 


$arrMiss=array();
foreach ($array_psql_sites as $sqlSite) {
	if (!isset($array_mongo_sites[$sqlSite])) {
		$arrMiss[]=$sqlSite;
		//echo "Missing ".$sqlSite."\n";
	}
}
if ($debug) print_r($arrMiss);

echo consoleMessage("info", count($arrMiss)." missing site(s) among ".count($array_psql_sites)." => ".number_format((count($arrMiss)*100/count($array_psql_sites)), 2)."%");

if (count($arrMiss)>0) {

	$json="[";

	$arrDetailsSites=array();
	$qDetailsSites="SELECT * FROM koordinater_mittpunkt_topokartan WHERE kartatx IN ('".implode("', '", $arrMiss)."')";
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

	foreach ($arrMiss as $kartatx) {
		if (!isset($arrDetailsSites[$kartatx])) 
			echo consoleMessage("error", "No coordinates for kartatx ".$rtDetailsSites["kartatx"]); 
		else {

			

			$json.='
{
	"dateCreated" : ISODate("'.$date_now_tz.'"),
	"dateModified" : ISODate("'.$date_now_tz.'"),
	"status" : "active",
	"description" : "",
	"geoIndex" : {
		"type" : "Point",
		"coordinates" : [
			'.$arrDetailsSites[$kartatx]["wgs84_lon"].',
			'.$arrDetailsSites[$kartatx]["wgs84_lat"].',
			6
		]
	},
	"siteId" : "'.generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx").'",
	"extent" : {
		"geometry" : {
			"decimalLongitude" : '.$arrDetailsSites[$kartatx]["wgs84_lon"].',
			"decimalLatitude" : '.$arrDetailsSites[$kartatx]["wgs84_lat"].',
			"centre" : [
				'.$arrDetailsSites[$kartatx]["wgs84_lon"].',
				'.$arrDetailsSites[$kartatx]["wgs84_lat"].',
				6
			],
			"aream2" : 0,
			"type" : "Point",
			"coordinates" : [
				'.$arrDetailsSites[$kartatx]["wgs84_lon"].',
				'.$arrDetailsSites[$kartatx]["wgs84_lat"].',
				6
			],
			"areaKmSq" : 0
		},
		"source" : "Point"
	},
	"kartaTx" : "'.$kartatx.'",
	"projects" : [
		"'.$commonFields["natt"]["projectId"].'"
	],
	"transectParts" : [],
	"name" : "'.$kartatx.' - no detail",
	"area" : "0",
	"commonName" : "'.$kartatx.' - no detail",
	"yearStarted" : 0,
	"type" : ""
}
			';
			$nbJson++;

			$json.=',';
		}
	}

	if ($json!="") {

		echo consoleMessage("info", $nbJson." json sites generated among ".count($arrMiss)." missing => ".number_format(($nbJson*100/count($arrMiss)), 2)."%");

		// remove the last comma
		$json[strlen($json)-1]=' ';
		$json.=']';

		$path_json_result="json/".date("Y-m-d_His")."_emptysites.json";

		if ($fp = fopen($path_json_result, 'w')) {
			fwrite($fp, $json);
			fclose($fp);

			echo "scp ".$path_json_result." ubuntu@89.45.233.195:/home/ubuntu/\n";
			echo 'mongoimport --db ecodata --collection site --jsonArray --file '.$path_json_result."\n";
		}
		else echo consoleMessage("error", "can't create file ".$path);
	}
}
?>