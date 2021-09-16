<?php
$database="SFT";
$dataOrigin="scriptExcel";

require dirname(__FILE__)."/lib/config.php";
require dirname(__FILE__)."/lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

$server=DEFAULT_SERVER;

$arr_protocol=array("std", "natt", "vinter", "sommar", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust");
}
else {

	$protocol=$argv[1];

	echo consoleMessage("info", "Get sites for protocol ".$protocol);

	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]);

	$projectId=$commonFields[$protocol]["projectId"];

	$filter = ['projects' => $projectId];
	$options = [];
	$query = new MongoDB\Driver\Query($filter, $options); 

	$rows = $mng->executeQuery("ecodata.site", $query);

	$arrSites=array();


	$filename="sites_".date("Ymd-His")."_extract_".$database."_".$protocol.".csv";
	$path_extract=PATH_DOWNLOAD."extract/".$database."/".$protocol."/".$filename;

	if ($fp = fopen($path_extract, 'w')) {

		$headers=array("siteId", "name", "karta", "kartaTx", "lan", "verificationStatus", "internalSiteId", "fjall104", "fjall142");
		fputcsv($fp, $headers, ";");

		foreach ($rows as $row){

			$site=array();

			$site["siteId"]=$row->siteId;
			$site["name"]=(isset($row->name) ? $row->name : "");
			$site["karta"]=(isset($row->karta) ? $row->karta : "");
			$site["kartaTx"]=(isset($row->kartaTx) ? $row->kartaTx : "");
			$site["lan"]=(isset($row->lan) ? $row->lan : "");
			$site["verificationStatus"]=(isset($row->verificationStatus) ? $row->verificationStatus : "");
			$site["internalSiteId"]=(isset($row->adminProperties->internalSiteId) ? $row->adminProperties->internalSiteId : "");
			$site["fjall104"]=(isset($row->adminProperties->fjall104) ? $row->adminProperties->fjall104 : "");
			$site["fjall142"]=(isset($row->adminProperties->fjall142) ? $row->adminProperties->fjall142 : "");

			fputcsv($fp, $site, ";");

			$arrSites[]=$site;
		}


		echo consoleMessage("info", count($arrSites)." site(s).");
		echo consoleMessage("info", "File created : ".$path_extract);
	}
	else {
		echo consoleMessage("error", "Can't create file ".$path_extract);
	}

}


?>
