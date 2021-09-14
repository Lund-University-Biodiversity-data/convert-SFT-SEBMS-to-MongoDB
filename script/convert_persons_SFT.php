
<?php
$database="SFT";
$dataOrigin="scriptPostgres";
$server="PROD";

require "lib/config.php";
require "lib/functions.php";

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "php convert_persons_SFT.php [reinsertall]");

if (isset($argv[1]) && $argv[1] == "reinsertall") {

	$mode="reinsertall";

}
else {
	$mode="update";
}

// date now
$micro_date = microtime();
$date_array = explode(" ",$micro_date);
$micsec=number_format($date_array[0]*1000, 0, ".", "");
$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
if ($micsec==1000) $micsec=999;
$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";
//echo "Date: $date_now_tz\n";

$tabUserAlreadyCreated=getListUsersAlreadyHardcoded();

if ($mode=="update") {
	$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created

    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

    $filter = [];
    if (!is_null($projectId)) {
        $filter = ['hub' => "sft"];
    }
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 
    $rows = $mng->executeQuery("ecodata.person", $query);

    foreach ($rows as $row){
        
        if (isset($row->internalPersonId)) {
        	$tabUserAlreadyCreated[]=$row->internalPersonId;
        }
    }
}


echo consoleMessage("info", count($tabUserAlreadyCreated)." person(s) already in mongoDb");

$arr_json_person='[';

if ($mode=="reinsertall") {
	$arr_json_person.=getJsonPersonsHardcoded();
}

$db_connection = pg_connect("host=".$DB["host"]." dbname=".$DB["database"]." user=".$DB["username"]." password=".$DB["password"])  or die("CONNECT:" . consoleMessage("error", pg_result_error()));

$qPerson= "
		select *
		from personer 
		";
$rPerson = pg_query($db_connection, $qPerson);

$nbPersonsToCreate=0;
$nbPersonsPsql=0;

while ($rtPerson = pg_fetch_array($rPerson)) {

	$nbPersonsPsql++;
	$explBD=explode("-", $rtPerson["persnr"]);
	$birthDate="19".substr($explBD[0], 0, 2)."-".substr($explBD[0], 2, 2)."-".substr($explBD[0], 4, 2);

	$personId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

	if (!in_array($rtPerson["persnr"], $tabUserAlreadyCreated)) {

		$arr_json_person.='{
			"dateCreated" : ISODate("'.$date_now_tz.'"),
			"lastUpdated" : ISODate("'.$date_now_tz.'"),
			"personId" : "'.$personId.'",
			"firstName" : "'.$rtPerson["fornamn"].'",
			"lastName" : "'.$rtPerson["efternamn"].'",
			"gender" : "'.$rtPerson["sx"].'",
			"birthDate" : "'.$birthDate.'",
			"email" : "'.$rtPerson["epost"].'",
			"phoneNum" : "'.$rtPerson["telhem"].'",
			"mobileNum" : "'.$rtPerson["telmobil"].'",
			"address1" : "'.$rtPerson["adress1"].'",
			"address2" : "'.$rtPerson["adress2"].'",
			"postCode" : "'.$rtPerson["postnr"].'",
			"town" : "'.$rtPerson["ort"].'",
			"projects": [ 
				"'.$commonFields["std"]["projectId"].'", 
				"'.$commonFields["natt"]["projectId"].'", 
				"'.$commonFields["kust"]["projectId"].'", 
				"'.$commonFields["punkt"]["projectId"].'" 
			],
			"hub" : "sft",
			"internalPersonId" : "'.$rtPerson["persnr"].'"
		},';

		$nbPersonsToCreate++;
	}
	else {
		//echo consoleMessage("warning", "Persnr already hardcoded ".$rtPerson["persnr"]);
	}

/*
			"'.$commonFields["sommar"]["projectId"].'", 
			"'.$commonFields["sjo"]["projectId"].'", 
*/
}

echo consoleMessage("info", $nbPersonsPsql." person(s) in PSQL database");
echo consoleMessage("info", $nbPersonsToCreate." person(s) in Json file to be created");

$arr_json_person[strlen($arr_json_person)-1]=' ';
$arr_json_person.=']';

$filename_json='postgres_json_'.$database.'_persons_'.date("Y-m-d-His").'.json';
$path='dump_json_sft_sebms/'.$database.'/persons/'.$filename_json;
//echo 'db.'.$typeO.'.remove({"dateCreated" : {$gte: new ISODate("'.date("Y-m-d").'T01:15:31Z")}})'."\n";
echo 'mongoimport --db ecodata --collection person --jsonArray --file '.$path."\n";
//$json = json_encode($arr_rt, JSON_UNESCAPED_SLASHES); 
if ($fp = fopen($path, 'w')) {
	fwrite($fp, $arr_json_person);
	fclose($fp);
}
else echo consoleMessage("error", "can't create file ".$path);

echo "scp dump_json_sft_sebms/".$database."/persons/postgres_json_* ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/dump_json_sft_sebms/".$database."/persons/\n";

echo consoleMessage("info", "Script ends");
?>