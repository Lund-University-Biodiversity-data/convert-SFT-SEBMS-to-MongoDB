<?php

// format: caracters spearated by '-'. Example: xxxx-xxxx-xxxx-xxxxxxxxx
// generates an unique ID with hexa digits.
function generate_uniqId_format ($format) {
	$format_arr=explode("-", $format);
	$uniqid_str=array();
	foreach ($format_arr as $part) {
		// 13 digits random uniqid
		//$uniqid=uniqid();
		$uniqid="";
		for ($i=0;$i<strlen($part);$i++) {
			$uniqid.=dechex(rand(0,15));
		}

		$uniqid_str[]=substr($uniqid, strlen($uniqid)-strlen($part), strlen($part));

		//echo "uniqueID:".$uniqid."|part:".substr($uniqid, strlen($uniqid)-strlen($part), strlen($part))."\n";
	}

	return implode("-", $uniqid_str);
}


// convert a string with HHMM to HH:MM AM/PM
// examples: 
// 2342 => 11:42 PM
// 1114 => 11:14 AM
// 754 => 07:54 AM
// 15 => 00:15 AM, 
function convertTime($time) {

	if ($time>1200){
		$time-=1200;
		$time=str_pad($time, 4, '0', STR_PAD_LEFT);
		$time=substr($time, 0, 2).":".substr($time, 2, 2)." PM";
	}
	else {
		$time=str_pad($time, 4, '0', STR_PAD_LEFT);
		$time=substr($time, 0, 2).":".substr($time, 2, 2)." AM";
	}

	return $time;
}

echo "****CONVERT SFT mattrutter to MongoDB JSON****\n";

$db_connection = pg_connect("host=localhost dbname=sft_ake user=postgres")  or die("CONNECT:" . pg_result_error());


$commonFields=array();
$commonFields["dateCreated"]=array("datetime", true, "2020-03-27T09:55:42.288Z");
$commonFields["lastUpdated"]=array("datetime", true, $commonFields["dateCreated"][2]);
//$commonFields["occurenceID"]="????";
$commonFields["status"]=array("string", true, "active");
$commonFields["recordedBy"]=array("string", true, "Mathieu Blanchet");
$commonFields["rightsHolder"]=array("string", true, "Lund University");
$commonFields["institutionID"]=$commonFields["rightsHolder"];
$commonFields["basisOfRecord"]=array("string", true, "HumanObservation");
$commonFields["datasetId"]=array("string", true, "7a47e9c7-ee3a-4681-939b-b144a1269fd0");
$commonFields["datasetName"]=array("string", true, "vinterrutterna");
$commonFields["type"]=array("string", true, "Bird surveys - vinterrutterna");
$commonFields["locationID"]=array("string", true, "eccb9fcb-a12e-4fa6-95a3-ef7d329646e3");
$commonFields["locationName"]=array("string", true, "Night route test 02DSV");
$commonFields["eventTime"]=array("datetime", true, "08:42 AM");
//$commonFields["verbatimCoordinates"]="";
$commonFields["multimedia"]=array("array", true, "[ ]");
//$commonFields["activityId"]=?????;
//$commonFields["eventID"]=$commonFields["activityId;

$commonFields["outputId"]=array("string", true, "e1e377f8-9abc-46ee-8518-c567196470cf"); /*** TO BE CHANGED **/
$commonFields["name"]=array("string", true, "vinterrutterna"); /*** TO BE CHANGED **/

$commonFields["outputSpeciesId"]=array("string", true, "");
//$commonFields["projectActivityId"]=array("string", true, "17ee4c9f-abe7-4926-b2d7-244f008ceaeb");
$commonFields["projectActivityId"]=array("string", true, "7a47e9c7-ee3a-4681-939b-b144a1269fd0");
$commonFields["projectId"]=array("string", true, "d9253588-6fe3-4a2a-a7cd-25fc283134f3");
$commonFields["userId"]=array("number", true, 5);
$commonFields["decimalLatitude"]=array("number", true, 55.57062482384738);
$commonFields["decimalLongitude"]=array("number", true, 13.624076843261719);


// LPAD(cast(LEAST(p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20) as text), 4, '0')
$qEvents="
select P.efternamn, P.fornamn, TN.datum , p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.kartatx
from totalnatt TN
left join personer P on P.persnr = TN.persnr 
where TN.kartatx='02DSV'  
AND TN.art='000'
order by datum
";

$rEvents = pg_query($db_connection, $qEvents);
if (!$rEvents) die("QUERY:" . pg_last_error());


