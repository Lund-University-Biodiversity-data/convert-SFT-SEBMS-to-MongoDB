<?php
$database="SFT";
require "config.php";
require "functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

echo consoleMessage("info", "Script starts");

$county=getCountyLanArray();
$province=getProvinceLskArray();


// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna)
$arr_protocol=array("std", "natt");
// 2- mode: all (delete everything and recreate) 
$arr_mode=array("all");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt");
}
else {

	$protocol=$argv[1];

	if (!isset($argv[2]) || !in_array(trim($argv[2]), $arr_mode)) {
		consoleMessage("warning", "Second parameter missing => all");
		$mode="all";
	}
	else $mode=$argv[2];
	consoleMessage("info", "Mode: ".$mode);

	$commonFields=array();

	switch ($protocol) {
		case "std":

			$commonFields["projectId"]="dab767a5-929e-4733-b8eb-c9113194201f";
			break;
		case "natt":

			$commonFields["projectId"]="d9253588-6fe3-4a2a-a7cd-25fc283134f3";
			break;
	}

	$array_sites=array();
	$array_sites_req=array();

	echo "****CONVERT SFT ".$protocol." to MongoDB JSON****\n";

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));


	/*
	$qSpecies='SELECT DISTINCT art, dyntaxa_id, english_name FROM eurolist';
	$rSpecies = pg_query($db_connection, $qSpecies);
	if (!$rSpecies) die("QUERY:" . consoleMessage("error", pg_last_error()));

	$arr_species=array();

	while ($rtSpecies = pg_fetch_array($rSpecies)) {
		$arr_species[$rtSpecies["art"]]["dyntaxa"]=$rtSpecies["dyntaxa_id"];
		$arr_species[$rtSpecies["art"]]["en"]=$rtSpecies["english_name"];
		$arr_species[$rtSpecies["art"]]["sv"]=$rtSpecies["arthela"];
	}
	*/

	$qSites='	select * from standardrutter_koordinater sk, standardrutter_oversikt so 
				where sk.karta = so.karta
				and sk.karta in (select distinct karta from totalstandard t2 )
				';
	$rSites = pg_query($db_connection, $qSites);
	if (!$rSites) die("QUERY:" . consoleMessage("error", pg_last_error()));

	$arr_json_sites='[';

	while ($rtSites = pg_fetch_array($rSites)) {
		$siteId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

		$array_sites_req[]="'".$rtSites["karta"]."'";

		$array_sites[$rtSites["karta"]]=array();

		$array_sites[$rtSites["karta"]]["siteId"]=$siteId;
		$array_sites[$rtSites["karta"]]["decimalLatitude"]=$rtSites["mitt_wgs84_lat"];
		$array_sites[$rtSites["karta"]]["decimalLongitude"]=$rtSites["mitt_wgs84_lon"];


		// GBIF/DwC-A fields

		// latitude / longitude => use these one instead Mittpunkt_koordinater_topokarta

		$arr_json_sites.='{
			"siteId" : "'.$siteId.'",
			"locationID" : "'.$rtSites["karta"].'",
			"decimalLatitude" : '.$rtSites["mitt_wgs84_lat"].',
			"decimalLongitude" : '.$rtSites["mitt_wgs84_lon"].',
		';

		$arr_json_sites.='
			"subparts" : [';
		// all the subparts of the site
		for ($i=1;$i<=8;$i++) {
			$arr_json_sites.='
				{ "locationType" : "point", 
				"locationSubpartRank": '.$i.', 
				"locationInventoryMethod" : "punktinventering" },';
		}
		for ($i=1;$i<=8;$i++) {
			$arr_json_sites.='
				{ "locationType" : "line", 
				"locationSubpartRank": '.$i.', 
				"locationInventoryMethod" : "linjeinventering" },';
		}

		$arr_json_sites[strlen($arr_json_sites)-1]=' ';
		$arr_json_sites.='
			],';

		// other fields
		$arr_json_sites.='
			"locationType" : "rutt",
			"locationName" : "'.$rtSites["namn"].'",
			"geographicSystem" : "WGS84",
			"province" : "'.$province[$rtSites["lsk"]].'",
			"county" : "'.$county[$rtSites["lan"]].'",
			"locationRemarks" : "'.$rtSites["notering"].'"
		},';

	}

	$arr_json_sites[strlen($arr_json_sites)-1]=' ';
	$arr_json_sites.=']';

	$req_sites="(".implode(",", $array_sites_req).")";
	//print_r($array_sites);

