<?php

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
//require PATH_SHARED_FUNCTIONS."mongo-functions.php";


$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "php script/correctSurveyFinishTime.php std excel [exec]");
echo consoleMessage("info", "Change the finish time for all the historical surveys");


$arr_protocol=array("std");
$arr_mode=array("excel", "postgres");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

  if (!isset($argv[2]) || !in_array(trim($argv[2]), $arr_mode)) {
    echo consoleMessage("error", "Second parameter missing: ".implode("/", $arr_mode));
  }
  else {
    $protocol=$argv[1];
    $mode=$argv[2];

    if ($mode=="excel") $dataOrigin="scriptExcel";
    if ($mode=="postgres") $dataOrigin="scriptPostgres";

    $savelogcsv="";
    $json="";
  	// get all the output
    $filter = ["dataOrigin" => $dataOrigin,"name" => "Standardrutt"];
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

          $time999=true; // to find the old times with only 999 in it

          foreach ($row->data->timeOfObservation[0] as $key => $val) {
            //echo $key."-".$val."\n";
            if ($val != 999) {
              $time999=false;

              if ($val!="0" && $val!="" && $val!=0) {
                $val=intval($val);

                if ($val<0 || $val>2400) {
                  $consoleTxt.=consoleMessage("error", "time value for ".$key." must be between 0 and 2400 for outputId ".$row->outputId);
                }
                if ($val<$start_time)
                  $start_time=$val;

                // add 24 hours to the night tmes, to help comparing
                if ($protocol=="natt" && $val<1200)
                  $val+=2400;

                if ($val>$finish_time)
                  $finish_time=$val;

                if ($val>2400) $val-=2400;
              }
            }

          }
          if ($time999) {
            $start_time=0;
            $finish_time=0;
          }
          else {
            if ($start_time>2400) $start_time-=2400;
            if ($finish_time>2400) $finish_time-=2400;
            
            //echo consoleMessage("info", "finishtime found : ".$finish_time);

            $finish_time=addTimeToFinish($finish_time, 35);
          }
          $finish_time_converted=convertTime($finish_time, "24H");

          $savelogcsv.=$row->activityId.",".$row->data->surveyDate.",".$row->data->surveyFinishTime.",".$finish_time.",".$finish_time_converted."\n";
          $json.='db.output.update({"activityId":"'.$row->activityId.'"},{"$set":{"data.surveyFinishTime":"'.$finish_time_converted.'"}});'."\n";
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

  	echo consoleMessage("info", $nbOutput." output(s) with surveyFinishTime to be fixed in dataorigin:".$dataOrigin);

    if ($savelogcsv!="") {
      $path= "script/json/".date("Y-m-d_His")."_correctSurveyFinishTime_".$mode."_savelogcsv.csv";
      if ($fp = fopen($path, 'w')) {
        fwrite($fp, $savelogcsv);
        echo consoleMessage("info", "File savelogcsv write in ".$path);

        fclose($fp);
      }
      else echo consoleMessage("error", "can't create file ".$path);
    }

    if ($json!="") {
      $path= "script/json/".date("Y-m-d_His")."_correctSurveyFinishTime_".$mode.".json";
      if ($fp = fopen($path, 'w')) {
        fwrite($fp, $json);
        echo consoleMessage("info", "File json write in ".$path);

        fclose($fp);
        echo consoleMessage("info", "Comand to run it :");
        echo "mongo ecodata < ".$path."\n";
      }
      else echo consoleMessage("error", "can't create file ".$path);
    }

  }
}

echo consoleMessage("info", "script ends.");
