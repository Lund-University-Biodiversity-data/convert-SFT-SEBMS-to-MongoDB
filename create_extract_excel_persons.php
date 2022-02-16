<?php
$database="SFT";
$dataOrigin="scriptExcel";

require dirname(__FILE__)."/lib/config.php";
require dirname(__FILE__)."/lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

$server=DEFAULT_SERVER;
$hub=SFT_HUB;

function getGenderCode($gender) {

	switch($gender) {
		case "man":
			return "M";
			break;
		case "kvinna":
			return "F";
			break;
		case "annat":
			return "annat";
			break;
		default:
			return "?";
			break;
	}

}
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc", "all");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust / iwc / all");
}
else {

	$protocol=$argv[1];

	echo consoleMessage("info", "Get sites for protocol ".$protocol);


	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]);

	if ($protocol!="all") {
		$projectId=$commonFields[$protocol]["projectId"];
		echo consoleMessage("info", "ProjectId : ".$projectId);

		$fieldNameFilterProject="projectId";
		$fieldValueFilterProject=$projectId;
	}
	else {
		$projectId="";
		echo consoleMessage("info", "All projects from SFT hub");

		$fieldNameFilterProject="hub";
		$fieldValueFilterProject=$hub;
	}
	

	$arrPersons=array();

	// if the years are specified, we'll select only the persons who participed in the program specified during the year specified
	if (isset($argv[2]) && $argv[2]!="___") {
		echo consoleMessage("info", "Get years ".$argv[2]);

		$explodYrs=explode("_", $argv[2]);
		$yrStart=$explodYrs[1];
		$yrEnd=$explodYrs[2];

		echo consoleMessage("info", "From ".($yrStart!="" ? $yrStart : "-empty-")." to ".($yrEnd!="" ? $yrEnd : "-empty-"));


        $aggregate = [ 
            'aggregate' => "output", 
            'pipeline' =>[
                ['$lookup'=>[
                    'from'=>'activity',
                    'localField'=>'activityId',
                    'foreignField'=>'activityId',
                    'as'=>'act'
                ]],
                ['$match'=>[
                	"act.projectId" => $projectId, // can be removed later if protocol = all
                	"act.status" => [
                		'$in' => ["active", "under review"]
                	]
                ]],
			    ['$lookup'=>[
			        'from'=>'person',
			        'localField'=>'act.personId',
			        'foreignField'=>'personId',
			        'as'=>'pers'
			    ]],
			    ['$match'=>[
			    	"pers.personId" => ['$exists'=> 1] // useless but if the match is empty it doesn't work
                	// will add later inthe code the hub=sft if protocol=all
                ]],
                ['$project'=>[
                    "activityId" => 1,
                    "data.surveyDate" => 1,
                    "act.personId" => 1,
                    "name" => 1,
			        "pers.internalPersonId" => 1,
			        "pers.firstName" => 1,
			        "pers.lastName" => 1,
			        "pers.gender" => 1,
			        //"pers.birthDate" => 1,
			        "pers.email" => 1,
			        "pers.phoneNum" => 1,
			        "pers.mobileNum" => 1,
			        "pers.address1" => 1,
			        "pers.address2" => 1,
			        "pers.postCode" => 1,
			        "pers.town" => 1,
			        "pers.userId" => 1,
                ]]
            ],
            'cursor' => new stdClass,
        ];

        // remove the filter project when all protocol. Add a filter on the hub
		if($protocol=="all"){
			unset($aggregate["pipeline"][1]['$match']["act.projectId"]);
			$aggregate["pipeline"][3]['$match']["pers.hub"]="sft";

		}

        try{
            $command = new MongoDB\Driver\Command($aggregate);

            $rt = $mng->executeCommand(MONGODB_NAME, $command);
            $rtArray=$rt->toArray();

            foreach ($rtArray as $output) {
            	//echo $output->activityId.":".$output->data->surveyDate." => ".$output->act[0]->personId."\n";

            	// get the date and fix it if needed with timezone
            	$eventDate=getEventDateAfterTimeZone($output->data->surveyDate);

            	// get the year and fix it based on the protocol
            	$year=getYearFromSurveyDateAndProtocol($eventDate, $protocol);

            	$okOutput=true;
            	if (is_numeric($yrStart) && $yrStart>$year) $okOutput=false;
            	if (is_numeric($yrEnd) && $yrEnd<$year) $okOutput=false;

            	if ($okOutput) {
            		if(!isset($arrPersons[$output->act[0]->personId])) {
	            		//echo $year. " OK => only from ".$yrStart." to ".$yrEnd."\n";
	            		$person=array();

						$gender="";
						if (isset($output->pers[0]->gender)) {
							$gender=getGenderCode($output->pers[0]->gender);
						}

						$person["persnr"]=(isset($output->pers[0]->internalPersonId) ? $output->pers[0]->internalPersonId : "");
						$person["fornamn"]=(isset($output->pers[0]->firstName) ? $output->pers[0]->firstName : "");
						$person["efternamn"]=(isset($output->pers[0]->lastName) ? $output->pers[0]->lastName : "");
						$person["sx"]=$gender;
						//$person["birthDate"]=(isset($output->pers[0]->birthDate) ? $output->pers[0]->birthDate : "");
						$person["epost"]=(isset($output->pers[0]->email) ? $output->pers[0]->email : "");
						$person["telhem"]=(isset($output->pers[0]->phoneNum) ? $output->pers[0]->phoneNum : "");
						$person["telmobil"]=(isset($output->pers[0]->mobileNum) ? $output->pers[0]->mobileNum : "");
						$person["address1"]=(isset($output->pers[0]->address1) ? $output->pers[0]->address1 : "");
						$person["address2"]=(isset($output->pers[0]->address2) ? $output->pers[0]->address2 : "");
						$person["postnr"]=(isset($output->pers[0]->postCode) ? $output->pers[0]->postCode : "");
						$person["ort"]=(isset($output->pers[0]->town) ? $output->pers[0]->town : "");
						$person["userId"]=(isset($output->pers[0]->userId) ? $output->pers[0]->userId : "");
						$person["personId"]=$output->act[0]->personId;

						$person[$output->name]=$output->name;


						$arrPersons[$output->act[0]->personId]=$person;
					}
					else {
						if (!isset($arrPersons[$output->act[0]->personId][$output->name])) {
							$arrPersons[$output->act[0]->personId][$output->name]=$output->name;
						}
					}
            	}
            	else {

            		//echo $year. " refused => only from ".$yrStart." to ".$yrEnd."\n";
            	}
            	
            }

        } catch(\Exception $e){
           echo "Exception:", $e->getMessage(), "\n";
        }

	}
	else {
		echo consoleMessage("info", "No years specified");

		if($protocol=="all"){
			$filter = ["hub" => SFT_HUB];
		}
		else {
			$filter = ['projects' => $commonFields[$protocol]["projectId"]];
		}

		//$options = ['limit' => 20];
		$options = [];

		$query = new MongoDB\Driver\Query($filter, $options); 

		$rows = $mng->executeQuery("ecodata.person", $query);


		foreach ($rows as $row){

			$person=array();

			$gender="";
			if (isset($row->gender)) {
				$gender=getGenderCode($row->gender);
			}

			$person["persnr"]=(isset($row->internalPersonId) ? $row->internalPersonId : "");
			$person["fornamn"]=(isset($row->firstName) ? $row->firstName : "");
			$person["efternamn"]=(isset($row->lastName) ? $row->lastName : "");
			$person["sx"]=$gender;
			//$person["birthDate"]=(isset($row->birthDate) ? $row->birthDate : "");
			$person["epost"]=(isset($row->email) ? $row->email : "");
			$person["telhem"]=(isset($row->phoneNum) ? $row->phoneNum : "");
			$person["telmobil"]=(isset($row->mobileNum) ? $row->mobileNum : "");
			$person["address1"]=(isset($row->address1) ? $row->address1 : "");
			$person["address2"]=(isset($row->address2) ? $row->address2 : "");
			$person["postnr"]=(isset($row->postCode) ? $row->postCode : "");
			$person["ort"]=(isset($row->town) ? $row->town : "");
			$person["userId"]=(isset($row->userId) ? $row->userId : "");
			$person["personId"]=$row->personId;
			$person["protocols"]=$protocol;

			$arrPersons[]=$person;
		}


	}

//print_r($arrPersons);

	$filename="persons_".date("Ymd-His")."_extract_".$database."_".$protocol.".csv";
	$path_extract=PATH_DOWNLOAD."extract/".$database."/".$protocol."/".$filename;

	if ($fp = fopen($path_extract, 'w')) {

		$headers=array("persnr", "fornamn", "efternamn", "sx", /*"birthDate", */"epost", "telhem", "telmobil", "address1", "address2", "postnr", "ort", "userId", "personId", "protocols");
		fputcsv($fp, $headers, ";");


		foreach($arrPersons as $person) {
			fputcsv($fp, $person, ";");
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
