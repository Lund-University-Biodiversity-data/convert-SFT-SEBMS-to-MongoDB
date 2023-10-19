<?php
$database="SFT";
$dataOrigin="scriptPostgres";

require "lib/config.php";
require "lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";


echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php convert_SFT.php std 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc", "kust2021", "kust2022");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode(" / ", $arr_protocol));
}
else {

	$projectsId = '[ "'.$commonFields["std"]["projectId"].'", "'.$commonFields["natt"]["projectId"].'", "'.$commonFields["vinter"]["projectId"].'", "'.$commonFields["sommar"]["projectId"].'", "'.$commonFields["kust"]["projectId"].'" ]';
	
	$protocol=$argv[1];

	if ($protocol=="kust2021") {
		$protocol="kust";
		$kustYEAR="_2021";
	}elseif ($protocol=="kust2022") {
		$protocol="kust";
		$kustYEAR="_2022_temp";
	}else{
		$kustYEAR="";
	}
	$arrSpeciesNotFound=array();
	$speciesNotFound=0;
	$speciesFound=0;
	$observationFieldName="observations";

	if (isset($argv[2]) && is_numeric($argv[2])) {
		$limitEvents=$argv[2];
		echo consoleMessage("info", "Number of events limited to ".$limitEvents);
	}

	if (isset($argv[3]) && $argv[3]=="debug") {
		$debug=true;
		echo consoleMessage("info", "DEBUG mode");
	}
	$array_persons=array();

	$array_sites=getArraySitesFromMongo($commonFields[$protocol]["projectId"], "TEST");
	$array_sites_req=array();

    $mng = new MongoDB\Driver\Manager(); // Driver Object created
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    else echo consoleMessage("error", "No connection to mongoDb");


	foreach($array_sites as $indexSite => $data) {
		$array_sites_req[]="'".$indexSite."'";
	}

	$req_sites="(".implode(",", $array_sites_req).")";


	echo consoleMessage("info", count($array_sites)." site(s) for the project ".$commonFields[$protocol]["projectId"]);
	/**************************** connection to mongoDB   ***/

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
			select P.efternamn, P.fornamn, P.persnr, T.datum, T.per, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, CONCAT(T.persnr, '-', T.rnr) AS sitename
			from punktrutter Pu, totalvinter_pkt T
			left join personer P on P.persnr = T.persnr 
			where CONCAT(T.persnr, '-', T.rnr) IN ".$req_sites."
			and Pu.persnr = T.persnr 
			and Pu.rnr=T.rnr
			AND T.art='000'
			order by datum
			";
			break;

		case "sommar":
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, T.datum, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, CONCAT(T.persnr, '-', T.rnr) AS sitename
			from punktrutter Pu, totalsommar_pkt T
			left join personer P on P.persnr = T.persnr 
			where CONCAT(T.persnr, '-', T.rnr) IN ".$req_sites."
			and Pu.persnr = T.persnr 
			and Pu.rnr=T.rnr
			AND T.art='000'
			order by datum
			";
			break;

		case "natt":
			// LPAD(cast(LEAST(p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20) as text), 4, '0')
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, TN.datum , p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.kartatx AS sitename, per
			from totalnatt TN
			left join personer P on P.persnr = TN.persnr 
			where TN.kartatx IN ".$req_sites."  
			AND TN.art='000'
			order by datum
			";

			/*
			$siteInfo["locationID"]="eccb9fcb-a12e-4fa6-95a3-ef7d329646e3"; 
			$siteInfo["locationName"]="Night route test 02DSV"; 
			$siteInfo["decimalLatitude"]=55.56793;
			$siteInfo["decimalLongitude"]=13.61763; 
			*/

			break;
		case "kust":
			// LPAD(cast(LEAST(p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20) as text), 4, '0')
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, T.datum ,T.ruta AS sitename, T.yr, KS.start, KS.stopp
			from totalkustfagel200".$kustYEAR." T
			left join personer P on P.persnr = T.persnr 
			left join kustfagel200_start_stopp".$kustYEAR."	 KS on T.ruta=KS.ruta AND T.datum=KS.datum 
			where T.ruta  IN ".$req_sites."  
			AND T.art='000'
			order by datum
			";

			break;

		case "iwc":

			/*
			
			
			*/
			$qEvents="
			select P.efternamn, P.fornamn, P.persnr, T.datum ,T.site AS sitename, T.yr, T.metod, 'januari' as period, T.komm
			from total_iwc_januari T
			left join personer P on P.persnr = T.persnr 
			where T.site  IN ".$req_sites."  
			AND T.art='000'
			UNION 
			select P.efternamn, P.fornamn, P.persnr, T.datum ,T.site AS sitename, T.yr, T.metod, 'september' as period, T.komm
			from total_iwc_september T
			left join personer P on P.persnr = T.persnr 
			where T.site  IN ".$req_sites."  
			AND T.art='000'
			order by datum
			";

			break;
	}

	if (isset($limitEvents) && $limitEvents>0)
		$qEvents.=" LIMIT ".$limitEvents;

	$array_species_guid=array();
	// GET the list of species
	foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
		$url="https://lists.biodiversitydata.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true&max=1000";
		$obj = json_decode(file_get_contents($url), true);

		foreach($obj as $sp) {
			

			// if no lsid => check the KVP values
			if(!isset($sp["lsid"])) {
				//echo consoleMessage("error", "No lsid for species ".$sp["name"]." => ");
				foreach ($sp["kvpValues"] as $iKvp => $kvp) {
					if ($kvp["key"]=="dyntaxa_id" && isset($kvp["value"]) && is_numeric($kvp["value"])) {
						echo consoleMessage("info", "dyntaxa_id found in KPVvalues for ".$sp["name"]);
						$array_species_guid[$animals][$sp["name"]]=$kvp["value"];
					}
				}
			}
			else {
				//$array_species_guid[$sp["scientificName"]]=$sp["lsid"];
				$array_species_guid[$animals][$sp["name"]]=$sp["lsid"];
			}

			if (!isset($array_species_guid[$animals][$sp["name"]])) {
				echo consoleMessage("error", "No dyntaxa_id for ".$sp["name"]);
			}
		}

		echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"][$animals]." obtained. ".count($obj)." elements");
