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
echo consoleMessage("info", "php updateMedobsForKustTemp.php 2022");


$debug=false;
$collection="site";

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_year=array(2021,2022,2023);

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_year)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_year));
}
else {

	$protocol="kust";
	$year=$argv[1];
	echo consoleMessage("info", "Protocol ".$protocol." => projectId: ".$commonFields[$protocol]["projectId"]);

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	$tableMedobs="kustfagel200_medobs_".$year."_temp";

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    else echo consoleMessage("error", "No connection to mongoDb");

    $filter = ["name" => $commonFields[$protocol]["name"], "data.surveyDate" => ['$gte' => "2023-01-01"]];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    $rows = $mng->executeQuery("ecodata.output", $query);

	$cmdHelpers=array();

	
	foreach ($rows as $row){

		$filter = ["siteId" => $row->data->location];
	    $query = new MongoDB\Driver\Query($filter, []); 
		$rowsSite = $mng->executeQuery("ecodata.site", $query);

		foreach($rowsSite as $rowSite)
			$internalSiteId=$rowSite->adminProperties->internalSiteId;

		$qMedObs="SELECT * from ".$tableMedobs." WHERE ruta='".$internalSiteId."'";
		$rMedobs = pg_query($db_connection, $qMedObs);
		if (!$rMedobs) die("QUERY:" . consoleMessage("error", pg_last_error()));

		if (pg_num_rows($rMedobs)>0) {

			// check if the helpers are already set
			if (($row->data->helpers)!="" && count($row->data->helpers)>0) {
	    		echo consoleMessage("warning", "helpers field is not empty : ".count($row->data->helpers)." row(s)" );
	    		print_r($row->data->helpers);

			}


    		echo consoleMessage("info", pg_num_rows($rMedobs)." line(s) for this output");

    		$helpers= "[ ";
			while ($rtMedobs = pg_fetch_array($rMedobs)) {
				$person=getPersonFromInternalId ($rtMedobs["persnr"], $server);

				$helpers.= '
		                {"helper" : "'.$person["firstName"].' '.$person["lastName"].'"},';
				//$helpers[$rtMedobs["persnr"]]=$person["firstName"].' '.$rtHelpers["lastName"]
			}

			$helpers[strlen($helpers)-1]=' ';
		    $helpers.= "]";


		    $cmd='db.output.update({activityId:"'.$row->activityId.'"}, {$set:{
		    	"data.helpers":'.$helpers.'
		    }})';

		    //echo $cmd; 
		    $cmdHelpers[]=$cmd;
		}

	}

	$filename_json='update_output_helpers_'.$year.'_'.$protocol.'_'.date("Y-m-d-His").'.js';
	$path='dump_json_sft_sebms/'.$database.'/'.$protocol."/".$filename_json;

	if ($fp = fopen($path, 'w')) {
		foreach ($cmdHelpers as $cmd) {
			fwrite($fp, $cmd.";\n");
		}
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



}