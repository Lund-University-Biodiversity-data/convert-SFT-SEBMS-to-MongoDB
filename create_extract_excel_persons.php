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

	$rows = $mng->executeQuery("ecodata.person", $query);

	$arrPersons=array();


	$filename="persons_".date("Ymd-His")."_extract_".$database."_".$protocol.".csv";
	$path_extract=PATH_DOWNLOAD."extract/".$database."/".$protocol."/".$filename;

	if ($fp = fopen($path_extract, 'w')) {

		$headers=array("persnr", "fornamn", "efternamn", "sx", "birthDate", "epost", "telhem", "telmobil", "address1", "address2", "postnr", "ort", "userId", "personId");
		fputcsv($fp, $headers, ";");

		foreach ($rows as $row){

			$person=array();

			if (isset($row->gender)) {
				switch($row->gender) {
					case "man":
						$gender="M";
						break;
					case "kvinna":
						$gender="F";
						break;
					case "annat":
						$gender="annat";
						break;
					default:
						$gender="?";
						break;
				}
			}
			else $gender="";


			$person["persnr"]=(isset($row->internalPersonId) ? $row->internalPersonId : "");
			$person["fornamn"]=(isset($row->firstName) ? $row->firstName : "");
			$person["efternamn"]=(isset($row->lastName) ? $row->lastName : "");
			$person["sx"]=$gender;
			$person["birthDate"]=(isset($row->birthDate) ? $row->birthDate : "");
			$person["epost"]=(isset($row->email) ? $row->email : "");
			$person["telhem"]=(isset($row->phoneNum) ? $row->phoneNum : "");
			$person["telmobil"]=(isset($row->mobileNum) ? $row->mobileNum : "");
			$person["address1"]=(isset($row->address1) ? $row->address1 : "");
			$person["address2"]=(isset($row->address2) ? $row->address2 : "");
			$person["postnr"]=(isset($row->postCode) ? $row->postCode : "");
			$person["ort"]=(isset($row->town) ? $row->town : "");
			$person["userId"]=(isset($row->userId) ? $row->userId : "");
			$person["personId"]=$row->personId;

			fputcsv($fp, $person, ";");

			$arrPersons[]=$person;
		}


		echo consoleMessage("info", count($arrPersons)." person(s).");
		echo consoleMessage("info", "File created : ".$path_extract);
	}
	else {
		echo consoleMessage("error", "Can't create file ".$path_extract);
	}

}




/*
// check no missing data
foreach ($arrPersons as $person){

	if (!isset($arrPersonsDetails[$person]["name"]))	{
		echo "No data found for person ".$person;

		echo consoleMessage("error", "No data found for person ".$person);
		$arrPersonsDetails[$person]["name"]="ERROR - see console for details";
		$arrPersonsDetails[$person]["email"]="ERROR - see console for details";
	}

}
*/


?>
