<?php
$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";

echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/addHelperId.php kust [exec]");

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

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
           'act.verificationStatus' => "approved",
           'act.projectId'=> $commonFields["kust"]["projectId"]
       ]],
       ['$project'=> [
           "activityId" => 1,
           "outputId" => 1,
           "data.observations" => 1,
       ]],
       /*['$limit' => 10]*/
   ],
   'cursor' => new stdClass,
];

try{
   $command = new \MongoDB\Driver\Command($aggregate);

   $rt = $mng->executeCommand(MONGODB_NAME, $command);
   $rtArray=$rt->toArray();

	echo consoleMessage("info", count($rtArray)." row(s) to check");

	$nbErrors=0;
	$nbOk=0;

	foreach ($rtArray as $row){

		foreach ($row->data->observations as $obs) {

			if (isset($obs->water) && intval($obs->water)>0 && isset($obs->island) && intval($obs->island)>0 ) {
				$water=0;
				$island=0;
				$ic=0;
				if (isset($obs->water)) $water=$obs->water;
				if (isset($obs->island)) $island=$obs->island;
				if (isset($obs->individualCount)) $ic=$obs->individualCount;

				if ($water+$island != $ic) {
					echo consoleMessage("error", $row->activityId." : water-island-ic => ".$water."-".$island."-".$ic);
					$nbErrors++;
				}
				else {
					$nbOk++;
				}
			}
		}
	}

	echo consoleMessage("info", $nbErrors." error(s) found. ".number_format(($nbErrors*100)/($nbOk+$nbErrors), 2)."%");

/*

				if ($okAddHelperId && count($helperId)>0) {
					//if (count($helperId)>1)
					echo consoleMessage("info", "Add personId (".implode("/", $helperId).") => ".$row->activityId);
					$nbHadd++;

					if (isset($argv[2]) && $argv[2]=="exec") {
						$bulk = new MongoDB\Driver\BulkWrite;
						//$filter = [];
						$filter = ['activityId' => $row->activityId];
						//print_r($filter);
						$options =  ['$set' => ['helperIds' => $helperId]];
						$updateOptions = [];
						$bulk->update($filter, $options, $updateOptions); 
						$result = $mng->executeBulkWrite('ecodata.activity', $bulk);
					}
				}
				else {
					$nbHmiss++;
				}
			}
*/		


} catch(\Exception $e){
  echo "Exception:", $e->getMessage(), "\n";

}

	echo consoleMessage("info", "Script ends");

?>
