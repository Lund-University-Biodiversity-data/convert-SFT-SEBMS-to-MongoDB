<?php
$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php update_sites.php iwc 2 debug");


$debug=false;
$collection="site";

// parameters
// 1- protocol: iwc (sjöfåglar)
$arr_protocol=array("iwc");

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


	/*
	$qSites="select *
         from
         iwc_koordinater
         order by site
		";
	if (isset($limit) && $limit>0) $limit.=" LIMIT ".$limit;
	$rSites = pg_query($db_connection, $qSites);
	if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

	
	while ($rtSites = pg_fetch_array($rSites)) {
		$arrSitesPsql[$rtSites["site"]]=$rtSites["lokalnamn"];
	}
	*/
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
    	
    	// A FINIR
    	// check the existence of the field
    	// if it exists : DON'T TOUCH, MAYBE ALREADY EDITED ON PURPOSE !
    	// if it does not exist => fill it with SQL value

		if (isset($row->adminProperties->internalSiteId) && $row->adminProperties->internalSiteId!="NO" && trim($row->adminProperties->internalSiteId)!="") {
			$newName=$row->adminProperties->internalSiteId." - ".str_replace(" - IWC", "", $row->name);

			// command to edit the site.
			$cmdSite='db.'.$collection.'.update({"siteId" : "'.$row->siteId.'"}, {$set : {
				"name" : "'.$newName.'"
			}});
	';

			// command to edit all the records with that siteId
			$cmdRecord='db.record.updateMany({"locationID" : "'.$row->siteId.'"}, {$set : {
				"locationName" : "'.$newName.'"
			}});
	';

			/*
			$cmdJs.='db.'.$collection.'.update({"siteId" : "'.$row->siteId.'"}, {$set : {
		';

			$cmdJs.='"name" : "'.$newName.'"';

			$cmdJs.='}});
	';*/
			$cmdJs.=$cmdSite;
			$cmdJs.=$cmdRecord;
		}
		else echo consoleMessage("error", "No internalSiteId for : ".$row->name);



    }

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