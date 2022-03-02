<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/editRecordsAddSwedishRank.php protocol [debug]");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$array_species_sn=array();
$array_species_guid=array();

$debug=false;

$arr_protocol=array("std", "natt", "vinter", "sommar", "kust", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
    echo consoleMessage("error", "First parameter missing: std / natt / vinter / sommar / kust / iwc");
    exit();
}
$protocol=$argv[1];

if (isset($argv[2]) && $argv[2]=="debug") $debug=true;

foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
    $url="https://lists.biodiversitydata.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true";
    $obj = json_decode(file_get_contents($url), true);

    foreach($obj as $sp) {
        
        $array_species_sn[$animals][]=$sp["name"];


        foreach ($sp["kvpValues"] as $iKvp => $kvp) {
            if (isset($kvp["key"]) && $kvp["key"]=="rank") {
                $rank=$kvp["value"];
                $array_species_rank[$animals][$rank]=$sp["name"];
            }   

        }

    }

    echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"][$animals]." obtained. ".count($obj)." elements");

}
//print_r($array_species_sn["birds"]);
//echo $array_species_rank["birds"][3090]; exit;

// get all the sites
$filter = ["status"=>"active", "name" => $commonFields[$protocol]["name"], "verificationStatus" => ['$ne' => "draft"]];
//$filter = ["dataOrigin" => "scriptSitePunktIntranet", "verificationStatus"=>"godkänd", "status"=>"active"];
$options = [];
/*$options = [
   'limit' => 100,
]; */
$query = new MongoDB\Driver\Query($filter, $options); 

$rows = $mng->executeQuery("ecodata.output", $query);

$nbSNok=0;
$nbSNfixed=0;
$nbSNerror=0;
$nbSNunknown=0;
$nbActivitiesError=0;

foreach ($rows as $row){

    $animals="birds";
    if (isset($row->data->observations)) {

        $dateCreated="";
        foreach($row->data->observations as $obs) {

            if (in_array($obs->species->scientificName, $array_species_sn[$animals] )) {
                $nbSNok++;
            }
            else {
                //echo consoleMessage("error", "Can't find SN ".$obs->species->scientificName." from activity ".$row->activityId." in array");
                $nbSNerror++;
                $nbSNunknown++;

                $dateCreated=$row->data->surveyDate;

                if (isset($obs->swedishRank) && isset($array_species_rank[$animals][intval($obs->swedishRank)]) && trim($array_species_rank[$animals][intval($obs->swedishRank)])!="") {
                    $nbSNfixed++;
                    //echo consoleMessage("warn", $obs->species->scientificName." replaced by ".$array_species_rank[$animals][intval($obs->swedishRank)]);

                }
                else {
                    if (!isset($obs->swedishRank)) {
                        echo consoleMessage("error", "No field swedishRank for ".$obs->species->name.", activity : ".$row->activityId);

                    }
                    elseif (intval($obs->swedishRank)==0) {
                        echo consoleMessage("error", "swedishRank is 0 for ".$obs->species->name.", activity : ".$row->activityId);

                    }
                    else 
                        echo consoleMessage("error", "Can't fix with rank ".intval($obs->swedishRank)." ".$array_species_rank[$animals][intval($obs->swedishRank)]." name : ".$obs->species->name." activity : ".$row->activityId);
                }
            }
                
        }
        if ($dateCreated!="") {
            if ($debug) echo consoleMessage("warn", "Problem with activity ".$row->activityId." created ".$dateCreated);
            $nbActivitiesError++;
        }
    }
    else {
        echo consoleMessage("warn", "No observations  for ".$row->activityId);
    }
}

echo consoleMessage("info", $nbSNok." ok scientificNames.");
echo consoleMessage("info", $nbSNerror." error scientificNames.");
echo consoleMessage("info", $nbSNfixed." fixed scientificNames.");
echo consoleMessage("info", "including ".$nbSNunknown." unknown scientificNames.");

echo consoleMessage("info", $nbActivitiesError." activity error scientificNames.");

?>