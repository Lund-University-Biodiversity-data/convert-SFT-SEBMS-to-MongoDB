<?php

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
//require PATH_SHARED_FUNCTIONS."mongo-functions.php";


$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/correctSurveyFinishTime.php std ");
echo consoleMessage("info", "Change the finish time for all the historical surveys");


$arr_protocol=array("std");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	$protocol=$argv[1];

	// get all the output
    $filter = ["dataOrigin" => "scriptExcel","name" => "Standardrutt"];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    $rows = $mng->executeQuery("ecodata.output", $query);

    $nbOutput=0;
    $nbTimeFixed=0;
    foreach ($rows as $row){

      if (isset($row->data->surveyFinishTime) && strlen($row->data->surveyFinishTime)==5){
        $explTime=explode(":",$row->data->surveyFinishTime);
        if (isset($explTime[0]) && isset($explTime[1]) && is_numeric($explTime[0]) && is_numeric($explTime[1])) {
          if ($explTime[0]<0 || $explTime[0]>24) {
            echo consoleMessage("error", "Wrong hours in surveyFinishTime (".$explTime[0].") for outputId ".$row->outputId);
          }
          if ($explTime[1]<0 || $explTime[1]>60) {
            echo consoleMessage("error", "Wrong minutes in surveyFinishTime (".$explTime[1].") for outputId ".$row->outputId);
          }

          // obtain the thoericall finish time
          // find the start time and finish time
          $start_time=2359;
          $finish_time=0;
          $timeOfObservation=array();

          foreach ($row->data->timeOfObservation[0] as $key => $val) {
            
            if ($val!="0" && $val!="" && $val!=0) {
              $val=intval($val);

              if ($val<0 || $val>2400) {
                $consoleTxt.=consoleMessage("error", "time value for ".$key." must be between 0 and 2400 for outputId ".$row->outputId);
              }
              if ($val<$start_time)
                $start_time=$val;

              // add 24 hours to the night tmes, to help comparing
              if ($val<1200)
                $val+=2400;

              if ($val>$finish_time)
                $finish_time=$val;

              if ($val>2400) $val-=2400;
            }

          }
          if ($start_time>2400) $start_time-=2400;
          if ($finish_time>2400) $finish_time-=2400;
          
          //echo consoleMessage("info", "finishtime found : ".$finish_time);

          $finish_time=addTimeToFinish($finish_time, 35);
          //echo consoleMessage("info", "finishtime +35 : ".$finish_time);
          //echo consoleMessage("info", "in output so far ".$row->data->surveyFinishTime);
echo $row->data->surveyFinishTime.",".$finish_time.",".$row->activityId."\n";
        }
        else {
          echo consoleMessage("error", "Wrong format (missing minute/time or non-numerical values) for surveyFinishTime for outputId : ".$row->outputId);

        }
      }
      else {
        echo consoleMessage("error", "Wrong format (empty or strlen != 5) for surveyFInishTime for outputId : ".$row->outputId);

      }
      //echo $row->data->surveyFinishTime;
    	$nbOutput++;
	}

	echo consoleMessage("info", $nbOutput." output(s) with surveyFinishTime to be fixed.");

/*
	$bulk = new MongoDB\Driver\BulkWrite;

    $filter = [
    	'internalPersonId' => $internalPersonId
    ];

    $options =  ['$set' => [
    	'anonymizedId' => $anonymizedId
    ]];

    $updateOptions = ['multi' => false];
    $bulk->update($filter, $options, $updateOptions); 
    $result = $mng->executeBulkWrite('ecodata.person', $bulk);
*/
}

echo consoleMessage("info", "script ends.");
