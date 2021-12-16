<?php
$database="SFT";
$dataOrigin="scriptExcel";

require dirname(__FILE__)."/lib/config.php";
require dirname(__FILE__)."/lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

$server=DEFAULT_SERVER;

$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust / iwc");
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

		$headers=array("siteId", "name", "internalSiteId", "routetype", "lan", "lsk", "verificationStatus", "bookingComment", "paperSurveySubmitted", "summarySurveySubmitted", "bookedBy", "decimalLatitude", "decimalLongitude");

		switch ($protocol) {

			case "iwc":
				array_push($headers, "goose", "helcom_sub", "ln_karta", "ki", "ev", "area");
				break;

			default: 
				array_push($headers, "fjall104", "fjall142");
				break;
		}
		fputcsv($fp, $headers, ";");

		foreach ($rows as $row){

			$site=array();

			$site["siteId"]=$row->siteId;
			$site["name"]=(isset($row->name) ? $row->name : "");
			$site["internalSiteId"]=(isset($row->adminProperties->internalSiteId) ? $row->adminProperties->internalSiteId : "");
			$site["routetype"]=(isset($row->adminProperties->routetype) ? $row->adminProperties->routetype : "");
			//$site["karta"]=(isset($row->karta) ? $row->karta : "");
			//$site["kartaTx"]=(isset($row->kartaTx) ? $row->kartaTx : "");
			$site["lan"]=(isset($row->adminProperties->lan) ? $row->adminProperties->lan : "");
			$site["lsk"]=(isset($row->adminProperties->lsk) ? $row->adminProperties->lsk : "");
			$site["verificationStatus"]=(isset($row->verificationStatus) ? $row->verificationStatus : "");
			$site["bookingComment"]=(isset($row->adminProperties->bookingComment) ? $row->adminProperties->bookingComment : "");	
			$site["paperSurveySubmitted"]=(isset($row->adminProperties->paperSurveySubmitted) ? $row->adminProperties->paperSurveySubmitted : "");			
			$site["summarySurveySubmitted"]=(isset($row->adminProperties->summarySurveySubmitted) ? $row->adminProperties->summarySurveySubmitted : "");			

			$site["bookedBy"]=(isset($row->bookedBy) ? $row->bookedBy : "");
			$site["decimalLatitude"]=(isset($row->extent->geometry->decimalLatitude) ? $row->extent->geometry->decimalLatitude : "");
			$site["decimalLongitude"]=(isset($row->extent->geometry->decimalLongitude) ? $row->extent->geometry->decimalLongitude : "");

			switch ($protocol) {

				case "iwc":

					$site["goose"]=(isset($row->adminProperties->goose) ? $row->adminProperties->goose : "");
					$site["helcom_sub"]=(isset($row->adminProperties->helcom_sub) ? $row->adminProperties->helcom_sub : "");
					$site["ln_karta"]=(isset($row->adminProperties->ln_karta) ? $row->adminProperties->ln_karta : "");
					$site["ki"]=(isset($row->adminProperties->ki) ? $row->adminProperties->ki : "");
					$site["ev"]=(isset($row->adminProperties->ev) ? $row->adminProperties->ev : "");
					$site["area"]=(isset($row->adminProperties->area) ? $row->adminProperties->area : "");
					break;

				default: 
					
					$site["fjall104"]=(isset($row->adminProperties->fjall104) ? $row->adminProperties->fjall104 : "");
					$site["fjall142"]=(isset($row->adminProperties->fjall142) ? $row->adminProperties->fjall142 : "");
					break;
			}

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