$arr_json_activity='[';
$arr_json_output='[';
$arr_json_record='[';
$nbLines=0;
while ($rtEvents = pg_fetch_array($rEvents)) {

	$qRecords="
		select EL.arthela AS names, EL.latin as scientificname, p01, p02, p03, p04, p05, p06, p07, p08, p09, p10, p11, p12, p13, p14, p15, p16, p17, p18, p19, p20, TN.art, TN.datum
		from totalnatt TN, eurolist EL
		where EL.art=TN.art 
		and TN.kartatx='".$rtEvents["kartatx"]."'  
		AND EL.art<>'000'
		AND TN.datum='".$rtEvents["datum"]."'
		order by datum
	";
	//echo $qRecords;
	$rRecords = pg_query($db_connection, $qRecords);
	if (!$rRecords) die("QUERY:" . pg_last_error());

	$nbLines++;
	if ($nbLines%100==0) echo number_format($nbLines, 0, ".", " ")." ĺines\n";

	$activityId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");
	$eventID=$activityId;
	$outputId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

	//echo "outputId: $outputId\n";

	// date now
	$micro_date = microtime();
	$date_array = explode(" ",$micro_date);
	$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".number_format($date_array[0]*1000, 0)."Z";
	//echo "Date: $date_now_tz\n";

	// survey date
	$date_survey=date("Y-m-d", strtotime($rtEvents["datum"]))."T00:00:00Z";
	//echo "date survey".$date_survey."\n";

	// find the start time and finsh time
	$start_time=2359;
	$finish_time=0;
	$arr_time=array();

	for ($i=1; $i<=20; $i++) {
		$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
		
		// add 24 hours to the night tmes, to help comparing
		if ($rtEvents[$ind]<1200)
			$rtEvents[$ind]+=2400;

//echo $rtEvents[$ind]."\n";

		if ($rtEvents[$ind]<$start_time)
			$start_time=$rtEvents[$ind];

		if ($rtEvents[$ind]>$finish_time)
			$finish_time=$rtEvents[$ind];

		if ($rtEvents[$ind]>2400) $rtEvents[$ind]-=2400;

		$arr_time[$ind]=convertTime($rtEvents[$ind]);
	}

	if ($start_time>2400) $start_time-=2400;
	if ($finish_time>2400) $finish_time-=2400;

	$start_time_brut=$start_time;

	$start_time=convertTime($start_time);
	$finish_time=convertTime($finish_time);

	//echo "start_time: $start_time\n";
	//echo "finish_time: $finish_time\n";

	$eventDate=date("Y-m-d", strtotime($rtEvents["datum"]))."T".substr($start_time_brut, 0, 2).":".substr($start_time_brut, 2, 4).":00Z";
	//echo "eventDate: $eventDate\n";


	$recorder_name=$rtEvents["fornamn"].' '.$rtEvents["efternamn"];


	$data_field="";
	while ($rtRecords = pg_fetch_array($rRecords)) {
		
		$data_field.='{';

		$IC=0;

		for ($i=1; $i<=20; $i++) {
			$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
			if (!is_numeric($rtRecords[$ind])) $rtRecords[$ind]=0;
			$IC+=$rtRecords[$ind];

			$data_field.='"P'.str_pad($i, 2, '0', STR_PAD_LEFT).'": '.$rtRecords[$ind].",";
		}

		$outputSpeciesId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

		$data_field.='"species" : {
						"listId" : "error-unmatched",
						"commonName" : "",
						"outputSpeciesId" : "'.$outputSpeciesId.'",
						"scientificName" : "'.$rtRecords["scientificname"].'",
						"name" : "'.$rtRecords["names"].'",
						"guid" : ""
					},';
		$data_field.='"individualCount" : '.$IC.'
						},';

		$occurenceID=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");


		$arr_json_record.='{
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"occurrenceID" : "'.$occurenceID.'",
			"status" : "active",
			"recordedBy" : "'.$recorder_name.'",
			"rightsHolder" : "'.$commonFields["rightsHolder"][2].'",
			"institutionID" : "'.$commonFields["institutionID"][2].'",
			"basisOfRecord" : "'.$commonFields["basisOfRecord"][2].'",
			"datasetID" : "'.$commonFields["projectActivityId"][2].'",
			"datasetName" : "'.$commonFields["datasetName"][2].'",
			"eventID" : "'.$eventID.'",
			"name" : "'.$rtRecords["names"].'",
			"scientificName" : "'.$rtRecords["scientificname"].'",
			"multimedia" : [ ],
			"activityId" : "'.$activityId.'",
			"decimalLatitude" : '.$commonFields["decimalLatitude"][2].',
			"decimalLongitude" : '.$commonFields["decimalLongitude"][2].',
			"eventDate" : "'.$eventDate.'",
			"individualCount" : '.$IC.',
			"outputId" : "'.$outputId.'",
			"outputSpeciesId" : "0ff0e6fb-8cd1-4c3d-bf71-efa57375e245",
			"projectActivityId" : "'.$commonFields["projectActivityId"][2].'",
			"projectId" : "'.$commonFields["projectId"][2].'",
			"userId" : "'.$commonFields["userId"][2].'"
		},';


	}
	// replace last comma by 
	$data_field[strlen($data_field)-1]=' ';
	//echo "data_field: ".$data_field."\n";



	$arr_json_activity.='{
		"activityId" : "'.$activityId.'",
		"assessment" : false,
		"dateCreated" : ISODate("'.$date_now_tz.'"),
		"lastUpdated" : ISODate("'.$date_now_tz.'"),
		"progress" : "planned",
		"projectActivityId" : "'.$commonFields["projectActivityId"][2].'",
		"projectId" : "'.$commonFields["projectId"][2].'",
		"projectStage" : "",
		"siteId" : "",
		"status" : "active",
		"type" : "'.$commonFields["type"][2].'",
		"userId" : "'.$commonFields["userId"][2].'",
		"mainTheme" : ""
	},';
	//			"helper1" : "Jean Michel Helper",
	//			"helper2" : "Raoul HElper",
	$arr_json_output.='{
		"activityId" : "'.$activityId.'",
		"dateCreated" : ISODate("'.$date_now_tz.'"),
		"lastUpdated" : ISODate("'.$date_now_tz.'"),
		"outputId" : "'.$outputId.'",
		"status" : "active",
		"outputNotCompleted" : false,
		"data" : {
			"surveyFinishTime" : "'.$finish_time.'",
			"locationAccuracy" : 50,
			"surveyDate" : "'.$date_survey.'",
			"locationHiddenLatitude" : '.$commonFields["decimalLatitude"][2].',
			"locationLatitude" : '.$commonFields["decimalLatitude"][2].',
			"locationSource" : "Google maps",
			"recordedBy" : "'.$recorder_name.'",
			"helper1": "",
			"helper2": "",
			"surveyStartTime" : "'.$start_time.'",
			"locationCentroidLongitude" : null,
			"observations" : [
				'.$data_field.'
			],
			"locationLongitude" : '.$commonFields["decimalLongitude"][2].',
			"locationHiddenLongitude" : '.$commonFields["decimalLongitude"][2].',
			"locationCentroidLatitude" : null,
			"transectName" : "'.$commonFields["locationName"][2].'"
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
		"name" : "'.$commonFields["name"][2].'"
	},';



};

