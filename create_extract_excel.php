<?php
$database="SFT";
$dataOrigin="scriptExcel";

define ("SCRIPT_PATH", "/home/mathieu/Documents/repos/convert-SFT-SEBMS-to-MongoDB/");

require SCRIPT_PATH."lib/config.php";
require SCRIPT_PATH."lib/functions.php";

//require_once "lib/PHPExcel/Classes/PHPExcel.php";


require SCRIPT_PATH.'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php create_extract_excel.php std 2 debug");

$debug=false;

// parameters
// 1- protocol: std (standardrutterna) - natt (nattrutterna) - vinter (vinterrutterna) - sommar (sommarrutterna) - kust (kustfagelrutterna)
$arr_protocol=array("std", "natt", "vinter", "sommar", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust");
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
        $url="https://lists.bioatlas.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true";
        $obj = json_decode(file_get_contents($url), true);

        foreach($obj as $sp) {

            if (trim($sp["lsid"])=="") echo consoleMessage("error", "No lsid/guid for ".$sp["name"]);

            if (isset($sp["kvpValues"][0]["key"]) && $sp["kvpValues"][0]["key"]=="art") {
                $art=$sp["kvpValues"][0]["value"];
                $array_species_art[$animals][$sp["lsid"]]=$art;
            }
            else {
                echo consoleMessage("error", "No ART for ".$sp["name"]);
            }

        }
        echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"][$animals]." obtained for ".$animals.". ".count($obj)." elements");
    }


	switch($protocol) {
		case "std":
			$nbPts=8;

            $headers=array(/*"kart_id", */"persnr", "karta", "datum", "yr", "art", "p1", "p2", "p3", "p4", "p5", "p6", "p7", "p8", "l1", "l2", "l3", "l4", "l5", "l6", "l7", "l8", "pkind", "lind", "locationIdMongo", "outputIdMongo");

			break;
		case "vinter":
			$nbPts=20;
			break;
		case "natt":
			$nbPts=20;

            $headers=array("persnr", "kartatx", "per", "datum", "yr", "art", "kull", "pt", "p01", "p02", "p03", "p04", "p05", "p06", "p07", "p08", "p09", "p10", "p11", "p12", "p13", "p14", "p15", "p16", "p17", "p18", "p19", "p20", "pk", "ind", "locationIdMongo", "outputIdMongo");

            $commonFields["listSpeciesId"]["mammalsOnRoad"]=$commonFields["listSpeciesId"]["mammals"];
            $array_species_art["mammalsOnRoad"]=$array_species_art["mammals"];

			break;
		case "kust":

            $headers=array("persnr", "ruta", "datum", "yr", "art", "i100m", "openw", "ind", "temp");
			$nbPts=1;
			break;
	}

	$array_sites=array();
	$array_sites_mongo=array();

	$array_sites=getArraySitesFromMongo($protocol, $commonFields[$protocol]["projectId"]);

	foreach($array_sites as $indexSite => $data) {
		$array_sites_mongo[$data["locationID"]]=$indexSite;
	}

    echo consoleMessage("info", count($array_sites)." site(s) for the project ".$commonFields[$protocol]["projectId"]);

//print_r($array_sites_mongo);

	/**************************** connection to mongoDB   ***/
    $mng = new MongoDB\Driver\Manager($mongoConnection["url"]); // Driver Object created

    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");
    
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
        else  echo consoleMessage("error", "No internalPersonId for ".$row->firstName.$row->lastName);
        
    }

    $filter = ['projectId' => $commonFields[$protocol]["projectId"], "status" => "active", "verificationStatus" => "approved"];
    //$filter = [];
    $options = [];
