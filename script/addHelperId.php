<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/addHelperId.php kust");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$arr_protocol=array("kust", "iwc", "natt");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {


	$manualPersonProject["kust"]["ERIK ANDERSSON"]="868608f0-1b10-2135-5e3d-9f5b94355edd" ; // 450605-1
	$manualPersonProject["kust"]["JAN KARLSSON"]="b4522b0c-2e92-2337-e951-9367c4f1058d"; // 511229-1
	$manualPersonProject["kust"]["STIG LARSSON"]="5d43da9d-d5fd-e0b3-47a1-4f0f738d5af6"; // 480815-1 
	$manualPersonProject["kust"]["ÅSA LUNDBERG"]="3e9adb4d-855a-2a0e-fc23-7f99b702198b"; // 480516-3
	// For Åsa L => one is supposed to be fixed to 470516-2 // d9e6f947-c068-88fb-5eeb-7569309215b9 // AB3548 // 2016
	$manualPersonProject["kust"]["JÖRGEN ANDERSSON"]="f4da33c3-508c-246a-80dc-6700416c9684"; // 650423-1
	$manualPersonProject["kust"]["ÅKE ANDERSSON"]="d46c6ba7-d7f9-48b9-7c4c-c4e4eec894e0"; // 380703-2


	$protocol=$argv[1];
	/*
	// get all the sites
	$filter = ["status"=>"active", "data.helpers.helper" => ['$exists' => true ] ];
	$options = [];
	$options = [
	   'limit' => 10,
	];  
	$query = new MongoDB\Driver\Query($filter, $options); 

	$rows = $mng->executeQuery("ecodata.output", $query);
	*/

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
	           'status'=> 'active',
	           'act.projectId'=> $commonFields[$protocol]["projectId"],
	           'data.helpers.helper' => [ '$exists' => true ],
	       ]],
	       ['$project'=> [
	           "activityId" => 1,
	           "outputId" => 1,
	           "data.helpers" => 1,
	           "act.helperId" => 1,
	       ]],
	       /*['$limit' => 10]*/
	   ],
	   'cursor' => new stdClass,
	];

	try{
      $command = new \MongoDB\Driver\Command($aggregate);

      $rt = $mng->executeCommand(MONGODB_NAME, $command);
      $rtArray=$rt->toArray();

      $arrPersons=array();

		$nbHmiss=0;
		$nbHadd=0;
		$nbHok=0;

		echo consoleMessage("info", count($rtArray)." row(s) to check");

		foreach ($rtArray as $row){

			if (!isset($row->act[0]->helperId)) {

				//if (count($row->data->helpers)>2)
				//echo "go helpers".count($row->data->helpers)." => ".$row->activityId."\n";
				
				if (count($row->data->helpers)>0) {


					$helperId=array();
					$okAddHelperId=true;

					foreach($row->data->helpers as $help) {

						if (!isset($arrPersons[$help->helper])) {
							$personName=explode(" ", $help->helper, 2);

							if (isset($personName[0]) && isset($personName[1]) && count($personName)==2) {
								//echo implode("/", $personName)."\n";

								// first try with BIG LETTERS
								$filter = ["firstName"=>mb_strtoupper($personName[0]), "lastName"=>mb_strtoupper($personName[1])];
								$options = [];
								$query = new MongoDB\Driver\Query($filter, $options); 
								$rowsP = $mng->executeQuery("ecodata.person", $query);

								$nbPers=0;
								foreach($rowsP as $person) {
									//echo "validé pour le personId".$person->personId."\n";
									$nbPers++;
									$persId=$person->personId;
								}

								if ($nbPers!=1) {

									// 2nd try with SMALL LETTERS
									$filter = ["firstName"=>($personName[0]), "lastName"=>($personName[1])];
									$options = [];
									$query = new MongoDB\Driver\Query($filter, $options); 
									$rowsP = $mng->executeQuery("ecodata.person", $query);

									$nbPers=0;
									foreach($rowsP as $person) {
										//echo "validé pour le personId".$person->personId."\n";
										$nbPers++;
										$persId=$person->personId;
									}

									if ($nbPers!=1) {


										// NEW ROUND BUT WITH THE MANUAL personId
										if (isset($manualPersonProject[$protocol][$help->helper])) {
											echo consoleMessage("info", "manual fix for ".$help->helper."");
											$helperId[]=$manualPersonProject[$protocol][$help->helper];
											$arrPersons[$help->helper]=$manualPersonProject[$protocol][$help->helper];
										}
										else {

											$okAddHelperId=false;
											echo consoleMessage("warn", "Did not find 1 person with that name (".$nbPers." instead) : ".$help->helper. " activityId => ".$row->activityId);
										}

									} else {
										$helperId[]=$persId;
										$arrPersons[$help->helper]=$persId;
									}

								}
								else {
									$helperId[]=$persId;
									$arrPersons[$help->helper]=$persId;
								}
								/*}
								else {
									$okAddHelperId=false;
								}*/
								
							}
							else {

								echo consoleMessage("warn", "Impossible to explode personName (".count($personName).") => ".$help->helper);
								$okAddHelperId=false;

							}
						}
						else {
							//echo consoleMessage("info", "Person already found (".$help->helper.") => ".$arrPersons[$help->helper]);
							$helperId[]=$arrPersons[$help->helper];
						}
						
					}

					if ($okAddHelperId && count($helperId)>0) {
						//if (count($helperId)>1)
							//echo consoleMessage("info", "Add personId (".implode("/", $helperId).")");
						$nbHadd++;
						/*
						$bulk = new MongoDB\Driver\BulkWrite;
						//$filter = [];
						$filter = ['activityId' => $row->activityId, "data.observations.species.guid" => $obs->species->guid];
						//print_r($filter);
						$options =  ['$set' => ['data.observations.$.swedishRank' => $array_species_guid[$animal][$obs->species->guid]["rank"]]];
						$updateOptions = [];
						$bulk->update($filter, $options, $updateOptions); 
						$result = $mng->executeBulkWrite('ecodata.output', $bulk);
						*/
					}
					else {
						$nbHmiss++;
					}
				}
				
			}
			else {
				var_dump($row->act[0]->helperId);
				echo consoleMessage("info", "Already helperIds ".$row->activityId);
				$nbSRok++;
			}
		}

		echo consoleMessage("info", $nbHmiss." missing output with helperId.");
		echo consoleMessage("info", $nbHadd." added helperId.");
		echo consoleMessage("info", $nbHok." ok helperId.");

  } catch(\Exception $e){
     echo "Exception:", $e->getMessage(), "\n";

  }



}
?>