//exit();
//print_r($array_species_guid[$animals]);
	}

	//print_r($array_species_guid);

	echo "****CONVERT ".$database." ".$protocol." to MongoDB JSON****\n";

	$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

	if ($debug) echo consoleMessage("info", "qEvents : ".$qEvents);
	$rEvents = pg_query($db_connection, $qEvents);
	if (!$rEvents) die("QUERY:" . consoleMessage("error", pg_last_error()));


	$arr_json_activity='[
	';
	$arr_json_output='[
	';
	$arr_json_record='[
	';
	$arr_json_person='[
	';
	$nbLines=0;

	
	$tabHelperMatch=array();


	while ($rtEvents = pg_fetch_array($rEvents)) {

		$siteInfo["locationID"]=$array_sites[$rtEvents["sitename"]]["locationID"];
		//$siteInfo["locationName"]=$array_sites[$rtEvents["sitename"]]["commonName"]; 
		$siteInfo["locationName"]=$array_sites[$rtEvents["sitename"]]["locationName"]; 
		$siteInfo["decimalLatitude"]=$array_sites[$rtEvents["sitename"]]["decimalLatitude"]; // create an array of sites 
		$siteInfo["decimalLongitude"]=$array_sites[$rtEvents["sitename"]]["decimalLongitude"]; 


/*
// A VIRER ABSOLUEMNT A LA FIN DU TEST
$siteInfo["locationID"]="d1ba4c70-abdb-42d9-aa69-af12b42b81a4";
$siteInfo["locationName"]="27K7H, RAATUKKAV."; 
$siteInfo["decimalLatitude"]=21.229625768756662; // create an array of sites 
$siteInfo["decimalLongitude"]=66.93673750351373; 
*/


		switch($protocol) {
			case "std":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p1, p2, p3, p4, p5, p6, p7, p8, l1, l2, l3, l4, l5, l6, l7, l8, TS.art, TS.datum, pkind, lind, EL.rank
					from totalstandard TS, eurolist EL
					where EL.art=TS.art 
					and TS.karta='".$rtEvents["sitename"]."'  
					AND TS.art<>'000'
					AND TS.datum='".$rtEvents["datum"]."'
					order by datum
				";
				//$qRecords.=" LIMIT 20";
				$nbPts=8;
				break;
			case "vinter":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, pk, ind, TN.art, TN.datum, EL.rank
					from punktrutter Pu, totalvinter_pkt TN, eurolist EL
					where EL.art=TN.art 
					AND TN.persnr=PU.persnr
					AND TN.rnr=PU.rnr
					and CONCAT(TN.persnr, '-', TN.rnr)='".$rtEvents["sitename"]."'  
					AND TN.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=20;
				break;
			case "sommar":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, pk, ind, TN.art, TN.datum, EL.rank
					from punktrutter Pu, totalsommar_pkt TN, eurolist EL
					where EL.art=TN.art 
					AND TN.persnr=PU.persnr
					AND TN.rnr=PU.rnr
					and CONCAT(TN.persnr, '-', TN.rnr)='".$rtEvents["sitename"]."'  
					AND TN.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=20;
				break;
			case "natt":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.art, TN.datum, TN.pt, TN.kull, EL.rank
					from totalnatt TN, eurolist EL
					where EL.art=TN.art 
					and TN.kartatx='".$rtEvents["sitename"]."'  
					AND TN.art<>'000'
					AND TN.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=20;
				break;
			case "kust":
				$qRecords="
					select EL.arthela AS names, EL.latin as scientificname, i100m, ind, openw, T.art, T.datum, EL.rank
					from totalkustfagel200".$kustYEAR." T, eurolist EL
					where EL.art=T.art 
					and T.ruta='".$rtEvents["sitename"]."'  
					AND T.art<>'000'
					AND T.datum='".$rtEvents["datum"]."'
					order by datum
				";
				$nbPts=1;
				break;
			case "iwc":
			/*
select EL.arthela AS names, EL.latin as scientificname, T.art, T.datum, T.antal 
					from total_iwc_januari T, eurolist EL
					where EL.art=T.art 
					and T.site='".$rtEvents["sitename"]."'  
					AND T.art<>'000'
					AND T.yr='".$rtEvents["yr"]."'
					union
			*/
				$qRecords="
					
					select EL.arthela AS names, EL.latin as scientificname, T.art, T.datum, T.antal
					from total_iwc_".$rtEvents["period"]." T, eurolist EL
					where EL.art=T.art 
					and T.site='".$rtEvents["sitename"]."'  
					AND T.art<>'000'
					AND T.yr='".$rtEvents["yr"]."'
					AND T.metod='".$rtEvents["metod"]."'
					order by datum
				";

				$nbPts=1;
				break;
		}

		if (isset($limitEvents) && $limitEvents>0)
			$qRecords.=" LIMIT ".$limitEvents;
		
		//if ($debug) echo consoleMessage("info", "qRecords :". $qRecords);

		//echo $qRecords;
		$rRecords = pg_query($db_connection, $qRecords);
		if (!$rRecords) die("QUERY:" . consoleMessage("error", pg_last_error()));
		$nbRecords=pg_num_rows($rRecords);
		
		$nbLines++;
		if ($nbLines%100==0) echo number_format($nbLines, 0, ".", " ")." ĺines\n";

		$activityId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");
		$eventID=$activityId;
		$outputId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

		//echo "outputId: $outputId\n";

		$eventRemarks="";

		// survey date
		$date_survey=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";

		// date now
		$micro_date = microtime();
		$date_array = explode(" ",$micro_date);
		$micsec=number_format($date_array[0]*1000, 0, ".", "");
		$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
		if ($micsec==1000) $micsec=999;
		$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";
		//echo "Date: $date_now_tz\n";

		$helpers="[{}]";
		$helperIds=array();

		if ($protocol=="iwc") {

			$start_time="";


			if (is_null($rtEvents["datum"]) || trim($rtEvents["datum"])=="") {
				if ($rtEvents["period"]=="januari") {
					$rtEvents["datum"]=$rtEvents["yr"]."0115";
					$eventRemarks.="Date missing in historical data => 15 Jan. ";
				}
				elseif ($rtEvents["period"]=="september") {
					$rtEvents["datum"]=$rtEvents["yr"]."0910";
					$eventRemarks.="Date missing in historical data => 10 Sep. ";
				}
			}
			$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
			$date_survey=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";

			$eventRemarks.=$rtEvents["komm"];

			//$helpers=getHelpers($db_connection, $protocol, $rtEvents["sitename"], $rtEvents["datum"], $rtEvents["persnr"]);
			$helpersArr=getHelpers($db_connection, $protocol, $rtEvents["sitename"], $rtEvents["datum"], $rtEvents["persnr"]);

			$helpers= "[ ";
			$helperIds=array();
		    foreach ($helpersArr as $helpPersnr => $helpName) {
		        $helpers.= '
		                {"helper" : "'.$helpName.'"},';

		        if (!isset($tabHelperMatch[$helpPersnr])) {
		        	$helperMongo=getPersonFromInternalId($helpPersnr, "TEST");
		        	//echo "helper : ".$helperMongo["personId"]."\n";
		        	$tabHelperMatch[$helpPersnr]=$helperMongo["personId"];
		        }

		        $helperIds[]=$tabHelperMatch[$helpPersnr];
		    }
		    $helpers[strlen($helpers)-1]=' ';
		    $helpers.= "]";


			$per=ucfirst($rtEvents["period"]);
			switch ($rtEvents["metod"]) {
				case "L":
					$observedFrom="land";
					break;
				case "B":
					$observedFrom="båt";
					break;	
				case "F":
					$observedFrom="flyg";
					break;
				case "X":
					$observedFrom="X";
					break;
				default:
					$observedFrom="error";
					echo consoleMessage("error", "unknown method : ".$rtEvents["metod"]);
					break;
			}
			// check the number of records 
			if ($nbRecords==0) {
				$noSpecies="ja";
				//echo "NO SPECIES ".$rtEvents["datum"]." ".$rtEvents["yr"]." ".$rtEvents["sitename"]." ".$rtEvents["period"];
			}
			else $noSpecies="nej";
			
			$specific_iwc='"observedFrom" : "'.$observedFrom.'",
				"period" : "'.$per.'",
				"windSpeedKmPerHourCategorical": "",
				"istäcke" : "",
				"windDirectionCategorical" : "",
				"noSpecies" : "'.$noSpecies.'",';

		}
		elseif ($protocol=="kust") {

			if ($rtEvents["start"]!="") {
				$start_time=substr($rtEvents["start"], 0, 5);
				$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".$start_time.":00Z";
			}
			else {
				$start_time="";
				$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
			}
			if ($rtEvents["stopp"]!="") {
				$finish_time=substr($rtEvents["stopp"], 0, 5);
			}
			else $finish_time="";

			$qDuckl="
			select *
			from kustfagel200_ejderungar".$kustYEAR." KE
			where KE.ruta = '".$rtEvents["sitename"]."'
			AND CAST(yr AS text)=LEFT('".$rtEvents["datum"]."',4)
			AND persnr='".$rtEvents["persnr"]."'
			";
			$rDuckl = pg_query($db_connection, $qDuckl);
			if (!$rDuckl) die("QUERY:" . consoleMessage("error", pg_last_error()));

			$ducklingsCounted='"ducklingsCounted" : "nej"';
			$ducklingCount='"ducklingCount" : ""';
			$ducklingSize='"ducklingSize" : ""';

			if (pg_num_rows($rDuckl)!=1) {
				//echo consoleMessage("warning", pg_num_rows($rDuckl)." row(s) of kustfagel200_ejderungar for ruta/persnr/yr ".$rtEvents["sitename"]."/".$rtEvents["persnr"]."/".$rtEvents["yr"]);
			}
			else {
				$rtDuckl = pg_fetch_array($rDuckl);
				$ducklingsCounted='"ducklingsCounted" : "'. ($rtDuckl["ungar_inventerade"] =="j" ? "ja" : "nej").'"';
				$ducklingCount='"ducklingCount" : "'. $rtDuckl["antal"].'"';
				$ducklingSize='"ducklingSize" : "'. $rtDuckl["storlek"].'"';
			}			

			$specific_kust=$ducklingsCounted.',
				'.$ducklingCount.',
				'.$ducklingSize.',
				';

			//$helpers=getHelpers($db_connection, $protocol, $rtEvents["sitename"], $rtEvents["datum"], $rtEvents["persnr"]);

			$helpersArr=getHelpers($db_connection, $protocol, $rtEvents["sitename"], $rtEvents["datum"], $rtEvents["persnr"]);

			$helpers= "[ ";
			$helperIds=array();
		    foreach ($helpersArr as $helpPersnr => $helpName) {
		        $helpers.= '
		                {"helper" : "'.$helpName.'"},';

		        if (!isset($tabHelperMatch[$helpPersnr])) {
		        	$helperMongo=getPersonFromInternalId($helpPersnr, "TEST");
		        	//echo "helper : ".$helperMongo["personId"]."\n";
		        	$tabHelperMatch[$helpPersnr]=$helperMongo["personId"];
		        }

		        $helperIds[]=$tabHelperMatch[$helpPersnr];
		    }
		    $helpers[strlen($helpers)-1]=' ';
		    $helpers.= "]";


			/*$qHelpers="
			select *
			from kustfagel200_medobs KE, personer P
			where P.persnr=KE.persnr
			AND KE.ruta = '".$rtEvents["sitename"]."'
			AND CAST(yr AS text)=LEFT('".$rtEvents["datum"]."',4)
			AND P.persnr<>'".$rtEvents["persnr"]."'
			";
			$rHelpers = pg_query($db_connection, $qHelpers);
			if (!$rHelpers) die("QUERY:" . consoleMessage("error", pg_last_error()));

			$helpers= "[
			";
			while ($rtHelpers = pg_fetch_array($rHelpers)) {
				$help=$rtHelpers["fornamn"].' '.$rtHelpers["efternamn"];
				$helpers.= '
					{"helper" : "'.$help.'"},';
			}
			$helpers[strlen($helpers)-1]=' ';
			$helpers.= "]";*/

			$arrKustMammals=array();
		}
		elseif ($protocol=="vinter" || $protocol=="sommar") {
			$start_time="";
			$finish_time="";
			$transport="";
			$snow="";

			if (isset($rtEvents["p01"]) && is_numeric($rtEvents["p01"]) && $rtEvents["p01"]<=4) {
				$transport=$rtEvents["p01"];
			}
			if (isset($rtEvents["p02"]) && is_numeric($rtEvents["p02"]) && $rtEvents["p02"]<=3) {
				$snow=$rtEvents["p02"];
			}
			if (isset($rtEvents["p03"])) {
				$start_time=convertTime($rtEvents["p03"], "24H");
			}
			if (isset($rtEvents["p04"])) {
				$finish_time=convertTime($rtEvents["p04"], "24H");
			}

			$specific_punkt="";

			if ($protocol=="vinter") {
				$per=$rtEvents["per"];
				$specific_punkt.='"period" : "'.$per.'", 
					"snow" : "'.$snow.'",';
			} 
			
			$specific_punkt.='"transport" : "'.$transport.'",';

			if ($start_time=="") {
				$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
			}
			else {
				$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".$start_time.":00Z";
			}

		}
		else {


			// find the start time and finsh time
			$start_time=2359+2400;
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
					case "sommar":
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						break;
					case "natt":

						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);

						$timeOfObservation.='
						"TidP'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$rtEvents[$ind].'",';

						break;
				}

				if ($rtEvents[$ind]!="") {
					$rtEvents[$ind]=intval($rtEvents[$ind]);

					// add 24 hours to the night tmes, to help comparing
					if ($rtEvents[$ind]<1200)
						$rtEvents[$ind]+=2400;

					if ($rtEvents[$ind]<$start_time)
						$start_time=$rtEvents[$ind];

					if ($rtEvents[$ind]>$finish_time)
						$finish_time=$rtEvents[$ind];

					if ($rtEvents[$ind]>2400) $rtEvents[$ind]-=2400;

					$arr_time[$ind]=convertTime($rtEvents[$ind]);

				}
			}

			if ($start_time>2400) $start_time-=2400;
			if ($finish_time>2400) $finish_time-=2400;

			$start_time_brut=$start_time;

			$start_time=convertTime($start_time, "24H");

			// add 5 minutes 
			$finish_time_5min=str_pad($finish_time, 4, '0', STR_PAD_LEFT);
			$hours=intval(substr($finish_time_5min, 0, 2));
			$minutes=intval(substr($finish_time_5min, 2, 2));

			if ($minutes>=55) {
				$minutes=str_pad(($minutes+5)-60, 2, '0', STR_PAD_LEFT);

				if ($hours==23) $hours=0;
				else $hours++;
			}
			else {
				$minutes+=5;
			}
			$minutes=str_pad($minutes, 2, '0', STR_PAD_LEFT);
			$finish_time=intval($hours.$minutes);

			$finish_time=convertTime($finish_time, "24H");

			//$eventTime=date("H:i", strtotime($rtEvents["datum"]));

			$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".$start_time.":00Z";
			//echo "eventDate: $eventDate\n";

			$minutesSpentObserving[strlen($minutesSpentObserving)-1]=' ';
			$minutesSpentObserving.='}],';
			$timeOfObservation[strlen($timeOfObservation)-1]=' ';
			$timeOfObservation.='}],';


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

				$eventRemarks=str_replace('"', "|", str_replace("\\n", "|", $rtEvents["txt"]));

				$eventRemarks=str_replace("
", "|", $eventRemarks);

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

		}

		// check observers
		$recorder_name=$rtEvents["fornamn"].' '.$rtEvents["efternamn"];
		$userId = $commonFields["userId"];

		if (!isset($array_persons[$rtEvents["persnr"]])) {

			$filter = ['internalPersonId' => $rtEvents["persnr"]];
			//$filter = [];
			$options = [];

			//db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
			// 
			$query = new MongoDB\Driver\Query($filter, $options); 
			$rows = $mng->executeQuery("ecodata.person", $query);

			$docPerson = iterator_to_array($rows);
			if (count($docPerson)!=0) {
				$array_persons[$rtEvents["persnr"]]["personId"] = $docPerson[0]->personId;
				$array_persons[$rtEvents["persnr"]]["userId"] = (isset($docPerson[0]->userId) ? $docPerson[0]->userId : $userId);
				//echo consoleMessage("info", "Person exists in database: ".$array_persons[$rtEvents["persnr"]]);
			}
			else {

				$array_persons[$rtEvents["persnr"]]["personId"]=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

				$qPerson= "
						select *
						from personer 
						where persnr='".$rtEvents["persnr"]."'  
					";
				$rPerson = pg_query($db_connection, $qPerson);
				$rtPerson = pg_fetch_array($rPerson);

				$explBD=explode("-", $rtEvents["persnr"]);
				$birthDate="19".substr($explBD[0], 0, 2)."-".substr($explBD[0], 2, 2)."-".substr($explBD[0], 4, 2);


				$arr_json_person.='{
					"dateCreated" : ISODate("'.$date_now_tz.'"),
					"lastUpdated" : ISODate("'.$date_now_tz.'"),
					"personId" : "'.$array_persons[$rtEvents["persnr"]]["personId"].'",
					"firstName" : "'.$rtPerson["fornamn"].'",
					"lastName" : "'.$rtPerson["efternamn"].'",
					"gender" : "'.$rtPerson["sx"].'",
					"birthDate" : "'.$birthDate.'",
					"email" : "'.$rtPerson["epost"].'",
					"phoneNum" : "'.$rtPerson["telhem"].'",
					"mobileNum" : "'.$rtPerson["telmobil"].'",
					"address1" : "'.$rtPerson["adress1"].'",
					"address2" : "'.$rtPerson["adress2"].'",
					"postCode" : "'.$rtPerson["postnr"].'",
					"town" : "'.$rtPerson["ort"].'",
					"projects": '.$projectsId.',
					"internalPersonId" : "'.$rtEvents["persnr"].'"
				},';

			}
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

				case "sommar":
				case "vinter":
				case "iwc":
					
					$animals="birds";
					$speciesFieldName="species";
					$animalsDataField=$animals;

				break;

				case "kust":
					if ($rtRecords["art"]=="714") {
						$animals="mammals";
						$arrKustMammals[]='"mink"';
					}
					elseif ($rtRecords["art"]=="719") {
						$animals="mammals";
						$arrKustMammals[]='"grävling"';
					}
					elseif ($rtRecords["art"]=="709") {
						$animals="mammals";
						$arrKustMammals[]='"rödräv"';
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
				case "sommar":
					// we use the field ind for the total, becausse historical rows do not have any details in p01->p20, only data in ind.
					$IC=$rtRecords["ind"];
					if (is_null($rtRecords["ind"])) echo consoleMessage("error", "ind value is NULL at date ".$date_survey);

					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						//$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}
					$data_field[$animalsDataField].='"pk": "'.$rtRecords["pk"].'", ';
					break;
				case "natt":
					for ($i=1; $i<=$nbPts; $i++) {
						$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
						if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
						$IC+=$rtRecords[$ind];

						$data_field[$animalsDataField].='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": "'.$rtRecords[$ind].'",';
					}

					break;

				case "kust":

					if ($animalsDataField=="birds") {
						$data_field[$animalsDataField].='"island" : "'.($rtRecords["i100m"]!="" ? $rtRecords["i100m"] : 0).'",';
						$data_field[$animalsDataField].='"water" : "'.($rtRecords["openw"]!="" ? $rtRecords["openw"] : 0).'",';
						$IC=$rtRecords["ind"];

					}

					break;

				case "iwc":

					if ($animalsDataField=="birds") {
						

						if (trim($rtRecords["antal"])=="") {
							echo consoleMessage("error", "No number of species for ".$rtRecords["art"].$rtEvents["sitename"].$rtEvents["datum"]);
							exit();
						}
						$IC=$rtRecords["antal"];

					}

					break;



			}

			
			

			if (isset($array_species_guid[$animals][$rtRecords["scientificname"]]) && $array_species_guid[$animals][$rtRecords["scientificname"]]!="-1") {
				$speciesFound++;
				$listId=$commonFields["listSpeciesId"][$animals];
				$guid=$array_species_guid[$animals][$rtRecords["scientificname"]];
			}
			else {
				if ($debug) {
					echo consoleMessage("error", "No species guid for -".$rtRecords["scientificname"]."- art#".$rtRecords["art"]." (".$rtEvents["sitename"]."/".$rtEvents["datum"].") in list of ".$animals);
					exit();
				}
				$speciesNotFound++;
				$listId="error-unmatched";
				$guid="";
				$array_species_guid[$animals][$rtRecords["scientificname"]]="-1";

				if (isset($arrSpeciesNotFound[$rtRecords["scientificname"]]) && $arrSpeciesNotFound[$rtRecords["scientificname"]]>0)
					$arrSpeciesNotFound[$rtRecords["scientificname"]]++;
				else $arrSpeciesNotFound[$rtRecords["scientificname"]]=1;
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
			$data_field[$animalsDataField].='"individualCount" : '.$IC.',
							  "swedishRank": "'.$rtRecords["rank"].'"
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
				"eventRemarks" : "'.$eventRemarks.'",
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
				"userId" : "'.$array_persons[$rtEvents["persnr"]]["userId"].'"
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
			"userId" : "'.$array_persons[$rtEvents["persnr"]]["userId"].'",
			"personId" : "'.$array_persons[$rtEvents["persnr"]]["personId"].'",
			"verificationStatus" : "approved",
			"mainTheme" : ""
			'.(count($helperIds)>0 ? ', "helperIds" : ["'.implode('","', $helperIds ).'"]' : "").'
		},';


		//			"transectName" : "'.$siteInfo["locationName"].'"

		$specific_fields="";

		switch ($protocol) {

			case "std":
				$specific_fields.='
				"surveyStartTime" : "'.$start_time.'",
				"surveyFinishTime" : "'.$finish_time.'",
				'.$timeOfObservation.'
				'.$distanceCovered.'
				'.$minutesSpentObserving.'
				"isGpsUsed" : "'.$isGpsUsed.'",
				"mammalObservations" : [
					'.$data_field["mammals"].'
				],
				'
				;


			break;

			case "natt":
			$specific_fields.='
				"surveyStartTime" : "'.$start_time.'",
				"surveyFinishTime" : "'.$finish_time.'",'.
				$specific_natt.
				$timeOfObservation.
				'
				"mammalObservations" : [
					'.$data_field["mammals"].'
				],
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

			case "kust":
				$specific_fields.='
				"surveyStartTime" : "'.$start_time.'",
				"surveyFinishTime" : "'.$finish_time.'",'.
				$specific_kust.
					'"mammalObservations" : [
					'.(count($arrKustMammals)>0 ? implode(",", $arrKustMammals) : '"nej"').'
				],';


			break;

			case "sommar":
			case "vinter":
				$specific_fields.='
				"surveyStartTime" : "'.$start_time.'",
				"surveyFinishTime" : "'.$finish_time.'",'.
				$specific_punkt;
				break;

			case "iwc":
				$specific_fields.=$specific_iwc;
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
				"locationAccuracy" : 50,
				"surveyDate" : "'.$date_survey.'",
				'.$specific_fields.'
				"locationHiddenLatitude" : '.$siteInfo["decimalLatitude"].',
				"locationLatitude" : '.$siteInfo["decimalLatitude"].',
				"locationSource" : "Google maps",
				"recordedBy" : "'.$recorder_name.'",
				"helpers" : '.$helpers.',
				"locationCentroidLongitude" : null,
				"observations" : [
					'.$data_field["birds"].'
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
			"dataOrigin" : "'.$dataOrigin.'"
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
		$filename_json='postgres_json_'.$database.'_'.$protocol.'_'.$typeO.'s_'.date("Y-m-d-His").'.json';
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
	
	echo consoleMessage("info", "Species ratio found in the species lists : ".$speciesFound." / ".($speciesFound+$speciesNotFound)." = ".number_format($ratioSpecies*100, 2)."%");

	if ($ratioSpecies!=1) {
		echo consoleMessage("info", "Species not found :");
		var_dump($arrSpeciesNotFound);
	}

	echo "scp dump_json_sft_sebms/".$database."/".$protocol."/postgres_json_* radar@canmove-dev.ekol.lu.se:/home/radar/convert-SFT-SEBMS-to-MongoDB/dump_json_sft_sebms/".$database."/".$protocol."/\n";
	echo "scp dump_json_sft_sebms/".$database."/".$protocol."/postgres_json_* ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/dump_json_sft_sebms/".$database."/".$protocol."/\n";

	echo consoleMessage("info", "Script ends");

}


?>