/*    $options = [
       'limit' => $limitFiles,
    ];    */
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


    $filter = ['projectId' => $commonFields[$protocol]["projectId"], "status" => "active"];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 
    $rowsOutput = $mng->executeQuery("ecodata.record", $query);

    $rowsToArrayRecord=$rowsOutput->toArray();
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

        fputcsv($fp, $headers);

        $nbR=0;
        foreach ($rowsToArrayOutput as $output) {

            $line=array();

            if (!isset($arrOutputFromRecord[$output->outputId])) {
                if ($debug) echo consoleMessage("warn", "OUTPUT ID ".$output->outputId." does not exist in records array => no records");
            }
            
            $eventDate=$output->data->surveyDate;
            $year=substr($eventDate, 0, 4);
            /*
            if (isset($arrOutputFromRecord[$output->outputId]["eventDate"])) {
                $year=substr($arrOutputFromRecord[$output->outputId]["eventDate"], 0, 4);
            }
            else $year="";
            */

            //$line["kart_id"]="";

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
            }

            //$line["datum"]=substr($arrOutputFromRecord[$output->outputId]["eventDate"], 0, 10);
            $line["datum"]=$eventDate;
            $line["yr"]=$year;

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

                    break;
            }
            $line["locationIdMongo"]=$output->data->location;
            $line["outputIdMongo"]=$output->outputId;
            $line["activityIdMongo"]=$output->activityId;

            fputcsv($fp, $line);


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

                    $line["locationIdMongo"]=$output->data->location;
                    $line["outputIdMongo"]=$output->outputId;

                    fputcsv($fp, $line);
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

                    $line["locationIdMongo"]=$output->data->location;
                    $line["outputIdMongo"]=$output->outputId;

                    fputcsv($fp, $line);

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
                                    fputcsv($fp, $line);
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
                                $art="ERROR";
                                var_dump($obs);

                                echo consoleMessage("error", "No ART for  ".$animals." / ".$obs->species->guid);
                            }
                            else {
                                $art=str_pad($array_species_art[$animals][$obs->species->guid], 3, "0", STR_PAD_LEFT);
                            }

                            break;
                        case "amphibians":
                            if (!isset($obs->speciesAmphibians->guid) || !isset($array_species_art[$animals][$obs->speciesAmphibians->guid])) {
                                $art="ERROR";
                                echo consoleMessage("error", "No ART for  ".$animals." / ".$obs->speciesAmphibians->guid);
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
                            if (isset($obs->P01)) $line["p1"]=$obs->P01;
                            if (isset($obs->P02)) $line["p2"]=$obs->P02;
                            if (isset($obs->P03)) $line["p3"]=$obs->P03;
                            if (isset($obs->P04)) $line["p4"]=$obs->P04;
                            if (isset($obs->P05)) $line["p5"]=$obs->P05;
                            if (isset($obs->P06)) $line["p6"]=$obs->P06;
                            if (isset($obs->P07)) $line["p7"]=$obs->P07;
                            if (isset($obs->P08)) $line["p8"]=$obs->P08;

                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                $line["l".$iP]="";
                            }  
                            if (isset($obs->L01)) $line["l1"]=$obs->L01;
                            if (isset($obs->L02)) $line["l2"]=$obs->L02;
                            if (isset($obs->L03)) $line["l3"]=$obs->L03;
                            if (isset($obs->L04)) $line["l4"]=$obs->L04;
                            if (isset($obs->L05)) $line["l5"]=$obs->L05;
                            if (isset($obs->L06)) $line["l6"]=$obs->L06;
                            if (isset($obs->L07)) $line["l7"]=$obs->L07;
                            if (isset($obs->L08)) $line["l8"]=$obs->L08;

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
                            if (isset($obs->P01)) $line["p01"]=$obs->P01;
                            if (isset($obs->P02)) $line["p02"]=$obs->P02;
                            if (isset($obs->P03)) $line["p03"]=$obs->P03;
                            if (isset($obs->P04)) $line["p04"]=$obs->P04;
                            if (isset($obs->P05)) $line["p05"]=$obs->P05;
                            if (isset($obs->P06)) $line["p06"]=$obs->P06;
                            if (isset($obs->P07)) $line["p07"]=$obs->P07;
                            if (isset($obs->P08)) $line["p08"]=$obs->P08;
                            if (isset($obs->P09)) $line["p09"]=$obs->P09;
                            if (isset($obs->P10)) $line["p10"]=$obs->P10;
                            if (isset($obs->P11)) $line["p11"]=$obs->P11;
                            if (isset($obs->P12)) $line["p12"]=$obs->P12;
                            if (isset($obs->P13)) $line["p13"]=$obs->P13;
                            if (isset($obs->P14)) $line["p14"]=$obs->P14;
                            if (isset($obs->P15)) $line["p15"]=$obs->P15;
                            if (isset($obs->P16)) $line["p16"]=$obs->P16;
                            if (isset($obs->P17)) $line["p17"]=$obs->P17;
                            if (isset($obs->P18)) $line["p18"]=$obs->P18;
                            if (isset($obs->P19)) $line["p19"]=$obs->P19;
                            if (isset($obs->P20)) $line["p20"]=$obs->P20;

                            $line["pk"]=0;
                            $line["ind"]=0;
                            for ($iP=1;$iP<=$nbPts;$iP++) {
                                if ($line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]!="" && $line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)]>0) $line["pk"]++;
                                $line["ind"]+=$line["p".str_pad($iP, 2, '0', STR_PAD_LEFT)];
                            }  

                            break;

                        case "kust":   

                            $line["i100m"]="";
                            $line["openw"]="";
                            $line["ind"]="";
                            
                            if (isset($obs->island[0])) $line["i100m"]=$obs->island[0];
                            if (isset($obs->water[0])) $line["openw"]=$obs->water[0];
                            if (isset($obs->individualCount)) $line["ind"]=$obs->individualCount;
                            
                            

                            break;
                    }

                    $line["locationIdMongo"]=$output->data->location;
                    $line["outputIdMongo"]=$output->outputId;

                    fputcsv($fp, $line);
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