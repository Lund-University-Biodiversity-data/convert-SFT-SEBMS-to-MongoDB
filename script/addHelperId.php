<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/addHelperId.php kust");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

$arr_protocol=array("kust", "iwc");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

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

				if (count($row->data->helpers)>1)
				echo "go helpers".count($row->data->helpers)."\n";
				
				if (count($row->data->helpers)>0) {


					$helperId=array();
					$okAddHelperId=true;

					foreach($row->data->helpers as $help) {

						if (!isset($arrPersons[$help->helper])) {
							$personName=explode(" ", $help->helper);

							if (isset($personName[0]) && isset($personName[1]) && count($personName)==2) {
								//echo implode("/", $personName)."\n";
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
									$okAddHelperId=false;
									echo consoleMessage("warn", "Did not find 1 person with that name (".$nbPers." instead) : ".$help->helper);

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
							echo consoleMessage("info", "Person already found (".$help->helper.") => ".$arrPersons[$help->helper]);
							$helperId[]=$arrPersons[$help->helper];
						}
						
					}

					if ($okAddHelperId) {
						if (count($helperId)>1)
							echo consoleMessage("info", "Add personId (".implode("/", $helperId).")");
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