// replace last comma by 
$arr_json_output[strlen($arr_json_output)-1]=' ';
$arr_json_output.=']';
$arr_json_activity[strlen($arr_json_activity)-1]=' ';
$arr_json_activity.=']';
$arr_json_record[strlen($arr_json_record)-1]=' ';
$arr_json_record.=']';

//echo $arr_json_output;
//echo "\n";





for ($i=1;$i<=3;$i++) {
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
	}
	$filename_json='json_'.$typeO.'s_SFT_natt_'.date("Y-m-d-His").'.json';
	$path='json/'.$filename_json;
	echo 'mongoimport --db ecodata --collection '.$typeO.' --jsonArray --file '.$path."\n";
	//$json = json_encode($arr_rt, JSON_UNESCAPED_SLASHES); 
	if ($fp = fopen($path, 'w')) {
		fwrite($fp, $json);
		fclose($fp);
	}
	else echo "ERROR: can\'t create file\n\n";

	echo 'PATH: '.$path."\n\n";

}

/*
send files
scp json/json_* radar@canmove-dev.ekol.lu.se:/home/radar/convert_sft_mongodb/json

clean mongo
db.activity.find({"userId":"5"}).count();
db.activity.remove({"userId":"5"})

db.record.find({"userId":"5"}).count();
db.record.remove({"userId":"5"})

db.output.find({"dateCreated":{"$gt": new ISODate("2020-04-03T08:16:15.184Z")}}).count()
db.output.remove({"dateCreated":{"$gt": new ISODate("2020-04-03T08:16:15.184Z")}})
*/





