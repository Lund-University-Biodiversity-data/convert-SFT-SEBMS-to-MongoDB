<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/editRecordsAddSwedishRank.php");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
	$url="https://lists.biodiversitydata.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true";
	$obj = json_decode(file_get_contents($url), true);

	foreach($obj as $sp) {
		
		$key="";
		$dyntaxa="";
		$rank="";
		$lsid="";


		if(isset($sp["lsid"])) {
			$lsid=$sp["lsid"];
			$key=$lsid;
		}

		foreach ($sp["kvpValues"] as $iKvp => $kvp) {
			if ($kvp["key"]=="dyntaxa_id" && isset($kvp["value"]) && is_numeric($kvp["value"])) {
				$dyntaxa=$kvp["value"];
			}

			if (isset($kvp["key"]) && $kvp["key"]=="rank") {
				$rank=$kvp["value"];
			}	

		}

		if ($lsid=="") {
			if ($dyntaxa!="") {
				$key=$dyntaxa;
				echo consoleMessage("info", "No lsid for ".$sp["name"].", dyntaxa instead");
			}
			else echo consoleMessage("error", "No lsid/dyntaxa for ".$sp["name"]);
		}

		if ($key=="") {
			echo consoleMessage("error", "No species info for ".$sp["name"]);

		}
		else {
			if (isset($rank) && trim($rank)!="")
			$array_species_guid[$animals][$key]["rank"]=$rank;
		}
		

	}

	echo consoleMessage("info", "Species list ".$commonFields["listSpeciesId"][$animals]." obtained. ".count($obj)." elements");
//exit();
//print_r($array_species_guid[$animals]);
}

//exit();


// get all the sites
$filter = ["status"=>"active", "data.observations.swedishRank" => ['$exists'=> false]];
//$filter = ["dataOrigin" => "scriptSitePunktIntranet", "verificationStatus"=>"godkänd", "status"=>"active"];
$options = [];
$options = [
/*   'limit' => 1000, */
];  
$query = new MongoDB\Driver\Query($filter, $options); 

$rows = $mng->executeQuery("ecodata.output", $query);

$nbSRmiss=0;
$nbSRadd=0;
$nbSRok=0;
$nbEmptyObs=0;

foreach ($rows as $row){
	$animal="birds";
	if (isset($row->data->observations) && count($row->data->observations)!=0) {
    	foreach($row->data->observations as $obs) {
    		if (!isset($obs->swedishRank)) {
    			//echo "pas de swedishRank pour activityId#".$row->activityId."\n";
    			$nbSRmiss++;

    			if (isset($obs->species->guid) && trim($obs->species->guid)!="") {
	    			if (isset($array_species_guid[$animal][$obs->species->guid]) && trim($array_species_guid[$animal][$obs->species->guid]["rank"])!="") {
	    				//echo $row->activityId."\n";

	    				$bulk = new MongoDB\Driver\BulkWrite;
						//$filter = [];
						$filter = ['activityId' => $row->activityId, "data.observations.species.guid" => $obs->species->guid];
						//print_r($filter);
						$options =  ['$set' => ['data.observations.$.swedishRank' => $array_species_guid[$animal][$obs->species->guid]["rank"]]];
						$updateOptions = [];
						$bulk->update($filter, $options, $updateOptions); 
						$result = $mng->executeBulkWrite('ecodata.output', $bulk);

					    $nbSRadd++;

						//echo consoleMessage("info", "Activity $row->activityId edited  with swedishRank ".$array_species_guid[$animal][$obs->species->guid]["rank"]." for ".$obs->species->scientificName);
	    			}
	    			else {
	    				echo consoleMessage("error", "No species info for activityId#".$row->activityId." and ".$obs->species->scientificName."/guid#:".$obs->species->guid." => maybe the guid changed ?");
	    			}
    			}
	    		else {
	    			echo consoleMessage("error", "No guid for activityId#".$row->activityId." and ".$obs->species->scientificName." (list: ".$obs->species->listId.")");
	    		}
    		}
    		else {
    			$nbSRok++;
    		}
    	}
	}
	else {
		//echo consoleMessage("warn", "No observations  for ".$row->activityId);
		$nbEmptyObs++;
	}
}

echo consoleMessage("info", $nbSRmiss." missing swedishRank.");
echo consoleMessage("info", $nbEmptyObs." empty obs.");
echo consoleMessage("info", $nbSRadd." added swedishRank.");
echo consoleMessage("info", $nbSRok." ok swedishRank.");

?>
