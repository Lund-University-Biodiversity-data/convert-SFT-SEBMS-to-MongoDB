<?php
$database="SFT";
$dataOrigin="scriptExcel";

require dirname(__FILE__)."/lib/config.php";
require dirname(__FILE__)."/lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

//require_once "lib/PHPExcel/Classes/PHPExcel.php";

$server=DEFAULT_SERVER;

//require SCRIPT_PATH.'vendor/autoload.php';
//use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php create_extract_excel.php std 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfåglarrutterna - iwc (sjöfåglarrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust / iwc");
}
else {

	$protocol=$argv[1];

	if (isset($argv[2]) && is_numeric($argv[2])) {
		$limitFiles=$argv[2];
		echo consoleMessage("info", "Number of events limited to ".$limitFiles);
	}

	if (isset($argv[3]) && $argv[3]=="debug") {
		$debug=true;
		echo consoleMessage("info", "DEBUG mode");
	}

    $array_species_guid=array();
    $array_species_art=array();
    // GET the list of species
    foreach ($commonFields["listSpeciesId"] as $animals => $listId) {


        $url="https://lists.biodiversitydata.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true";
        $obj = json_decode(file_get_contents($url), true);

        echo consoleMessage("info", "reading species list ".$url);

        // for the punktrutter, we gather owls and birds
        if (($protocol=="vinter" || $protocol=="sommar") && $animals=="owls") {
            $animals="birds";
        } 

        foreach($obj as $sp) {

            // if no-lsid, use the species name instead 
            if (trim($sp["lsid"])=="") {
                $indexSp=$sp["name"];
                echo consoleMessage("warning", "No lsid/guid for ".$sp["name"]." use name instead (".$indexSp.")");
            }
            else {
                $indexSp=$sp["lsid"];
            }

            $art="";

            foreach($sp["kvpValues"] as $iSp => $eltSp) {

                if (isset($eltSp["key"]) && $eltSp["key"]=="art") {
                    $art=$eltSp["value"];
                    $art=str_pad($art, 3, '0', STR_PAD_LEFT);
                }
            }

            if ($art=="") {
                echo consoleMessage("error", "No art for ".$sp["name"]."/".$sp["lsid"]);
                $art="-";
            }
            else {
                $array_species_art[$animals][$indexSp]=$art;

                // fix : add the name as well, in case the lsid change during time
                $array_species_art[$animals][$sp["name"]]=$art;
            }
            /*
            if (isset($sp["kvpValues"][0]["key"]) && $sp["kvpValues"][0]["key"]=="art") {
                $art=$sp["kvpValues"][0]["value"];
                $array_species_art[$animals][$sp["lsid"]]=$art;
            }
            else {
                echo consoleMessage("error", "No ART for ".$sp["name"]);
            }
            */
        }
        echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"][$animals]." obtained for ".$animals.". ".count($obj)." elements");
    }
	switch($protocol) {
		case "std":
			$nbPts=8;

            $headers=array("persnr", "karta", "datum", "yr", "verificationStatus", "art", "p1", "p2", "p3", "p4", "p5", "p6", "p7", "p8", "l1", "l2", "l3", "l4", "l5", "l6", "l7", "l8", "pkind", "lind", "activityIdMongo");

			break;
		case "vinter":
			$nbPts=20;

            $headers=array("persnr", "rnr", "datum", "yr", "verificationStatus", "art", "per", "p01", "p02", "p03", "p04", "p05", "p06", "p07", "p08", "p09", "p10", "p11", "p12", "p13", "p14", "p15", "p16", "p17", "p18", "p19", "p20", "pk", "ind", "activityIdMongo", "activityIdMongo");


			break;
        case "sommar":
            $nbPts=20;

            $headers=array("persnr", "rnr", "datum", "yr", "verificationStatus", "art", "p01", "p02", "p03", "p04", "p05", "p06", "p07", "p08", "p09", "p10", "p11", "p12", "p13", "p14", "p15", "p16", "p17", "p18", "p19", "p20", "pk", "ind", "activityIdMongo", "activityIdMongo");


            break;
		case "natt":
			$nbPts=20;

            $headers=array("persnr", "kartatx", "per", "datum", "yr", "verificationStatus", "art", "kull", "pt", "p01", "p02", "p03", "p04", "p05", "p06", "p07", "p08", "p09", "p10", "p11", "p12", "p13", "p14", "p15", "p16", "p17", "p18", "p19", "p20", "pk", "ind", "activityIdMongo");

            $commonFields["listSpeciesId"]["mammalsOnRoad"]=$commonFields["listSpeciesId"]["mammals"];
            $array_species_art["mammalsOnRoad"]=$array_species_art["mammals"];

			break;
		case "kust":

            $headers=array("persnr", "ruta", "datum", "yr", "verificationStatus", "art", "i100m", "openw", "ind", "surveyStartTime", "surveyFinishTime", "pulliCounted", "pulliCount", "pulliSize", "activityIdMongo");
			$nbPts=1;
			break;
        case "iwc":

            $headers=array("persnr", "site", "datum", "yr", "verificationStatus", "art", "period", "metod", "antal", "komm", "activityIdMongo");
            $nbPts=1;
            break;
	}

	$array_sites=array();
	$array_sites_mongo=array();

	$array_sites=getArraySitesFromMongo($commonFields[$protocol]["projectId"], $server);

	foreach($array_sites as $indexSite => $data) {
		$array_sites_mongo[$data["locationID"]]=$indexSite;
	}

    echo consoleMessage("info", count($array_sites)." site(s) for the project ".$commonFields[$protocol]["projectId"]);

    //if ($debug) print_r($array_sites_mongo);

	/**************************** connection to mongoDB   ***/
    $mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created
    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    else echo consoleMessage("error", "No connection to mongoDb");
    
    $filter = ['projects' => $commonFields[$protocol]["projectId"]];
    //$filter = [];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    $rowsPerson = $mng->executeQuery("ecodata.person", $query);
    $rowsToArrayPerson=$rowsPerson->toArray();
    echo consoleMessage("info", count($rowsToArrayPerson)." row(s) in person");
    $arrPerson=array();

    foreach ($rowsToArrayPerson as $row){
        if (property_exists($row, "internalPersonId")) {
            $arrPerson[$row->personId]=$row->internalPersonId;
        }
        else  echo consoleMessage("error", "No internalPersonId for ".$row->firstName." ".$row->lastName);
        
    }

    $filter = ['projectActivityId' => $commonFields[$protocol]["projectActivityId"], "status" => "active" , "verificationStatus" => ['$in' => array("approved", "under review")]];
    //$filter = [];
    $options = [];
    if (isset($limitFiles) && is_numeric($limitFiles)) {
        $options = [
           'limit' => $limitFiles,
        ];    
    }
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 

    $rowsActivity = $mng->executeQuery("ecodata.activity", $query);

    $rowsToArrayActivity=$rowsActivity->toArray();
    echo consoleMessage("info", count($rowsToArrayActivity)." row(s) in activities");
    $arrActivity=array();

    foreach ($rowsToArrayActivity as $row){
        $arrActivity[]=$row->activityId;
        $arrActivityDetails[$row->activityId]["personId"]=$row->personId;
        $arrActivityDetails[$row->activityId]["verificationStatus"]=$row->verificationStatus;
    }

    $filter = ["activityId" => ['$in' => $arrActivity], "status" => "active"];
    //$filter = [];
    $options = [];
    if (isset($limitFiles)) {
        $options = [
           'limit' => $limitFiles,
        ];    
    } 
    $query = new MongoDB\Driver\Query($filter, $options); 
    $rowsOutput = $mng->executeQuery("ecodata.output", $query);

    $rowsToArrayOutput=$rowsOutput->toArray();
    echo consoleMessage("info", count($rowsToArrayOutput)." row(s) in outputs");
    $arrOutput=array();

    foreach ($rowsToArrayOutput as $row){
        $arrOutput[]=$row->outputId;
    }


    $filter = ['projectActivityId' => $commonFields[$protocol]["projectActivityId"], "status" => "active"];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 
    $rowsRecord = $mng->executeQuery("ecodata.record", $query);

    $rowsToArrayRecord=$rowsRecord->toArray();
    echo consoleMessage("info", count($rowsToArrayRecord)." row(s) in records");
    $arrRecord=array();
    $arrOutputFromRecord=array();

    foreach ($rowsToArrayRecord as $row){
        $arrRecord[]=$row->occurrenceID;
        $arrOutputFromRecord[$row->outputId]["eventDate"]=$row->eventDate;  
    }

    $filename=date("Ymd-His")."_extract_".$database."_".$protocol.".csv";
    $path_extract=PATH_DOWNLOAD."extract/".$database."/".$protocol."/".$filename;


    if ($fp = fopen($path_extract, 'w')) {

        fputcsv($fp, $headers, ";");

        $nbR=0;
        foreach ($rowsToArrayOutput as $output) {

            $line=array();

            if (!isset($arrOutputFromRecord[$output->outputId])) {
                if ($debug) echo consoleMessage("warn", "OUTPUT ID ".$output->outputId." does not exist in records array => no records");
            }


            //echo "date avant :".$output->data->surveyDate."<br>";
            //$eventDate=$output->data->surveyDate;
            $eventDate=substr(str_replace("-", "", $output->data->surveyDate), 0, 8);
            //echo "date avant :".$eventDate."<br>";exit();


            $eventDateDetails=array();
            $out=sscanf($output->data->surveyDate, "%d-%d-%dT%d:%d:%dZ", $eventDateDetails["year"], $eventDateDetails["month"], $eventDateDetails["day"], $eventDateDetails["hour"],    $eventDateDetails["minute"], $eventDateDetails["second"]);
            
            if (($eventDateDetails["hour"]==22 || $eventDateDetails["hour"]==23) && $eventDateDetails["minute"]==0 && $eventDateDetails["second"]==0) {

                $eventDate=date('Ymd', strtotime($eventDate. ' + 1 days'));

                //echo consoleMessage("warn", "Date tranformed from ".$output->data->surveyDate." to ".$eventDate);
            }


            // bug discovered in BioCollect : when a date is chosen thrgouh the JS snipet, it's set to 00:00:00. And when save, it's the day before with T22:00:00Z (or 23:00)



            //$year=substr($eventDate, 0, 4);

            $year=getYearFromSurveyDateAndProtocol($eventDate, $protocol);


            if (!isset($arrPerson[$arrActivityDetails[$output->activityId]["personId"]])) {
                 echo consoleMessage("error", "No person for activity ID ".$output->activityId);
            }
            $line["persnr"]=$arrPerson[$arrActivityDetails[$output->activityId]["personId"]];
            if (!isset($array_sites_mongo[$output->data->location])) {
                 echo consoleMessage("error", "No site with ID ".$output->data->location);
            }

            switch ($protocol) {
                case "std":
                    $line["karta"]=$array_sites_mongo[$output->data->location];
                    break;
                case "natt":
                    $line["kartatx"]=$array_sites_mongo[$output->data->location];
                    $line["period"]=$output->data->period;
                    break;
                case "kust":
                    $line["ruta"]=$array_sites_mongo[$output->data->location];
                    break;
                case "iwc":
                    $line["site"]=$array_sites_mongo[$output->data->location];

                    /*
                    // specific for the year in iwc
                    // year +1 if december
                    if (substr($eventDate, 4, 2)==12) $year=substr($eventDate, 0, 4)+1;
                    else $year=substr($eventDate, 0, 4);
                    */
                    break;
                    
                case "vinter":

                    /*
                     // specific for the year in VINTER
                    // year -1 if before june
                    if (substr($eventDate, 4, 2)<6) {
                        $year=substr($eventDate, 0, 4)-1;
                    }
                    else $year=substr($eventDate, 0, 4);

                    // NO BREAK HERE !!
                    */
                case "sommar":
                    $explodeSite=explode("-", $array_sites_mongo[$output->data->location]);
                    if (count($explodeSite)!=3)
                        echo consoleMessage("error", "ERROR - number of elements in internalSiteId ".count($explodeSite)." - ".$array_sites_mongo[$output->data->location]." - LocationId in output is : ".$output->data->location);
                    $line["persnr"]=$explodeSite[0]."-".$explodeSite[1];
                    $line["rnr"]=$explodeSite[2];

                   
                    
                    break;
            }

            //$line["datum"]=substr($arrOutputFromRecord[$output->outputId]["eventDate"], 0, 10);
            $line["datum"]=$eventDate;
            $line["yr"]=$year;

            $line["verificationStatus"]=$arrActivityDetails[$output->activityId]["verificationStatus"];

            switch ($protocol) {
                case "std":

                    // first the header with art='000'
                    $line["art"]="000";
                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["p".$iP]="";
                    }
                    if (property_exists($output->data, "timeOfObservation")) {
                        // convert to array
                        $timeOfObservation = json_decode(json_encode($output->data->timeOfObservation[0]), true);
                        foreach ($timeOfObservation as $field => $val) {
                            $line["p".$field[strlen($field)-1]]=$val;

                        }
                    }

                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["l".$iP]="";
                    }
                    if (property_exists($output->data, "minutesSpentObserving")) {
                        // convert to array
                        $minutesSpentObserving = json_decode(json_encode($output->data->minutesSpentObserving[0]), true);
                        foreach ($minutesSpentObserving as $field => $val) {
                            $line["l".$field[strlen($field)-1]]=$val;

                        }
                    }

                    $line["pkind"]="";
                    $line["lind"]="";

                    break;

                case "natt":
                    // first the header with art='000'
                    $line["art"]="000";

                    $line["kull"]="0";
                    $line["pt"]="P";

                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]="";
                    }
                    if (property_exists($output->data, "timeOfObservation")) {
                        // convert to array
                        $timeOfObservation = json_decode(json_encode($output->data->timeOfObservation[0]), true);
                        foreach ($timeOfObservation as $field => $val) {
                            $line["p".substr($field, strlen($field)-2, 2)]=$val;

                        }
                    }

                    $line["pk"]="";
                    $line["ind"]="";

                    break;

                case "kust":
                    // first the header with art='000'
                    $line["art"]="000";

                    $line["i100m"]="";
                    $line["openw"]="";
                    $line["ind"]="";
                    $line["surveyStartTime"]=$output->data->surveyStartTime;
                    $line["surveyFinishTime"]=$output->data->surveyFinishTime;
                    $line["ducklingsCounted"]=$output->data->ducklingsCounted;
                    $line["ducklingCount"]=$output->data->ducklingCount;
                    $line["ducklingSize"]=$output->data->ducklingSize;

                    break;

                case "iwc":
                    // first the header with art='000'
                    $line["art"]="000";

                    $line["period"]=$output->data->period;
                    $line["metod"]=$output->data->observedFrom;
                    $line["antal"]="";
                    $line["komm"]=$output->data->eventRemarks;

                    break;

                case "sommar":
                case "vinter":
                    // first the header with art='000'
                    $line["art"]="000";

                    if ($protocol=="vinter")
                        $line["period"]=$output->data->period;

                    $transport="";
                    $snow="";
                    if (property_exists($output->data, "transport")) {
                        $transport=$output->data->transport;
                    }
                    if (property_exists($output->data, "snow")) {
                        $snow=$output->data->snow;
                    }
                    

                    $line["p01"]=$transport;
                    $line["p02"]=$snow;
                    $line["p03"]=$output->data->surveyStartTime;
                    $line["p04"]=$output->data->surveyFinishTime;

                    for ($iP=5;$iP<=$nbPts;$iP++) {
                        $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]="";
                    }

                    $line["pk"]="";
                    $line["ind"]="";

                    break;
            }
            $line["activityIdMongo"]=$output->activityId;

            fputcsv($fp, $line, ";");


            switch ($protocol) {
                case "std":

                    // first the header with art='999'
                    $line["art"]="999";

                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["p".$iP]="";
                    }

                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["l".$iP]="";
                    }
                    if (property_exists($output->data, "distanceCovered")) {
                        // convert to array
                        $distanceCovered = json_decode(json_encode($output->data->distanceCovered[0]), true);
                        foreach ($distanceCovered as $field => $val) {
                            $line["l".$field[strlen($field)-1]]=$val;

                        }
                    }

                    $line["pkind"]="";
                    $line["lind"]="";


                    fputcsv($fp, $line, ";");
                    break;

                case "natt":

                    // first the header with art='998'
                    $line["art"]="998";

                    $line["kull"]="0";
                    $line["pt"]="P";

                    for ($iP=1;$iP<=$nbPts;$iP++) {
                        $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]="";
                    }

                    $line["p01"]=$output->data->cloudsStart;
                    $line["p02"]=$output->data->temperatureStart;
                    $line["p03"]=$output->data->windStart;
                    $line["p04"]=$output->data->precipitationStart;
                    $line["p05"]=$output->data->cloudsEnd;
                    $line["p06"]=$output->data->temperatureEnd;
                    $line["p07"]=$output->data->windEnd;
                    $line["p08"]=$output->data->precipitationEnd;

                    $line["pk"]="";
                    $line["ind"]="";


                    fputcsv($fp, $line, ";");

                    break;


            }


            // then all the observations
            foreach ($commonFields["listSpeciesId"] as $animals => $listId) {

                switch($animals) {
                    case "birds":
                        $tabData=$output->data->observations;
                        break;
                    case "amphibians":
                        if (property_exists($output->data, "amphibianObservations"))
                            $tabData=$output->data->amphibianObservations;
                        else $tabData=array();
                        break;
                    case "mammals":
                        if ($protocol!="kust") {
                            if (property_exists($output->data, "mammalObservations")) {
                            $tabData=$output->data->mammalObservations;
                            }
                            else $tabData=array();
                        }
                        else {
                            $tabData=array();
                                
                            if (isset($output->data->mammalObservations[0]) && $output->data->mammalObservations[0]!="nej") {
                                $tabMammals=$output->data->mammalObservations;

                                $line["art"]="";
                                $line["i100m"]="";
                                $line["openw"]="";
                                $line["ind"]=1;

                                foreach ($tabMammals as $mam) {
                                    switch ($mam) {
                                        case "mink":
                                            $line["art"]="714";break;
                                        case "grävling":
                                            $line["art"]="719";break;
                                        case "rödräv":
                                            $line["art"]="709";break;
                                    }
                                    fputcsv($fp, $line, ";");
                                }


                            }
                        }
                        
                        break;
                    case "mammalsOnRoad":
                        if (property_exists($output->data, "mammalObservationsOnRoad")) {
                            $tabData=$output->data->mammalObservationsOnRoad;
                        }
                        else $tabData=array();
                        break;
                    case "owls":
                        if (property_exists($output->data, "youngOwlObservations"))
                            $tabData=$output->data->youngOwlObservations;
                        else $tabData=array();
                        break;
                }

                foreach ($tabData as $obs) {

                    switch($animals) {
                        case "birds":
                            if (!isset($obs->species->guid) || !isset($array_species_art[$animals][$obs->species->guid])) {
                                if (!isset($obs->species->scientificName) || !isset($array_species_art[$animals][$obs->species->scientificName])) {
                                    $art="ERROR";
                                    //var_dump($obs);

                                    echo consoleMessage("error", "No ART for ".$animals." / ".$obs->species->guid." / ".$obs->species->scientificName);
                                }
                                else {
                                    $art=str_pad($array_species_art[$animals][$obs->species->scientificName], 3, "0", STR_PAD_LEFT);
                                    //echo $obs->species->scientificName." => ".$art."\n";
                                }
                                
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->species->guid], 3, "0", STR_PAD_LEFT);
                            }

                            break;
                        case "amphibians":
                            if (!isset($obs->speciesAmphibians->guid) || !isset($array_species_art[$animals][$obs->speciesAmphibians->guid])) {
                                $art="ERROR";
                                echo consoleMessage("error", "No ART for ".$animals." / ".$obs->speciesAmphibians->guid);
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->speciesAmphibians->guid], 3, "0", STR_PAD_LEFT);
                            }
                            break;
                        case "mammals":
                            if (!isset($obs->speciesMammals->guid) || !isset($array_species_art[$animals][$obs->speciesMammals->guid])) {
                                $art="ERROR";
                                echo consoleMessage("error", "No ART for  ".$animals." / ".$obs->speciesMammals->guid);
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->speciesMammals->guid], 3, "0", STR_PAD_LEFT);
                            }
                            break;
                        case "mammalsOnRoad":
                            if (!isset($obs->speciesMammalsOnRoad->guid) || !isset($array_species_art[$animals][$obs->speciesMammalsOnRoad->guid])) {
                                $art="ERROR";
                                echo consoleMessage("error", "No ART for  ".$animals." / ".$obs->speciesMammalsOnRoad->guid);
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->speciesMammalsOnRoad->guid], 3, "0", STR_PAD_LEFT);
                            }
                            break;
                        case "owls":
                            if (!isset($obs->speciesYoungOwl->guid) || !isset($array_species_art[$animals][$obs->speciesYoungOwl->guid])) {
                                $art="ERROR";
                                echo consoleMessage("error", "No ART for  ".$animals." / ".$obs->speciesYoungOwl->guid);
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->speciesYoungOwl->guid], 3, "0", STR_PAD_LEFT);
                            }
                            break;
                    }

                    $line["art"]=$art;

                    switch ($protocol) {
                        case "std":
                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["p".$iP]="";
                            }  
                            if (isset($obs->P01)) $line["p1"]=trim($obs->P01);
                            if (isset($obs->P02)) $line["p2"]=trim($obs->P02);
                            if (isset($obs->P03)) $line["p3"]=trim($obs->P03);
                            if (isset($obs->P04)) $line["p4"]=trim($obs->P04);
                            if (isset($obs->P05)) $line["p5"]=trim($obs->P05);
                            if (isset($obs->P06)) $line["p6"]=trim($obs->P06);
                            if (isset($obs->P07)) $line["p7"]=trim($obs->P07);
                            if (isset($obs->P08)) $line["p8"]=trim($obs->P08);

                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["l".$iP]="";
                            }  
                            if (isset($obs->L01)) $line["l1"]=trim($obs->L01);
                            if (isset($obs->L02)) $line["l2"]=trim($obs->L02);
                            if (isset($obs->L03)) $line["l3"]=trim($obs->L03);
                            if (isset($obs->L04)) $line["l4"]=trim($obs->L04);
                            if (isset($obs->L05)) $line["l5"]=trim($obs->L05);
                            if (isset($obs->L06)) $line["l6"]=trim($obs->L06);
                            if (isset($obs->L07)) $line["l7"]=trim($obs->L07);
                            if (isset($obs->L08)) $line["l8"]=trim($obs->L08);

                            $line["pkind"]=0;
                            $line["lind"]=0;
                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["pkind"]+=$line["p".$iP];
                                $line["lind"]+=$line["l".$iP];
                            }  

                            break;

                        case "natt":

                            if ($animals=="owls") $line["kull"]="k"; 
                            else $line["kull"]="0";

                            if ($animals=="mammalsOnRoad") $line["pt"]="T"; 
                            else $line["pt"]="P";
                            

                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]="";
                            }  
                            if (isset($obs->P01)) $line["p01"]=trim($obs->P01);
                            if (isset($obs->P02)) $line["p02"]=trim($obs->P02);
                            if (isset($obs->P03)) $line["p03"]=trim($obs->P03);
                            if (isset($obs->P04)) $line["p04"]=trim($obs->P04);
                            if (isset($obs->P05)) $line["p05"]=trim($obs->P05);
                            if (isset($obs->P06)) $line["p06"]=trim($obs->P06);
                            if (isset($obs->P07)) $line["p07"]=trim($obs->P07);
                            if (isset($obs->P08)) $line["p08"]=trim($obs->P08);
                            if (isset($obs->P09)) $line["p09"]=trim($obs->P09);
                            if (isset($obs->P10)) $line["p10"]=trim($obs->P10);
                            if (isset($obs->P11)) $line["p11"]=trim($obs->P11);
                            if (isset($obs->P12)) $line["p12"]=trim($obs->P12);
                            if (isset($obs->P13)) $line["p13"]=trim($obs->P13);
                            if (isset($obs->P14)) $line["p14"]=trim($obs->P14);
                            if (isset($obs->P15)) $line["p15"]=trim($obs->P15);
                            if (isset($obs->P16)) $line["p16"]=trim($obs->P16);
                            if (isset($obs->P17)) $line["p17"]=trim($obs->P17);
                            if (isset($obs->P18)) $line["p18"]=trim($obs->P18);
                            if (isset($obs->P19)) $line["p19"]=trim($obs->P19);
                            if (isset($obs->P20)) $line["p20"]=trim($obs->P20);

                            $line["pk"]=0;
                            $line["ind"]=0;
                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                if ($line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]!="" && $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]>0) $line["pk"]++;
                                $line["ind"]+=intval($line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]);
                            }  

                            break;

                        case "kust":   

                            $line["i100m"]="";
                            $line["openw"]="";
                            $line["ind"]="";
                            $line["surveyStartTime"]="";
                            $line["surveyFinishTime"]="";
                            $line["ducklingsCounted"]="";
                            $line["ducklingCount"]="";
                            $line["ducklingSize"]="";

                            if (isset($obs->island)) $line["i100m"]=$obs->island;
                            if (isset($obs->water)) $line["openw"]=$obs->water;
                            if (isset($obs->individualCount)) $line["ind"]=trim($obs->individualCount);


                            break;

                        case "iwc":   

                            
                            $line["period"]=$output->data->period;
                            $line["metod"]=$output->data->observedFrom;
                            $line["antal"]=$obs->individualCount;
                            $line["komm"]="";
                            break;

                        case "sommar":
                        case "vinter":
                            
                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]="";
                            }  
                            if (isset($obs->P01)) $line["p01"]=trim($obs->P01);
                            if (isset($obs->P02)) $line["p02"]=trim($obs->P02);
                            if (isset($obs->P03)) $line["p03"]=trim($obs->P03);
                            if (isset($obs->P04)) $line["p04"]=trim($obs->P04);
                            if (isset($obs->P05)) $line["p05"]=trim($obs->P05);
                            if (isset($obs->P06)) $line["p06"]=trim($obs->P06);
                            if (isset($obs->P07)) $line["p07"]=trim($obs->P07);
                            if (isset($obs->P08)) $line["p08"]=trim($obs->P08);
                            if (isset($obs->P09)) $line["p09"]=trim($obs->P09);
                            if (isset($obs->P10)) $line["p10"]=trim($obs->P10);
                            if (isset($obs->P11)) $line["p11"]=trim($obs->P11);
                            if (isset($obs->P12)) $line["p12"]=trim($obs->P12);
                            if (isset($obs->P13)) $line["p13"]=trim($obs->P13);
                            if (isset($obs->P14)) $line["p14"]=trim($obs->P14);
                            if (isset($obs->P15)) $line["p15"]=trim($obs->P15);
                            if (isset($obs->P16)) $line["p16"]=trim($obs->P16);
                            if (isset($obs->P17)) $line["p17"]=trim($obs->P17);
                            if (isset($obs->P18)) $line["p18"]=trim($obs->P18);
                            if (isset($obs->P19)) $line["p19"]=trim($obs->P19);
                            if (isset($obs->P20)) $line["p20"]=trim($obs->P20);

                            $line["pk"]=0;
                            $line["ind"]=0;

                            if (isset($obs->pk)) $line["pk"]=trim($obs->pk);
                            if (isset($obs->individualCount)) $line["ind"]=$obs->individualCount;

                            break;
                    }

                    $line["activityIdMongo"]="";

                    fputcsv($fp, $line, ";");
                }
            }
            

            $nbR++;
            if (isset($limitFiles) && $nbR==$limitFiles) break;
        }

        echo consoleMessage("info", "file created successfully ".$path_extract);
        fclose($fp);

    }
    else {
        echo consoleMessage("error", "can't write in ".$path_extract);
    }


}
?>