//echo $req_sites;

	/*

	//$commonFields["occurenceID"]="????";
	$commonFields["status"]="active";
	$commonFields["recordedBy"]="Mathieu Blanchet";
	$commonFields["rightsHolder"]="Lund University";
	$commonFields["institutionID"]=$commonFields["rightsHolder"];
	$commonFields["basisOfRecord"]="HumanObservation";

	//$commonFields["eventTime"]=array("datetime", true, "08:42 AM");
	//$commonFields["verbatimCoordinates"]="";
	$commonFields["multimedia"]="[ ]";
	//$commonFields["activityId"]=?????;
	//$commonFields["eventID"]=$commonFields["activityId;


	//$commonFields["projectActivityId"]=array("string", true, "17ee4c9f-abe7-4926-b2d7-244f008ceaeb");
	$commonFields["userId"]=5;
	*/

	switch($protocol) {
		case "std":
			$qEvents="
			select P.efternamn, P.fornamn, TS.datum , p1, p2, p3, p4, p5, p6, p7, p8, l1, l2, l3, l4, l5, l6, l7, l8, TS.karta AS karta
			from totalstandard TS
			left join personer P on P.persnr = TS.persnr 
			where TS.karta IN ".$req_sites."
			AND TS.art='000'
			order by datum
			";
//where TS.karta='07D7C'  

			//$commonFields["datasetId"]=$commonFields["projectActivityId"];
			$commonFields["datasetName"]="test stadanrdrutter";
			//$commonFields["datasetName"]="Second STD survey";
			$commonFields["type"]="Bird Surveys - standardrutterna";
			$commonFields["name"]="standardrutterna";

			
			//$commonFields["locationID"]="507cb6c5-d439-4020-b7f7-b87d767541fa"; // create an array of sites 
			//$commonFields["locationName"]="STD_02C7H"; // create an array of sites 
			//$commonFields["decimalLatitude"]=55.78454; // create an array of sites 
			//$commonFields["decimalLongitude"]=13.2069; // create an array of sites 
			

			break;
		case "natt":
			// LPAD(cast(LEAST(p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20) as text), 4, '0')
			$qEvents="
			select P.efternamn, P.fornamn, TN.datum , p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.kartatx AS karta
			from totalnatt TN
			left join personer P on P.persnr = TN.persnr 
			where TN.kartatx='02DSV'  
			AND TN.art='000'
			order by datum
			";

			$commonFields["projectActivityId"]="7a47e9c7-ee3a-4681-939b-b144a1269fd0";
			$commonFields["datasetId"]=$commonFields["projectActivityId"];
			$commonFields["datasetName"]="vinterrutterna";
			$commonFields["type"]="Bird surveys - vinterrutterna";
			$commonFields["name"]="vinterrutterna";

			$commonFields["locationID"]="eccb9fcb-a12e-4fa6-95a3-ef7d329646e3"; // create an array of sites 
			$commonFields["locationName"]="Night route test 02DSV"; // create an array of sites 
			$commonFields["decimalLatitude"]=55.56793; // create an array of sites 
			$commonFields["decimalLongitude"]=13.61763; // create an array of sites 


			break;
	}



	//echo consoleMessage("info", $qEvents);
	$rEvents = pg_query($db_connection, $qEvents);
	if (!$rEvents) die("QUERY:" . consoleMessage("error", pg_last_error()));


	$arr_json_records='[';
	$nbLines=0;
	while ($rtEvents = pg_fetch_array($rEvents)) {

		$commonFields["siteId"]=$array_sites[$rtEvents["karta"]]["siteId"];
		$commonFields["locationName"]=$rtEvents["karta"]; 
		$commonFields["decimalLatitude"]=$array_sites[$rtEvents["karta"]]["decimalLatitude"]; // create an array of sites 
		$commonFields["decimalLongitude"]=$array_sites[$rtEvents["karta"]]["decimalLongitude"]; 


		switch($protocol) {
			case "std":
				$qRecords="
					select EL.arthela AS sv_name, EL.latin as scientificname, EL.dyntaxa_id as dyntaxaid, EL.englishname as en_name, p1, p2, p3, p4, p5, p6, p7, p8, l1, l2, l3, l4, l5, l6, l7, l8, TS.art, TS.datum
					from totalstandard TS, eurolist EL
					where EL.art=TS.art 
					and TS.karta='".$rtEvents["karta"]."'  
					AND EL.art<>'000'
					AND TS.datum='".$rtEvents["datum"]."'
					order by datum, scientificname
				";
				$nbPts=8;
				break;
			case "natt":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.art, TN.datum
					from totalnatt TN, eurolist EL
					where EL.art=TN.art 
					and TN.kartatx='".$rtEvents["karta"]."'  
					AND EL.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum, scientificname
				";
				$nbPts=20;
				break;
		}

		
		//echo $qRecords;
		$rRecords = pg_query($db_connection, $qRecords);
		if (!$rRecords) die("QUERY:" . consoleMessage("error", pg_last_error()));

		$nbLines++;
		if ($nbLines%100==0) echo number_format($nbLines, 0, ".", " ")." ĺines\n";

		$eventID=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

		// date now
		$micro_date = microtime();
		$date_array = explode(" ",$micro_date);
		$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".number_format($date_array[0]*1000, 0, ".", "")."Z";
		//echo "Date: $date_now_tz\n";

		// survey date
		$date_survey=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
		//echo "date survey".$date_survey."\n";

		// find the start time and finsh time
		$start_time=2359;
		$finish_time=0;
		$arr_time=array();

		for ($i=1; $i<=$nbPts; $i++) {
			switch($protocol) {
				case "std":
					$ind="p".$i;
					break;
				case "natt":
					$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
					break;
			}

			// add 24 hours to the night tmes, to help comparing
			if ($rtEvents[$ind]<1200)
				$rtEvents[$ind]+=2400;

			//echo $rtEvents[$ind]."\n";

			if ($rtEvents[$ind]<$start_time)
				$start_time=$rtEvents[$ind];

			if ($rtEvents[$ind]>$finish_time)
				$finish_time=$rtEvents[$ind];

			if ($rtEvents[$ind]>2400) $rtEvents[$ind]-=2400;

			$arr_time[$ind]=convertTime($rtEvents[$ind]);
		}

		if ($start_time>2400) $start_time-=2400;
		if ($finish_time>2400) $finish_time-=2400;

		$start_time_brut=$start_time;

		$start_time=convertTime($start_time);
		$finish_time=convertTime($finish_time);

		//echo "start_time: $start_time\n";
		//echo "finish_time: $finish_time\n";

		$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".substr($start_time_brut, 0, 2).":".substr($start_time_brut, 2, 4).":00Z";
		//echo "eventDate: $eventDate\n";


		$recorder_name=$rtEvents["fornamn"].' '.$rtEvents["efternamn"];


		$data_field="";
		while ($rtRecords = pg_fetch_array($rRecords)) {
			
			$data_field.='{';

			$IC=0;



			switch($protocol) {
				case "std":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".$i;
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field.='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="l".$i;
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field.='"L'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}
					break;
				case "natt":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field.='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}
					break;
			}

			

			$outputSpeciesId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

			$data_field.='
					"species" : {
						"commonName" : "'.$rtRecords["en_name"].'",
						"scientificName" : "'.$rtRecords["scientificname"].'",
						"taxonID" : "'.$rtRecords["dyntaxaid"].'",
						"name" : "'.$rtRecords["sv_name"].'"
					},';
			$data_field.='
					"individualCount" : '.$IC.',
					"occurenceRemarks" : ""
				},';

			$occurenceID=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");


			
		}
		// replace last comma by 
		$data_field[strlen($data_field)-1]=' ';
		//echo "data_field: ".$data_field."\n";

		$arr_json_records.='{
			"eventID" : "'.$eventID.'",
			"name" : "'.$commonFields["name"].'",
			"locationID" : "'.$commonFields["siteId"].'",
			"locationName" : "'.$commonFields["locationName"].'",
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"status" : "active",
			"eventDate" : "'.$eventDate.'",
			"eventTime" : "'.$start_time.'",
			"eventTimeEnd" : "'.$finish_time.'",
			"gpsUsed" : "", 
			"locationHiddenLatitude" : '.$commonFields["decimalLatitude"].',
			"locationLatitude" : '.$commonFields["decimalLatitude"].',
			"recordedBy" : "'.$recorder_name.'",
			"helper1": "",
			"helper2": "",
			"observations" : [
				'.$data_field.'
			]
		},';

	};

	// replace last comma by 
	$arr_json_records[strlen($arr_json_records)-1]=' ';
	$arr_json_records.=']';


	//echo $arr_json_output;
	//echo "\n";

	for ($i=1;$i<=2;$i++) {
		switch ($i) {
			case 1:
				$typeO="sites";
				$json=$arr_json_sites;
				break;
			case 2:
				$typeO="records";
				$json=$arr_json_records;
				break;
		}
		$filename_json='json_datahost_'.$protocol.'_'.$typeO.'_SFT_'.date("Y-m-d-His").'.json';
		$path='json/datahost/'.$protocol."/".$filename_json;
		echo 'mongoimport --db datahost --collection '.$typeO.' --jsonArray --file '.$path."\n";
		//$json = json_encode($arr_rt, JSON_UNESCAPED_SLASHES); 
		if ($fp = fopen($path, 'w')) {
			fwrite($fp, $json);
			fclose($fp);
		}
		else echo consoleMessage("error", "can't create file ".$path);

		//echo 'PATH: '.$path."\n\n";

	}

	echo "scp json/".$protocol."/json_* radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/json/".$protocol."/\n";
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


}


?>