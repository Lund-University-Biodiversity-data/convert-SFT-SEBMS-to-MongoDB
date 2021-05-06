<?php
$database="SEBMS";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php convert_SEBMS.php punktlokal 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna)
$arr_protocol=array("punktlokal", "slinga");
// 2- limit for events

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: punktlokal / slinga");
}
else {

	$projectsId = '[ "'.$commonFields["punktlokal"]["projectId"].'", "'.$commonFields["slinga"]["projectId"].'" ]';

	$protocol=$argv[1];

	if (isset($argv[2]) && is_numeric($argv[2])) {
		$limitEvents=$argv[2];
		echo consoleMessage("info", "Number of events limited to ".$limitEvents);
	}

	if (isset($argv[3]) && $argv[3]=="debug") {
		$debug=true;
		echo consoleMessage("info", "DEBUG mode");
	}


	$array_persons=array();
	$array_sites=array();
	$array_sites_req=array();

	$array_species_guid=array();

	$arrSpeciesNotFound=array();
	$speciesNotFound=0;
	$speciesFound=0;

	/**************************** connection to mongoDB   ***/
	$mng = new MongoDB\Driver\Manager(); // Driver Object created

	if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

	$filter = ['projects' => $commonFields[$protocol]["projectId"]];
	//$filter = [];
	$options = [];
	$query = new MongoDB\Driver\Query($filter, $options); 

	//db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
	// 
	$rows = $mng->executeQuery("ecodata.site", $query);
	echo count($rows)." row(s)\n";


	foreach ($rows as $row){

//		if ($row->name=="Abborrtjärntorpet") {

		    //echo "ROW: $row->siteId - $row->name\n";
			$array_sites[$row->name]=array();

			$array_sites[$row->name]["locationID"]=$row->siteId;
			$array_sites[$row->name]["locationName"]=$row->name;
			$array_sites[$row->name]["decimalLatitude"]=$row->extent->geometry->decimalLatitude;

			$array_sites[$row->name]["decimalLongitude"]=$row->extent->geometry->decimalLongitude;
		    //print_r($row->projects);


			$array_sites_req[]="'".$row->name."'";

//		}
	}

	//unset($array_sites_req);
	//$array_sites_req[]="'Linjevägen pkt 2 1/2 vändpl'";
	$req_sites="(".implode(",", $array_sites_req).")";
	//print_r($array_sites);
	echo consoleMessage("info", "Sites list obtained. ".count($array_sites)." element(s)");

//echo $req_sites;

	/**************************** connection to mongoDB   ***/

	switch($protocol) {
		case "punktlokal":
		case "slinga":
			$qEvents="
			select
			DISTINCT 
			vi.vis_uid,  
			vi.vis_begintime AS datum,  
			vi.vis_endtime,  
			ST_Y(ST_Transform(ST_SetSRID(ST_Point(sit_geort9025gonvlon, sit_geort9025gonvlat), 3021), 4326)) AS decimalLatitude,
			ST_X(ST_Transform(ST_SetSRID(ST_Point(sit_geort9025gonvlon, sit_geort9025gonvlat), 3021), 4326)) AS decimalLongitude,
			si.sit_name,
			vp.vip_per_participantid,
			vi.vis_sunshine,
			vi.vis_temperature,
			vi.vis_windspeed,
			vi.vis_winddirection
			FROM 
			obs_observation ob, sit_site si, seg_segment se, vis_visit vi 
			LEFT JOIN vip_visitparticipant vp on vi.vis_uid=vp.vip_vis_visitid
			where ob.obs_seg_segmentid =se.seg_uid 
			and ob.obs_vis_visitid = vi.vis_uid
			and si.sit_uid = se.seg_sit_siteid 
			and vi.vis_typ_datasourceid IN (".implode(",", $commonFields["punktlokal"]["datasourceID"]).")
			and si.sit_name IN ".$req_sites."
			ORDER BY vi.vis_begintime
			";
			if (isset($limitEvents) && $limitEvents>0)
				$qEvents.=" LIMIT ".$limitEvents;

			break;
	}

	// GET the list of species

	$url="https://lists.biodiversitydata.se/ws/speciesListItems/".$commonFields["listSpeciesId"];
	$obj = json_decode(file_get_contents($url), true);

	foreach($obj as $sp) {
		//$array_species_guid[$sp["scientificName"]]=$sp["lsid"];
		$array_species_guid[$sp["name"]]=$sp["lsid"];
	}

	//print_r($array_species_guid);

	echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"]." obtained. ".count($obj)." elements");

	echo "****CONVERT ".$database." ".$protocol." to MongoDB JSON****\n";

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	if ($debug) echo consoleMessage("info", $qEvents);
	$rEvents = pg_query($db_connection, $qEvents);
	if (!$rEvents) die("QUERY:" . consoleMessage("error", pg_last_error()));


	$arr_json_activity='[';
	$arr_json_output='[';
	$arr_json_record='[';
	$arr_json_person='[';
	$nbLines=0;
	while ($rtEvents = pg_fetch_array($rEvents)) {

		$siteInfo["locationID"]=$array_sites[$rtEvents["sit_name"]]["locationID"];
		$siteInfo["locationName"]=$array_sites[$rtEvents["sit_name"]]["locationName"]; 
		$siteInfo["decimalLatitude"]=$array_sites[$rtEvents["sit_name"]]["decimalLatitude"]; // create an array of sites 
		$siteInfo["decimalLongitude"]=$array_sites[$rtEvents["sit_name"]]["decimalLongitude"]; 

		switch($protocol) {
			case "punktlokal":
				$qRecords="
					select 
					ob.obs_count,
					ss.spe_dyntaxa,
					ss.spe_originalnameusage AS scientificname_alt,
					ss.spe_enmainname,
					CONCAT(ss.spe_genusname, ' ', ss.spe_speciesname) AS scientificname,
					ss.spe_semainname AS names,
					ob.obs_seg_segmentid,
					vi.vis_uid ,
					se.seg_sequence
					from 
					obs_observation ob, sit_site si, seg_segment se, spe_species ss, vis_visit vi  
					where ob.obs_seg_segmentid =se.seg_uid 
					and ob.obs_vis_visitid = vi.vis_uid
					and si.sit_uid = se.seg_sit_siteid 
					and ss.spe_uid = ob.obs_spe_speciesid 
					and vi.vis_typ_datasourceid IN (".implode(",", $commonFields["punktlokal"]["datasourceID"]).")
					and si.sit_name='".$rtEvents["sit_name"]."'
					and vi.vis_uid = ".$rtEvents["vis_uid"]."
					ORDER BY vi.vis_begintime
				";
				break;
			case "slinga":
				$qRecords="
					select 
					SUM(ob.obs_count) AS tot_count,
					ss.spe_dyntaxa,
					ss.spe_originalnameusage AS scientificname_alt,
					ss.spe_enmainname,
					CONCAT(ss.spe_genusname, ' ', ss.spe_speciesname) AS scientificname,
					ss.spe_semainname AS names
					from 
					obs_observation ob, sit_site si, seg_segment se, spe_species ss, vis_visit vi  
					where ob.obs_seg_segmentid =se.seg_uid 
					and ob.obs_vis_visitid = vi.vis_uid
					and si.sit_uid = se.seg_sit_siteid 
					and ss.spe_uid = ob.obs_spe_speciesid 
					and vi.vis_typ_datasourceid IN (".implode(",", $commonFields["punktlokal"]["datasourceID"]).")
					and si.sit_name='".$rtEvents["sit_name"]."'
					and vi.vis_uid = ".$rtEvents["vis_uid"]."
					GROUP BY ss.spe_dyntaxa, spe_originalnameusage, ss.spe_enmainname, scientificname, ss.spe_semainname

				";

				$nbPts=1;

				break;
		}

		
		//if ($debug && $rtEvents["vis_uid"]==620) echo $qRecords;
		$rRecords = pg_query($db_connection, $qRecords);
		if (!$rRecords) die("QUERY:" . consoleMessage("error", pg_last_error()));

		$nbLines++;
		if ($nbLines%100==0) echo number_format($nbLines, 0, ".", " ")." ĺines\n";

		$activityId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");
		$eventID=$activityId;
		$outputId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

		//echo "outputId: $outputId\n";

		// date now
		$micro_date = microtime();
		$date_array = explode(" ",$micro_date);
		$micsec=number_format($date_array[0]*1000, 0, ".", "");
		$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
		if ($micsec==1000) $micsec=999;
		$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";
		//echo "Date: $date_now_tz\n";

		// survey date
		$date_survey=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
		//echo "date survey".$date_survey."\n";

		$start_time=date("H:i", strtotime($rtEvents["datum"]));
		$finish_time=date("H:i", strtotime($rtEvents["vis_endtime"]));

		$eventDate=$date_survey;
		$eventTime=date("H:i", strtotime($rtEvents["vis_endtime"]));

		// check observers

		$qPerson='
					select per_uid, per_firstname, per_lastname, per_emailaddress, per_addressposttown, per_gender, per_phonehome, per_phonemobile, per_addressname, per_addressstreet, per_addresspostcode, per_birthyear
					from per_person pp, vip_visitparticipant vv 
					where vv.vip_per_participantid =pp.per_uid 
					and  vv.vip_vis_visitid  = '.$rtEvents["vis_uid"];

		$rPerson = pg_query($db_connection, $qPerson);

		$nbPart=0;
		$arrHelper=array();
		while ($rtPerson = pg_fetch_array($rPerson)) {

			if ($nbPart==0) {
				$recorder_name=$rtPerson["per_firstname"].' '.$rtPerson["per_lastname"];
			}
			else {
				$arrHelper[]=$rtPerson["per_firstname"].' '.$rtPerson["per_lastname"];
			}

			if (!isset($array_persons[$rtEvents["vip_per_participantid"]])) {

				$array_persons[$rtEvents["vip_per_participantid"]]=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

				$arr_json_person.='{
					"dateCreated" : ISODate("'.$date_now_tz.'"),
					"lastUpdated" : ISODate("'.$date_now_tz.'"),
					"personId" : "'.$array_persons[$rtEvents["vip_per_participantid"]].'",
					"firstName" : "'.$rtPerson["per_firstname"].'",
					"lastName" : "'.$rtPerson["per_lastname"].'",
					"gender" : "'.$rtPerson["per_gender"].'",
					"email" : "'.$rtPerson["per_emailaddress"].'",
					"birthDate" : "'.$rtPerson["per_birthyear"].'",
					"phoneNum" : "'.$rtPerson["per_phonehome"].'",
					"mobileNum" : "'.$rtPerson["per_phonemobile"].'",
					"address1" : "'.$rtPerson["per_addressname"].'",
					"address2" : "'.$rtPerson["per_addressstreet"].'",
					"postCode" : "'.$rtPerson["per_addresspostcode"].'",
					"town" : "'.$rtPerson["per_addressposttown"].'",
					"projects": '.$projectsId.',
					"internalPersonId" : "'.$rtEvents["vip_per_participantid"].'"
				},';
			}

			$nbPart++;
		}

		/*"
		helpers" : [
			{
				"helper" : "jean pierre"
			}
		]
		*/
		$helpers= "[
			";
		foreach ($arrHelper as $help) {
			$helpers.= '{"helper" : "'.$help.'"},';

		}
		$helpers[strlen($helpers)-1]=' ';

		$helpers.= "]";

		$data_field="";
		while ($rtRecords = pg_fetch_array($rRecords)) {
			
			$data_field.='{';

			$IC=0;

			switch($protocol) {
				case "punktlokal":
					$IC= $rtRecords["obs_count"];
					break;
				case "slinga":

					$IC= $rtRecords["tot_count"];
					$qDetRec="
						select 
						ob.obs_count,
						se.seg_sequence
						from 
						obs_observation ob, sit_site si, seg_segment se, spe_species ss, vis_visit vi  
						where ob.obs_seg_segmentid =se.seg_uid 
						and ob.obs_vis_visitid = vi.vis_uid
						and si.sit_uid = se.seg_sit_siteid 
						and ss.spe_uid = ob.obs_spe_speciesid 
						and vi.vis_typ_datasourceid IN (".implode(",", $commonFields["punktlokal"]["datasourceID"]).")
						and si.sit_name='".$rtEvents["sit_name"]."'
						and vi.vis_uid = ".$rtEvents["vis_uid"]."
						and ss.spe_semainname ='".$rtRecords["names"]."'
						ORDER BY se.seg_sequence
					";
					$rDetRec = pg_query($db_connection, $qDetRec);

					while ($rowDetRec = pg_fetch_array($rDetRec)) {
						$data_field.='"S'.$rowDetRec["seg_sequence"].'": "'.$rowDetRec["obs_count"].'",
						';
					}

					break;

			}

			$explSN=explode(",", trim($rtRecords["scientificname"]));
			if (isset($explSN[0]) && $explSN[0]!="")
			$scientifName=$explSN[0];
			else $scientifName=trim($rtRecords["scientificname_alt"]);

			if (isset($array_species_guid[$scientifName]) && $array_species_guid[$scientifName]!="-1") {
				$listId=$commonFields["listSpeciesId"];
				$speciesFound++;
				$guid=$array_species_guid[$scientifName];
			}
			else {
				if ($debug) echo consoleMessage("error", "No species guid for -".$scientifName."- (".$rtEvents["sit_name"]."/".$rtEvents["datum"].")");
				$speciesNotFound++;
				$listId="error-unmatched";
				$guid="";
				$array_species_guid[$scientifName]="-1";

				if (isset($arrSpeciesNotFound[$rtRecords["scientificname"]]) && $arrSpeciesNotFound[$rtRecords["scientificname"]]>0)
					$arrSpeciesNotFound[$rtRecords["scientificname"]]++;
				else $arrSpeciesNotFound[$rtRecords["scientificname"]]=1;

			}

			$outputSpeciesId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

			$data_field.='"species" : {
							"listId" : "'.$listId.'",
							"commonName" : "'.$rtRecords["spe_enmainname"].'",
							"outputSpeciesId" : "'.$outputSpeciesId.'",
							"scientificName" : "'.$scientifName.'",
							"name" : "'.$rtRecords["names"].'",
							"guid" : "'.$guid.'"
						},
						';
			$data_field.='"individualCount" : '.$IC.'
							},';

			$occurenceID=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");


			$arr_json_record.='{
				"dateCreated" : ISODate("'.$date_now_tz.'"),
				"lastUpdated" : ISODate("'.$date_now_tz.'"),
				"occurrenceID" : "'.$occurenceID.'",
				"status" : "active",
				"recordedBy" : "'.$recorder_name.'",
				"rightsHolder" : "'.$commonFields["rightsHolder"].'",
				"institutionID" : "'.$commonFields["institutionID"].'",
				"institutionCode" : "'.$commonFields["institutionCode"].'",
				"datasetID" : "'.$commonFields[$protocol]["projectActivityId"].'",
				"datasetName" : "'.$commonFields[$protocol]["datasetName"].'",
				"licence" : "'.$commonFields["licence"].'",
				"basisOfRecord" : "'.$commonFields["basisOfRecord"].'",
				"locationID" : "'.$siteInfo["locationID"].'",
				"locationName" : "'.$siteInfo["locationName"].'",
				"eventID" : "'.$eventID.'",
				"eventTime" : "'.$eventTime.'",
				"guid" : "'.$guid.'",
				"eventRemarks" : "",
				"name" : "'.$rtRecords["names"].'",
				"scientificName" : "'.$scientifName.'",
				"multimedia" : [ ],
				"activityId" : "'.$activityId.'",
				"decimalLatitude" : '.$siteInfo["decimalLatitude"].',
				"decimalLongitude" : '.$siteInfo["decimalLongitude"].',
				"eventDate" : "'.$eventDate.'",
				"individualCount" : '.$IC.',
				"outputId" : "'.$outputId.'",
				"outputSpeciesId" : "'.$outputSpeciesId.'",
				"projectActivityId" : "'.$commonFields[$protocol]["projectActivityId"].'",
				"projectId" : "'.$commonFields[$protocol]["projectId"].'",
				"userId" : "'.$commonFields["userId"].'"
			},';


		}
		// replace last comma by 
		$data_field[strlen($data_field)-1]=' ';
		//echo "data_field: ".$data_field."\n";



		$arr_json_activity.='{
			"activityId" : "'.$activityId.'",
			"assessment" : false,
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"progress" : "planned",
			"projectActivityId" : "'.$commonFields[$protocol]["projectActivityId"].'",
			"projectId" : "'.$commonFields[$protocol]["projectId"].'",
			"projectStage" : "",
			"siteId" : "'.$siteInfo["locationID"].'",
			"status" : "active",
			"type" : "'.$commonFields[$protocol]["type"].'",
			"userId" : "'.$commonFields["userId"].'",
			"personId" : "'.$array_persons[$rtEvents["vip_per_participantid"]].'",
			"verificationStatus" : "not applicable",
			"mainTheme" : ""
		},';
		//			"helper1" : "Jean Michel Helper",
		//			"helper2" : "Raoul HElper",
		$arr_json_output.='{
			"activityId" : "'.$activityId.'",
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"outputId" : "'.$outputId.'",
			"status" : "active",
			"outputNotCompleted" : false,
			"data" : {
				"surveyEndTime" : "'.$finish_time.'",
				"locationAccuracy" : 50,
				"helpers" : '.$helpers.',
				"temperatureInDegreesCelcius" : "'.$rtEvents["vis_temperature"].'",
				"percentageOfSunlight" : "'.$rtEvents["vis_sunshine"].'",
				"windSpeedKmPerHourCategorical" : "'.$rtEvents["vis_windspeed"].'",
				"windDirectionCategorical" : "'.$rtEvents["vis_winddirection"].'",
				"surveyDate" : "'.$date_survey.'",
				"locationHiddenLatitude" : '.$siteInfo["decimalLatitude"].',
				"locationLatitude" : '.$siteInfo["decimalLatitude"].',
				"recordedBy" : "'.$recorder_name.'",
				"surveyStartTime" : "'.$start_time.'",
				"locationCentroidLongitude" : null,
				"observations" : [
					'.$data_field.'
				],
				"location" : "'.$siteInfo["locationID"].'",
				"locationLongitude" : '.$siteInfo["decimalLongitude"].',
				"locationHiddenLongitude" : '.$siteInfo["decimalLongitude"].',
				"locationCentroidLatitude" : null,
				"transectName" : "'.$siteInfo["locationName"].'"
			},
			"selectFromSitesOnly" : true,
			"_callbacks" : {
				"sitechanged" : [
					null
				]
			},
			"mapElementId" : "locationMap",
			"projectId" : "'.$commonFields[$protocol]["projectId"].'",
			"name" : "'.$commonFields[$protocol]["name"].'",
			"dataOrigin" : "'.$dataOrigin.'"

		},';