/*
EXAMPLES =>

> db.activity.find({"activityId":"e67831b2-323c-47ce-b262-f03f0a9f4ffa"}).pretty()
{
	"_id" : ObjectId("5e8726aff14ced19ba41b7a5"),
	"activityId" : "e67831b2-323c-47ce-b262-f03f0a9f4ffa",
	"assessment" : false,
	"dateCreated" : ISODate("2020-04-03T12:06:07.519Z"),
	"lastUpdated" : ISODate("2020-04-03T12:06:07.579Z"),
	"progress" : "planned",
	"projectActivityId" : "7a47e9c7-ee3a-4681-939b-b144a1269fd0",
	"projectId" : "d9253588-6fe3-4a2a-a7cd-25fc283134f3",
	"projectStage" : "",
	"siteId" : "",
	"status" : "active",
	"type" : "Bird surveys - vinterrutterna",
	"userId" : "5",
	"mainTheme" : ""
}

> db.output.find({"outputId":"901446dd-186c-4cd1-989c-5facf9b3cf75"}).pretty()
{
	"_id" : ObjectId("5e8726aff14ced19ba41b7a6"),
	"activityId" : "e67831b2-323c-47ce-b262-f03f0a9f4ffa",
	"dateCreated" : ISODate("2020-04-03T12:06:07.618Z"),
	"lastUpdated" : ISODate("2020-04-03T12:06:07.992Z"),
	"outputId" : "901446dd-186c-4cd1-989c-5facf9b3cf75",
	"status" : "active",
	"outputNotCompleted" : false,
	"data" : {
		"locationAccuracy" : 50,
		"recordedBy" : "Mathieu Blanchet",
		"surveyDate" : "2020-04-03T14:03:54+0200",
		"locationHiddenLatitude" : 48,
		"locationCentroidLongitude" : null,
		"observations" : [
			{
				"P1" : "10",
				"P2" : "0",
				"P3" : "0",
				"P4" : "0",
				"species" : {
					"listId" : "error-unmatched",
					"commonName" : "",
					"outputSpeciesId" : "0ff0e6fb-8cd1-4c3d-bf71-efa57375e245",
					"scientificName" : "",
					"name" : "grus",
					"guid" : ""
				},
				"P5" : "0",
				"P6" : "0",
				"P7" : "0",
				"individualCount" : 10,
				"P8" : "0"
			}
		],
		"locationLatitude" : 48,
		"locationLongitude" : 49,
		"locationSource" : "Google maps",
		"locationCentroidLatitude" : null,
		"transectName" : "Night route test 02DSV",
		"locationHiddenLongitude" : 49
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
	"name" : "vinterrutterna"
}

> db.record.find({"projectActivityId":"7a47e9c7-ee3a-4681-939b-b144a1269fd0"}).pretty()
{
	"_id" : ObjectId("5e8726aff14ced19ba41b7a7"),
	"dateCreated" : ISODate("2020-04-03T12:06:07.924Z"),
	"lastUpdated" : ISODate("2020-04-03T12:06:07.978Z"),
	"occurrenceID" : "59769b59-d74e-421a-8c21-96a515bbcc25",
	"status" : "active",
	"recordedBy" : "Mathieu Blanchet",
	"rightsHolder" : "Lund University",
	"institutionID" : "Lund University",
	"basisOfRecord" : "HumanObservation",
	"datasetID" : "7a47e9c7-ee3a-4681-939b-b144a1269fd0",
	"datasetName" : "Test nattrutter",
	"eventID" : "e67831b2-323c-47ce-b262-f03f0a9f4ffa",
	"name" : "grus",
	"scientificName" : "grus",
	"multimedia" : [ ],
	"activityId" : "e67831b2-323c-47ce-b262-f03f0a9f4ffa",
	"decimalLatitude" : 48,
	"decimalLongitude" : 49,
	"eventDate" : "2020-04-03T14:03:54+0200",
	"individualCount" : 10,
	"outputId" : "901446dd-186c-4cd1-989c-5facf9b3cf75",
	"outputSpeciesId" : "0ff0e6fb-8cd1-4c3d-bf71-efa57375e245",
	"projectActivityId" : "7a47e9c7-ee3a-4681-939b-b144a1269fd0",
	"projectId" : "d9253588-6fe3-4a2a-a7cd-25fc283134f3",
	"userId" : "5"
}

*/







/*
/*


	foreach ($structure as $key => $data) {

		//mandatory fields OR if not mandatory => non-empty 
		if ($data[1] || (!$data[1] && trim($rtEvents[$key])!="")) {
			$line_json.='"'.$key.'"'.': ';
			if ($data[0]!="number") $line_json.='"';
			if ($data[0]=="number" && $rtEvents[$key]=="") $line_json.=0;
			elseif ($data[0]=="date" && $rtEvents[$key]!="") {
				$line_json.=date("Y-m-d H:i:s", strtotime($rtEvents[$key]));
			}
			else $line_json.=$rtEvents[$key];

			if ($data[0]!="number") $line_json.='"';
			$line_json.=',';
		}
	}

	// replace last comma by }
	$line_json[strlen($line_json)-1]='}';
	$arr_json_record.=' '.$line_json.',';

};
*/

?>