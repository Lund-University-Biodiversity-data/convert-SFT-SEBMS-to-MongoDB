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
echo consoleMessage("info", "php update_user_id_from_persnr.php iwc");


$debug=false;
$collection="site";

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust / iwc");
}
else {

	$protocol=$argv[1];

	echo consoleMessage("info", "Protocol ".$protocol." => projectId: ".$commonFields[$protocol]["projectId"]);

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	$qSites='SELECT DISTINCT persnr from total_iwc_januari
		UNION
		SELECT DISTINCT persnr from total_iwc_september
		ORDER BY persnr
		';
	$rSites = pg_query($db_connection, $qSites);
	if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));


	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    else echo consoleMessage("error", "No connection to mongoDb");


	$cmdJs="";

	while ($rtSites = pg_fetch_array($rSites)) {
		// get the person
		$person=getPersonFromInternalId ($rtSites["persnr"], $server);

		if (isset($person["userId"]) && $person["userId"]!="5" && $person["userId"]!=""){

			// get all the activitys this person is in or this project

		    $filter = ['projectId' => $commonFields[$protocol]["projectId"], 'personId' => $person["personId"]];
		    $options = [];
		    $query = new MongoDB\Driver\Query($filter, $options); 
		    $rows = $mng->executeQuery("ecodata.activity", $query);
		    $arrActivityId=array();
		    foreach ($rows as $row){
		        $arrActivityId[]=$row->activityId;
		    }


			if (count($arrActivityId)>0) {
				// update these activities
				$cmdJs.='db.activity.updateMany({
					"projectId" : "'.$commonFields[$protocol]["projectId"].'",
					"personId" : "'.$person["personId"].'"
				}, {$set : {
					"userId" : "'.$person["userId"].'"
				}});
		';

					$cmdJs.='db.record.updateMany({
					"activityId" : {"$in" : ["'.implode('",
					 "', $arrActivityId).'"]}
				}, {$set : {
					"userId" : "'.$person["userId"].'"
				}});
		';
			}

		}
	}


	$filename_json='update_activity_'.$database.'_'.$protocol.'_'.date("Y-m-d-His").'.js';
	$path='dump_json_sft_sebms/'.$database.'/'.$protocol."/".$filename_json;

	if ($fp = fopen($path, 'w')) {
		fwrite($fp, $cmdJs);
		fclose($fp);
		echo consoleMessage("info", "File ".$path." created");

		$cmd='mongo ecodata < '.$path;
		echo consoleMessage("info", "Command : ".$cmd);

		$scp='scp '.$path.' radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/'.$path;
		echo consoleMessage("info", "Command scp DEV : ".$scp);
		$scp='scp '.$path.' ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/'.$path;
		echo consoleMessage("info", "Command scp PROD : ".$scp);
	}
	else echo consoleMessage("error", "can't create file ".$path);



}