/* 
,
			"personId" : "'.$array_persons[$rtEvents["vip_per_participantid"]].'"
			*/

	};

	// replace last comma by 
	$arr_json_output[strlen($arr_json_output)-1]=' ';
	$arr_json_output.=']';
	$arr_json_activity[strlen($arr_json_activity)-1]=' ';
	$arr_json_activity.=']';
	$arr_json_record[strlen($arr_json_record)-1]=' ';
	$arr_json_record.=']';
	$arr_json_person[strlen($arr_json_person)-1]=' ';
	$arr_json_person.=']';

	//echo $arr_json_output;
	//echo "\n";

	for ($i=1;$i<=4;$i++) {
		switch ($i) {
			case 1:
				$typeO="activity";
				$json=$arr_json_activity;
				break;
			case 2:
				$typeO="output";
				$json=$arr_json_output;
				break;
			case 3:
				$typeO="record";
				$json=$arr_json_record;
				break;
			case 4:
				$typeO="person";
				$json=$arr_json_person;
				break;
		}
		$filename_json='postgres_json_SEBMS_'.$protocol.'_'.$typeO.'s_'.date("Y-m-d-His").'.json';
		$path='dump_json_sft_sebms/'.$database."/".$protocol."/".$filename_json;
//		echo 'db.'.$typeO.'.remove({"dateCreated" : {$gte: new ISODate("'.date("Y-m-d").'T01:15:31Z")}})'."\n";
		echo 'mongoimport --db ecodata --collection '.$typeO.' --jsonArray --file '.$path."\n";
		//$json = json_encode($arr_rt, JSON_UNESCAPED_SLASHES); 
		if ($fp = fopen($path, 'w')) {
			fwrite($fp, $json);
			fclose($fp);
		}
		else echo consoleMessage("error", "can't create file ".$path);

		//echo 'PATH: '.$path."\n\n";

	}

	$ratioSpecies = $speciesFound / ($speciesFound+$speciesNotFound);

	echo consoleMessage("info", "Species ratio found in the species lists : ".$speciesFound." / ".($speciesFound+$speciesNotFound)." = ".number_format($ratioSpecies*100, 2)."%");

	if ($ratioSpecies!=1) {
		var_dump($arrSpeciesNotFound);
	}

	echo "scp dump_json_sft_sebms/".$database."/".$protocol."/postgres_json_* ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/dump_json_sft_sebms/".$database."/".$protocol."/\n";
	/*
	send files
	scp json/std/json_* radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/json/std/

	mongoimport --db ecodata --collection activity --jsonArray --file json/std/json_std_activitys_SFT_2020-04-15-155129.json
	mongoimport --db ecodata --collection output --jsonArray --file json/std/json_std_outputs_SFT_2020-04-15-155129.json
	mongoimport --db ecodata --collection record --jsonArray --file json/std/json_std_records_SFT_2020-04-15-155129.json
	
	db.activity.remove({"activityId":"12c2ccf4-0851-8f12-a5da-c68a2638d47b"})
	db.output.remove({"outputId":"08da4473-eabd-d61d-5bc2-0154437c4364"})
	db.record.remove({"outputId":"08da4473-eabd-d61d-5bc2-0154437c4364"})

	mongoimport --db ecodata --collection activity --jsonArray --file json/std/activity.json
	mongoimport --db ecodata --collection output --jsonArray --file json/std/output.json
	mongoimport --db ecodata --collection record --jsonArray --file json/std/record.json


	clean mongo
	db.activity.find({"userId":"5"}).count();
	db.activity.remove({"userId":"5"})

	db.record.find({"userId":"5"}).count();
	db.record.remove({"userId":"5"})

punktlokal
db.activity.remove({projectId:"1fb10915-e6c0-451e-b575-b7e715d5d32f"})
db.record.remove({projectId:"1fb10915-e6c0-451e-b575-b7e715d5d32f"})
db.output.remove({"dateCreated":{$gt:new ISODate("2020-11-05T16:37:15.184Z"), $lt:new ISODate("2020-11-05T16:38:38.184Z")}})
db.person.remove({"dateCreated":{$gt:new ISODate("2020-11-05T16:37:15.184Z"), $lt:new ISODate("2020-11-05T16:38:38.184Z")}})

slinga
db.activity.remove({projectId:"30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"})
db.record.remove({projectId:"30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"})
db.output.remove({"dateCreated":{$gt:new ISODate("2020-11-06T13:37:15.184Z"), $lt:new ISODate("2020-11-06T13:42:38.184Z")}})
db.person.remove({"dateCreated":{$gt:new ISODate("2020-11-06T13:37:15.184Z"), $lt:new ISODate("2020-11-06T13:42:38.184Z")}})


	mongodump --db ecodata --collection site --out site_dump/
	mongorestore --db database_name path_to_bson_file
	*/


	echo consoleMessage("info", "Script ends");


}


?>