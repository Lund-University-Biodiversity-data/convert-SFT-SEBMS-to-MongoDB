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
		$projectActivityId=$commonFields[$protocol]["projectActivityId"];
		echo consoleMessage("info", "ProjectId : ".$projectId);

	}
	else {
		$projectId="";
		$projectActivityId="";
		echo consoleMessage("info", "All projects from SFT hub");
	}
	

	$arrPersons=array();

	// if the years are specified, we'll select only the persons who participed in the program specified during the year specified
	if (isset($argv[2]) && $argv[2]!="___") {
		echo consoleMessage("info", "Get years ".$argv[2]);

		$explodYrs=explode("_", $argv[2]);
		$yrStart=$explodYrs[1];
		$yrEnd=$explodYrs[2];

		echo consoleMessage("info", "From ".($yrStart!="" ? $yrStart : "-empty-")." to ".($yrEnd!="" ? $yrEnd : "-empty-"));

		$arrInternalSiteId["surveyors"]=array();
		$arrInternalSiteId["helpers"]=array();

		// 2 loops : one for the personId, the 2nd for the medinventerare (helperId)
		for ($iLoop=1;$iLoop<=2;$iLoop++) {

			// get all the persons involved as personId
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
	                	"act.projectActivityId" => $projectActivityId, // can be removed later if protocol = all
	                	"act.status" => [
	                		'$in' => ["active"]
	                	],
	                	"act.verificationStatus" => [
	                		'$in' => ["approved", "under review"]
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
                    ['$lookup'=>[
					    'from'=>'site',
					    'localField'=>'act.siteId',
					    'foreignField'=>'siteId',
					    'as'=>'siteID'
					]],
			        ['$unwind'=> '$siteID'],
	                ['$project'=>[
	                    "activityId" => 1,
	                    "data.surveyDate" => 1,
	                    "siteID.adminProperties.internalSiteId" => 1,
	                    "pers.personId" => 1,
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
				        "pers.anonymizedId" => 1,
				        "pers.userId" => 1,
	                ]]
	            ],
	            'cursor' => new stdClass,
	        ];

	        // remove the filter project when all protocol. Add a filter on the hub
			if($protocol=="all"){
				unset($aggregate["pipeline"][1]['$match']["act.projectActivityId"]);
				$aggregate["pipeline"][3]['$match']["pers.hub"]="sft";

			}

			// for the 2nd round, remove the link to activity based on 
			if ($iLoop==2) {
				$aggregate["pipeline"][2]['$lookup']['localField']='act.helperIds';
			}


	        try{
	            $command = new MongoDB\Driver\Command($aggregate);

	            $rt = $mng->executeCommand(MONGODB_NAME, $command);
	            $rtArray=$rt->toArray();

	            foreach ($rtArray as $output) {
	            	//if ($iLoop==2) echo $output->activityId.":".$output->data->surveyDate." => ".$output->pers[$iPers]->personId."\n";
//print_r($output);
	            	// get the date and fix it if needed with timezone
	            	$eventDate=getEventDateAfterTimeZone($output->data->surveyDate);

	            	// get the year and fix it based on the protocol
	            	$year=getYearFromSurveyDateAndProtocol($eventDate, $protocol);

	            	$okOutput=true;
	            	if (is_numeric($yrStart) && $yrStart>$year) $okOutput=false;
	            	if (is_numeric($yrEnd) && $yrEnd<$year) $okOutput=false;

	            	if ($okOutput) {

						// loop through persons, since there could be several !
						//if (count($output->pers)!=1) 
						//	echo consoleMessage("warn", count($output->pers)." for output->activityId ".$output->activityId  );

						for ($iPers=0;$iPers<count($output->pers); $iPers++){


							if(!isset($arrPersons[$output->pers[$iPers]->personId])) {
			            		//echo $year. " OK => only from ".$yrStart." to ".$yrEnd." - ".$output->activityId."\n";
			            		$person=array();

								$gender="";
								if (isset($output->pers[$iPers]->gender)) {
									$gender=getGenderCode($output->pers[$iPers]->gender);
								}

								$person["persnr"]=(isset($output->pers[$iPers]->internalPersonId) ? $output->pers[$iPers]->internalPersonId : "");
								$person["fornamn"]=(isset($output->pers[$iPers]->firstName) ? $output->pers[$iPers]->firstName : "");
								$person["efternamn"]=(isset($output->pers[$iPers]->lastName) ? $output->pers[$iPers]->lastName : "");
								$person["sx"]=$gender;
								//$person["birthDate"]=(isset($output->pers[$iPers]->birthDate) ? $output->pers[$iPers]->birthDate : "");
								$person["epost"]=(isset($output->pers[$iPers]->email) ? $output->pers[$iPers]->email : "");
								$person["telhem"]=(isset($output->pers[$iPers]->phoneNum) ? $output->pers[$iPers]->phoneNum : "");
								$person["telmobil"]=(isset($output->pers[$iPers]->mobileNum) ? $output->pers[$iPers]->mobileNum : "");
								$person["address1"]=(isset($output->pers[$iPers]->address1) ? $output->pers[$iPers]->address1 : "");
								$person["address2"]=(isset($output->pers[$iPers]->address2) ? $output->pers[$iPers]->address2 : "");
								$person["postnr"]=(isset($output->pers[$iPers]->postCode) ? $output->pers[$iPers]->postCode : "");
								$person["ort"]=(isset($output->pers[$iPers]->town) ? $output->pers[$iPers]->town : "");
								$person["anonymizedId"]=(isset($output->pers[$iPers]->anonymizedId) ? $output->pers[$iPers]->anonymizedId : "");
								$person["userId"]=(isset($output->pers[$iPers]->userId) ? $output->pers[$iPers]->userId : "");
								$person["personId"]=$output->pers[$iPers]->personId;

								// to specify if the person is huvudinventerare (personId) or medinventerare (helperId)
								if ($iLoop==1) {
									$person["huvudinventerare"]="X";
									$person["huv-inv-lokal"][]=$output->siteID->adminProperties->internalSiteId;
									$person["medinventerare"]="";
									$person["med-inv-lokal"]=array();
								}
								else {
									$person["huvudinventerare"]="";
									$person["huv-inv-lokal"]=array();
									$person["medinventerare"]="X";
									$person["med-inv-lokal"][]=$output->siteID->adminProperties->internalSiteId;
								}
								$person[$output->name]=$output->name;


								$arrPersons[$output->pers[$iPers]->personId]=$person;
								//if ($iLoop==2) echo "OK person (".$output->pers[$iPers]->firstName.$output->pers[$iPers]->lastName.") fron activity ".$output->activityId."\n";
								
							}
							else {

								if ($iLoop==1) {
									$arrPersons[$output->pers[$iPers]->personId]["huvudinventerare"]="X";
									$arrPersons[$output->pers[$iPers]->personId]["huv-inv-lokal"][]=$output->siteID->adminProperties->internalSiteId;
								}
								else {
									$arrPersons[$output->pers[$iPers]->personId]["medinventerare"]="X";
									$arrPersons[$output->pers[$iPers]->personId]["med-inv-lokal"][]=$output->siteID->adminProperties->internalSiteId;
								}


								if (!isset($arrPersons[$output->pers[$iPers]->personId][$output->name])) {
									$arrPersons[$output->pers[$iPers]->personId][$output->name]=$output->name;
								}
							}
						}
	            		
	            	}
	            	else {

	            		//echo $year. " refused => only from ".$yrStart." to ".$yrEnd."\n";
	            	}
	            	//print_r($arrPersons);
	            	//exit();
	            }

	        } catch(\Exception $e){
	           echo "Exception:", $e->getMessage(), "\n";
	        }






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
			$person["anonymizedId"]=(isset($row->anonymizedId) ? $row->anonymizedId : "");
			$person["userId"]=(isset($row->userId) ? $row->userId : "");
			$person["personId"]=$row->personId;
			$person["huvudinventerare"]="?";
			$person["huv-inv-lokal"]="?";
			$person["medinventerare"]="?";
			$person["med-inv-lokal"]="?";
			$person["protocols"]=$protocol;

			$arrPersons[]=$person;
		}


	}

//print_r($arrPersons);

	$filename="persons_".date("Ymd-His")."_extract_".$database."_".$protocol.".csv";
	$path_extract=PATH_DOWNLOAD."extract/".$database."/".$protocol."/".$filename;

	if ($fp = fopen($path_extract, 'w')) {

		$headers=array("persnr", "fornamn", "efternamn", "sx", /*"birthDate", */"epost", "telhem", "telmobil", "address1", "address2", "postnr", "ort", "anonymizedId", "userId", "personId", "huvudinventerare", "huv-inv-lokal", "medinventerare", "med-inv-lokal", "delprogram");
		fputcsv($fp, $headers, ";");


		foreach($arrPersons as $person) {

			if (isset($person["huv-inv-lokal"]) && is_array($person["huv-inv-lokal"])) {
				$person["huv-inv-lokal"]=implode(",", $person["huv-inv-lokal"]);
			}

			if (isset($person["med-inv-lokal"]) && is_array($person["med-inv-lokal"])) {
				$person["med-inv-lokal"]=implode(",", $person["med-inv-lokal"]);
			}

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
