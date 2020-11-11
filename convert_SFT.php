<?php
$database="SFT";
require "lib/config.php";
require "lib/functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php convert_SFT.php std 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar");
}
else {

	$protocol=$argv[1];

	$speciesNotFound=0;
	$speciesFound=0;
	$observationFieldName="observations";

	if (isset($argv[3]) && $argv[3]=="debug") {
		$debug=true;
		echo consoleMessage("info", "DEBUG mode");
	}
	$array_persons=array();
	$array_sites=array();
	$array_sites_req=array();

	$array_species_guid=array();

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
	//echo count($rows)." row(s)\n";


	foreach ($rows as $row){
	    
	    if ($protocol=="natt") {
	    	if (isset($row->KartaTx))
	    		$indexSite=$row->KartaTx;
	    	else {
	    		echo consoleMessage("info", "No KartaTx for site ".$row->name);
	    		$indexSite=$row->name;
	    	} 
	    }
	    else $indexSite=$row->name;

	    //echo "ROW: $row->siteId - $row->name\n";
		$array_sites[$indexSite]=array();

		$array_sites[$indexSite]["locationID"]=$row->siteId;
		$array_sites[$indexSite]["locationName"]=$indexSite;
		$array_sites[$indexSite]["decimalLatitude"]=$row->extent->geometry->decimalLatitude;
		$array_sites[$indexSite]["decimalLongitude"]=$row->extent->geometry->decimalLongitude;
//	    print_r($row->projects);


		$array_sites_req[]="'".$indexSite."'";
	}
	$req_sites="(".implode(",", $array_sites_req).")";
	if ($debug) print_r($array_sites);

	/**************************** connection to mongoDB   ***/


	//$commonFields["occurenceID"]="????";

	//$commonFields["eventTime"]=array("datetime", true, "08:42 AM");
	//$commonFields["verbatimCoordinates"]="";
	//$commonFields["activityId"]=?????;
	//$commonFields["eventID"]=$commonFields["activityId;


	//$commonFields["projectActivityId"]=array("string", true, "17ee4c9f-abe7-4926-b2d7-244f008ceaeb");

	switch($protocol) {
		case "std":
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, TS.datum , p1, p2, p3, p4, p5, p6, p7, p8, l1, l2, l3, l4, l5, l6, l7, l8, TS.karta AS sitename, SI.gps, SI.txt
			from totalstandard TS
			left join personer P on P.persnr = TS.persnr 
			left join standardrutter_inventerarinfo SI on (SI.karta =TS.karta and TS.yr = SI.yr)
			where TS.karta IN ".$req_sites."
			AND TS.art='000'
			order by datum
			";
			//$qEvents.=" LIMIT 10";
//where TS.karta='07D7C'  

			break;
		case "vinter":
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, T.datum , p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, Pu.ruttnamn AS sitename
			from punktrutter Pu, totalvinter_pkt T
			left join personer P on P.persnr = T.persnr 
			where Pu.ruttnamn IN ".$req_sites."
			and Pu.persnr = T.persnr 
			and Pu.rnr=T.rnr
			AND T.art='000'
			order by datum

			";
			//$qEvents.=" LIMIT 10";
//where TS.karta='07D7C'  

			break;
		case "natt":
			// LPAD(cast(LEAST(p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20) as text), 4, '0')
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, TN.datum , p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.kartatx AS sitename, per
			from totalnatt TN
			left join personer P on P.persnr = TN.persnr 
			where TN.kartatx='02DSV'  
			AND TN.art='000'
			order by datum
			";


			$siteInfo["locationID"]="eccb9fcb-a12e-4fa6-95a3-ef7d329646e3"; /* create an array of sites */
			$siteInfo["locationName"]="Night route test 02DSV"; /* create an array of sites */
			$siteInfo["decimalLatitude"]=55.56793; /* create an array of sites */
			$siteInfo["decimalLongitude"]=13.61763; /* create an array of sites */


			break;
	}


	// GET the list of species
	foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
		$url="https://lists.bioatlas.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals];
		$obj = json_decode(file_get_contents($url), true);

		foreach($obj as $sp) {
			//$array_species_guid[$sp["scientificName"]]=$sp["lsid"];
			$array_species_guid[$animals][$sp["name"]]=$sp["lsid"];
		}

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

		$siteInfo["locationID"]=$array_sites[$rtEvents["sitename"]]["locationID"];
		$siteInfo["locationName"]=$array_sites[$rtEvents["sitename"]]["locationName"]; 
		$siteInfo["decimalLatitude"]=$array_sites[$rtEvents["sitename"]]["decimalLatitude"]; // create an array of sites 
		$siteInfo["decimalLongitude"]=$array_sites[$rtEvents["sitename"]]["decimalLongitude"]; 

		switch($protocol) {
			case "std":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p1, p2, p3, p4, p5, p6, p7, p8, l1, l2, l3, l4, l5, l6, l7, l8, TS.art, TS.datum, pkind, lind
					from totalstandard TS, eurolist EL
					where EL.art=TS.art 
					and TS.karta='".$rtEvents["sitename"]."'  
					AND EL.art<>'000'
					AND TS.datum='".$rtEvents["datum"]."'
					order by datum
				";
				//$qRecords.=" LIMIT 20";
				$nbPts=8;
				break;
			case "vinter":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.art, TN.datum
					from punktrutter Pu, totalvinter_pkt TN, eurolist EL
					where EL.art=TN.art 
					AND TN.persnr=PU.persnr
					AND TN.rnr=PU.rnr
					and Pu.ruttnamn='".$rtEvents["sitename"]."'  
					AND EL.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=20;
				break;
			case "natt":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.art, TN.datum, TN.pt, TN.kull
					from totalnatt TN, eurolist EL
					where EL.art=TN.art 
					and TN.kartatx='".$rtEvents["sitename"]."'  
					AND EL.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=20;
				break;
		}

		
		//echo $qRecords;
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

		// find the start time and finsh time
		$start_time=2359;
		$finish_time=0;
		$arr_time=array();

		$minutesSpentObserving='"minutesSpentObserving" : [
				{';
		$timeOfObservation='"timeOfObservation" : [
				{';

		for ($i=1; $i<=$nbPts; $i++) {
			switch($protocol) {
				case "std":
					$ind="p".$i;
					
					$timeOfObservation.='
					"TidP'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$rtEvents["p".$i].'",';
					$minutesSpentObserving.='
					"TidL'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$rtEvents["l".$i].'",';

					break;
				case "vinter":
					$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
					break;
				case "natt":

					$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);

					$timeOfObservation.='
					"TidP'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$rtEvents[$ind].'",';

					break;
			}



			$rtEvents[$ind]=intval($rtEvents[$ind]);

			if ($rtEvents[$ind]<$start_time)
				$start_time=$rtEvents[$ind];

			// add 24 hours to the night tmes, to help comparing
			if ($rtEvents[$ind]<1200)
				$rtEvents[$ind]+=2400;



			if ($rtEvents[$ind]>$finish_time)
				$finish_time=$rtEvents[$ind];

			if ($rtEvents[$ind]>2400) $rtEvents[$ind]-=2400;

			$arr_time[$ind]=convertTime($rtEvents[$ind]);
		}

		if ($start_time>2400) $start_time-=2400;
		if ($finish_time>2400) $finish_time-=2400;

		$start_time_brut=$start_time;

		$start_time=convertTime($start_time, "24H");
		$finish_time=convertTime($finish_time, "24H");

		//$eventTime=date("H:i", strtotime($rtEvents["datum"]));

		$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".substr($start_time_brut, 0, 2).":".substr($start_time_brut, 2, 4).":00Z";
		//echo "eventDate: $eventDate\n";

		$minutesSpentObserving[strlen($minutesSpentObserving)-1]=' ';
		$minutesSpentObserving.='}],';
		$timeOfObservation[strlen($timeOfObservation)-1]=' ';
		$timeOfObservation.='}],';

		$eventRemarks="";

		switch($protocol) {
			case "std":

			$qEvents999="
			select *
			from totalstandard TS
			where TS.karta = '".$rtEvents["sitename"]."'
			AND TS.art='999'
			AND datum='".$rtEvents["datum"]."'
			";
			$rEvents999 = pg_query($db_connection, $qEvents999);
			if (!$rEvents999) die("QUERY:" . consoleMessage("error", pg_last_error()));
			$rtEvents999 = pg_fetch_array($rEvents999);
			if (pg_num_rows($rEvents999)!=1) die("QUERY:" . consoleMessage("error", pg_num_rows($rEvents999)." row of Event999 for karta ".$rtEvents["sitename"]));
			
			$distanceCovered='"distanceCovered" : [
					{
						';
			for ($i=1; $i<=$nbPts; $i++) {
				$distanceCovered.='
				"distanceOnL'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$rtEvents999["l".$i].'",';
			}

			$distanceCovered[strlen($distanceCovered)-1]=' ';
			$distanceCovered.='}],';

			if ($rtEvents["gps"]=="X") $isGpsUsed="ja";
			else $isGpsUsed="nej";

			$eventRemarks=str_replace('"', "|", $rtEvents["txt"]);

			break;

			case "natt":

			$qEvents998="
			select *
			from totalnatt TN
			where TN.kartatx = '".$rtEvents["sitename"]."'
			AND TN.art='998'
			AND datum='".$rtEvents["datum"]."'
			";
			$rEvents998 = pg_query($db_connection, $qEvents998);
			if (!$rEvents998) die("QUERY:" . consoleMessage("error", pg_last_error()));
			$rtEvents998 = pg_fetch_array($rEvents998);
			if (pg_num_rows($rEvents998)!=1) die("QUERY:" . consoleMessage("error", pg_num_rows($rEvents998)." row of Event998 for kartatx ".$rtEvents["sitename"]));
			
			$cloudStart='"cloudsStart" : "'. $rtEvents998["p01"].'"';
			$tempStart='"temperatureStart" : "'. $rtEvents998["p02"].'"';
			$windStart='"windStart" : "'. $rtEvents998["p03"].'"';
			$precipStart='"precipitationStart" : "'. $rtEvents998["p04"].'"';

			$per=$rtEvents["per"];

			$cloudEnd='"cloudsEnd" : "'. $rtEvents998["p05"].'"';
			$tempEnd='"temperatureEnd" : "'. $rtEvents998["p06"].'"';
			$windEnd='"windEnd" : "'. $rtEvents998["p07"].'"';
			$precipEnd='"precipitationEnd" : "'. $rtEvents998["p08"].'"';


			$specific_natt=$cloudStart.',
				'.$tempStart.',
				'.$windStart.',
				'.$precipStart.',
				'.$cloudEnd.',
				'.$tempEnd.',
				'.$windEnd.',
				'.$precipEnd.',
				"period" : "'.$per.'",';

			break;

		}

		// check observers
		$recorder_name=$rtEvents["fornamn"].' '.$rtEvents["efternamn"];

		if (!isset($array_persons[$rtEvents["persnr"]])) {

			$array_persons[$rtEvents["persnr"]]=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

			$qPerson= "
					select *
					from personer 
					where persnr='".$rtEvents["persnr"]."'  
				";
			$rPerson = pg_query($db_connection, $qPerson);
			$rtPerson = pg_fetch_array($rPerson);

			$arr_json_person.='{
				"dateCreated" : ISODate("'.$date_now_tz.'"),
				"lastUpdated" : ISODate("'.$date_now_tz.'"),
				"personId" : "'.$array_persons[$rtEvents["persnr"]].'",
				"firstName" : "'.$rtPerson["fornamn"].'",
				"lastName" : "'.$rtPerson["efternamn"].'",
				"gender" : "'.$rtPerson["sx"].'",
				"email" : "'.$rtPerson["epost"].'",
				"town" : "'.$rtPerson["ort"].'",
				"projectId": "'.$commonFields[$protocol]["projectId"].'"
			},';
		}

		foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
			$data_field[$animals]="";
		}
		$data_field["mammalsOnRoad"]="";

		while ($rtRecords = pg_fetch_array($rRecords)) {
			
			switch($protocol) {
				case "natt":
				if ($rtRecords["art"]>="128" && $rtRecords["art"]<="138" && $rtRecords["kull"]=="k") {
					$animals="owls";
					$animalsDataField=$animals;
					$speciesFieldName="speciesYoungOwl";
				}
				elseif ($rtRecords["art"]>="001" && $rtRecords["art"]<="699") {
					$animals="birds";
					$animalsDataField=$animals;
					$speciesFieldName="species";
				}
				elseif ($rtRecords["art"]>="700" && $rtRecords["art"]<="799") {
					$animals="mammals";
					if ($rtRecords["pt"]=="T") {
						$animalsDataField="mammalsOnRoad";
						$speciesFieldName="speciesMammalsOnRoad";
					}
					else {
						$animalsDataField="mammals";
						$speciesFieldName="speciesMammals";
					}

				}
				elseif ($rtRecords["art"]>="800" && $rtRecords["art"]<="899") {
					$animals="amphibians";
					$animalsDataField=$animals;
					$speciesFieldName="speciesAmphibians";
				}
				break;

				case "std":
					if ($rtRecords["art"]>="700" && $rtRecords["art"]<="799") {
						$animals="mammals";
						$speciesFieldName="speciesMammals";
					}
					else {
						$animals="birds";
						$speciesFieldName="species";
					}
					$animalsDataField=$animals;

				break;
			}


			$data_field[$animalsDataField].='{';

			$IC=0;

			switch($protocol) {
				case "std":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".$i;
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",
						';
					}
					$data_field[$animalsDataField].='"pointCount" : '.$rtRecords["pkind"].',
					';

					for ($i=1; $i<=$nbPts; $i++) {
						$ind="l".$i;
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"L'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",
						';
					}
					$data_field[$animalsDataField].='"lineCount" : '.$rtRecords["lind"].',
					';

					break;
				case "vinter":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}
					break;
				case "natt":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}

					break;
			}

			
			

			if (isset($array_species_guid[$animals][$rtRecords["scientificname"]]) && $array_species_guid[$animals][$rtRecords["scientificname"]]!="-1") {
				$speciesFound++;
				$listId=$commonFields["listSpeciesId"][$animals];
				$guid=$array_species_guid[$animals][$rtRecords["scientificname"]];
			}
			else {
				if ($debug) echo consoleMessage("error", "No species guid for -".$rtRecords["scientificname"]."- art#".$rtRecords["art"]." (".$rtEvents["sitename"]."/".$rtEvents["datum"].") in list of ".$animals);
				$speciesNotFound++;
				$listId="error-unmatched";
				$guid="";
				$array_species_guid[$animals][$rtRecords["scientificname"]]="-1";
			}
			$outputSpeciesId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

			$data_field[$animalsDataField].='
						"'.$speciesFieldName.'" : {
							"listId" : "'.$listId.'",
							"commonName" : "",
							"outputSpeciesId" : "'.$outputSpeciesId.'",
							"scientificName" : "'.$rtRecords["scientificname"].'",
							"name" : "'.$rtRecords["names"].'",
							"guid" : "'.$guid.'"
						},
						';
			$data_field[$animalsDataField].='"individualCount" : '.$IC.'
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
				"basisOfRecord" : "'.$commonFields["basisOfRecord"].'",
				"datasetID" : "'.$commonFields[$protocol]["projectActivityId"].'",
				"datasetName" : "'.$commonFields[$protocol]["datasetName"].'",
				"licence" : "'.$commonFields["licence"].'",
				"locationID" : "'.$siteInfo["locationID"].'",
				"locationName" : "'.$siteInfo["locationName"].'",
				"locationRemarks" : "",
				"eventID" : "'.$eventID.'",
				"eventTime" : "'.$start_time.'",
				"eventRemarks" : "",
				"notes" : "",
				"guid" : "'.$guid.'",
				"name" : "'.$rtRecords["names"].'",
				"scientificName" : "'.$rtRecords["scientificname"].'",
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
		foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
			if (strlen($data_field[$animals])>0)
				$data_field[$animals][strlen($data_field[$animals])-1]=' ';
		}
		if (strlen($data_field["mammalsOnRoad"])>0) $data_field["mammalsOnRoad"][strlen($data_field["mammalsOnRoad"])-1]=' ';
		//echo "data_field: ".$data_field[$animals]."\n";



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
			"mainTheme" : ""
		},';
		/*"
		helpers" : [
			{
				"helper" : "jean pierre"
			}
		]
		*/

		//			"transectName" : "'.$siteInfo["locationName"].'"

		$specific_fields=$timeOfObservation;

		switch ($protocol) {

			case "std":
				$specific_fields.='
				'.$distanceCovered.'
				'.$minutesSpentObserving.'
				"isGpsUsed" : "'.$isGpsUsed.'",
				'
				;


			break;

			case "natt":
			$specific_fields.=$specific_natt.
				'
				"mammalsCounted" : "ja",
				"mammalObservationsOnRoad" : [
					'.$data_field["mammalsOnRoad"].'
				],
				"youngOwlObservations" : [
					'.$data_field["owls"].'
				],
				"amphibiansCounted" : "ja",
				"amphibianObservations" : [
					'.$data_field["amphibians"].'
				],';

			break;
		}

		$arr_json_output.='{
			"activityId" : "'.$activityId.'",
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"outputId" : "'.$outputId.'",
			"status" : "active",
			"outputNotCompleted" : false,
			"data" : {
				"eventRemarks" : "'.$eventRemarks.'",
				"surveyFinishTime" : "'.$finish_time.'",
				"locationAccuracy" : 50,
				"surveyDate" : "'.$date_survey.'",
				'.$specific_fields.'
				"locationHiddenLatitude" : '.$siteInfo["decimalLatitude"].',
				"locationLatitude" : '.$siteInfo["decimalLatitude"].',
				"locationSource" : "Google maps",
				"recordedBy" : "'.$recorder_name.'",
				"helpers" : [
					{}
				],
				"surveyStartTime" : "'.$start_time.'",
				"locationCentroidLongitude" : null,
				"observations" : [
					'.$data_field["birds"].'
				],
				"mammalObservations" : [
					'.$data_field["mammals"].'
				],
				"location" : "'.$siteInfo["locationID"].'",
				"locationLongitude" : '.$siteInfo["decimalLongitude"].',
				"locationHiddenLongitude" : '.$siteInfo["decimalLongitude"].',
				"locationCentroidLatitude" : null
			},
			"selectFromSitesOnly" : true,
			"_callbacks" : {
				"sitechanged" : [
					null
				]
			},
			"mapElementId" : "locationMap",
			"checkMapInfo" : {
				"validation" : true
			},
			"name" : "'.$commonFields[$protocol]["name"].'",
			"personId" : "'.$array_persons[$rtEvents["persnr"]].'"
		},';



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
		$filename_json='json_'.$database.'_'.$protocol.'_'.$typeO.'s_'.date("Y-m-d-His").'.json';
		$path='dump_json_sft_sebms/'.$database.'/'.$protocol."/".$filename_json;
		//echo 'db.'.$typeO.'.remove({"dateCreated" : {$gte: new ISODate("'.date("Y-m-d").'T01:15:31Z")}})'."\n";
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
	
	echo consoleMessage("info", "Species ratio found in the species lists : ".number_format($ratioSpecies*100, 2)."%");


	echo "scp dump_json_sft_sebms/".$database."/".$protocol."/json_* ubuntu@89.45.233.195:/home/ubuntu/dump_json_sft_sebms/".$database."/".$protocol."/\n";
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

	db.activity.find({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}}).count()
	db.record.find({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}}).count()
	db.output.find({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}}).count()
	
	db.activity.remove({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}})
	db.record.remove({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}})
	db.output.remove({"dateCreated":{"$gt": new ISODate("2020-04-16T15:16:15.184Z")}})

	db.output.find({"dateCreated":{"$gt": new ISODate("2020-04-15T15:16:15.184Z")}}).count()


	db.output.remove({"dateCreated":{"$gt": new ISODate("2020-04-03T08:16:15.184Z")}})
	*/








	/*
	/*


		foreach ($structure as $key => $data) {

			//mandatory fields OR if not mandatory => non-empty 
			if ($data[1] || (!$data[1] && trim($rtEvents[$key])!="")) {
				$line_json.='"'.$key.'"'.': ';
				if ($data[0]!="number") $line_json.='"';
				if ($data[0]=="number" && $rtEvents[$key]=="") $line_json.=0;
				elseif ($data[0]=="date" && $rtEvents[$key]!="") {
					$line_json.=date("Y-m-d H:i:s", strtotime($rtEvents[$key]));
				}
				else $line_json.=$rtEvents[$key];

				if ($data[0]!="number") $line_json.='"';
				$line_json.=',';
			}
		}

		// replace last comma by }
		$line_json[strlen($line_json)-1]='}';
		$arr_json_record.=' '.$line_json.',';

	};
	*/

	echo consoleMessage("info", "Script ends");

	/*
NATT
db.activity.remove({projectId:"d0b2f329-c394-464b-b5ab-e1e205585a7c"})
db.record.remove({projectId:"d0b2f329-c394-464b-b5ab-e1e205585a7c"})
db.output.remove({"dateCreated":{$gt:new ISODate("2020-11-06T10:13:15.184Z"), $lt:new ISODate("2020-11-06T10:15:15.184Z")}})
db.person.remove({"dateCreated":{$gt:new ISODate("2020-11-06T10:13:15.184Z"), $lt:new ISODate("2020-11-06T10:15:15.184Z")}})


STD
db.activity.remove({projectId:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c"})
db.record.remove({projectId:"89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c"})
db.output.remove({"dateCreated":{$gt:new ISODate("2020-11-05T16:37:15.184Z"), $lt:new ISODate("2020-11-05T16:38:38.184Z")}})
db.person.remove({"dateCreated":{$gt:new ISODate("2020-11-05T16:37:15.184Z"), $lt:new ISODate("2020-11-05T16:38:38.184Z")}})

*/
}


?>