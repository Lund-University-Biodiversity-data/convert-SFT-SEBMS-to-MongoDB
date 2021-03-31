<?php
$database="SFT";
$dataOrigin="scriptExcel";
require "lib/config.php";
require "lib/functions.php";

//require_once "lib/PHPExcel/Classes/PHPExcel.php";


require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php transform_excel_json.php std 2 debug");

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

	switch($protocol) {
		case "std":
			$nbPts=8;
			break;
		case "vinter":
			$nbPts=20;
			break;
		case "natt":
			$nbPts=20;
			break;
		case "kust":
			$nbPts=1;
			break;
	}

	$arr_json_activity='[
	';
	$arr_json_output='[
	';
	$arr_json_record='[
	';
	$arr_json_person='[
	';

	$array_sites=getArraySitesFromMongo($protocol, $commonFields[$protocol]["projectId"]);

	foreach($array_sites as $indexSite => $data) {
		$array_sites_req[]="'".$indexSite."'";
	}
	
	$req_sites="(".implode(",", $array_sites_req).")";

	$arrSpeciesNotFound=array();
	$speciesNotFound=0;
	$speciesFound=0;
	$observationFieldName="observations";

	$array_species_guid=array();
	$array_species_art=array();
	// GET the list of species


	foreach ($commonFields["listSpeciesId"] as $animals => $listId) {

		echo consoleMessage("info", "Get info from species list ".$listId);


		$url="https://lists.bioatlas.se/ws/speciesListItems/".$commonFields["listSpeciesId"][$animals]."?includeKVP=true";
		$obj = json_decode(file_get_contents($url), true);

		foreach($obj as $sp) {
			//$array_species_guid[$sp["scientificName"]]=$sp["lsid"];
			$array_species_guid[$animals][$sp["name"]]["lsid"]=$sp["lsid"];

			if ($sp["lsid"]!="") {

				$art="-";
				$nameSWE="-";

				if (isset($sp["kvpValues"][0]["key"]) && $sp["kvpValues"][0]["key"]=="art") {
					$art=$sp["kvpValues"][0]["value"];
					$art=str_pad($art, 3, '0', STR_PAD_LEFT);
				}
				else echo consoleMessage("error", "No art for ".$sp["name"]."/".$sp["lsid"]);	

				if (isset($sp["kvpValues"][1]["key"])) {
					$nameSWE=$sp["kvpValues"][1]["value"];
				}
				else echo consoleMessage("error", "No name for ".$sp["name"]."/".$sp["lsid"]);	

				$array_species_art[$art]["lsid"]=$sp["lsid"];
				$array_species_art[$art]["sn"]=$sp["name"];
				$array_species_art[$art]["nameSWE"]=$nameSWE;

				/*
				$url="https://lists.bioatlas.se/ws/species/".$sp["lsid"];
				$species = json_decode(file_get_contents($url), true);

				if (isset($species[count($species)-1]["kvpValues"][0]["key"]) && $species[count($species)-1]["kvpValues"][0]["key"]=="art"){
					$art=$species[count($species)-1]["kvpValues"][0]["value"];
					$art=str_pad($art, 3, '0', STR_PAD_LEFT);
					$nameSWE=$species[count($species)-1]["kvpValues"][1]["value"];
				}
				elseif (isset($species[count($species)-2]["kvpValues"][0]["key"]) && $species[count($species)-2]["kvpValues"][0]["key"]=="art"){
					$art=$species[count($species)-2]["kvpValues"][0]["value"];
					$art=str_pad($art, 3, '0', STR_PAD_LEFT);
					$nameSWE=$species[count($species)-2]["kvpValues"][1]["value"];
				}
				else {
					$art="-";
					$nameSWE="-";
					echo consoleMessage("error", "No art for ".$sp["name"]."/".$sp["lsid"]);
				}

				$array_species_art[$art]["lsid"]=$sp["lsid"];
				$array_species_art[$art]["sn"]=$sp["name"];
				$array_species_art[$art]["nameSWE"]=$nameSWE;

				$line=$art."#".$array_species_art[$art]["lsid"]."#".$array_species_art[$art]["sn"]."#".$array_species_art[$art]["nameSWE"]."\n";
				fwrite($fp, $line);

				*/
			}
			else echo consoleMessage("error", "No lsid for ".$sp["name"]);		
		}




	}

	//if ($debug) print_r($array_species_art);

	// date now
	$micro_date = microtime();
	$date_array = explode(" ",$micro_date);
	$micsec=number_format($date_array[0]*1000, 0, ".", "");
	$micsec=str_pad($micsec,3,"0", STR_PAD_LEFT);
	if ($micsec==1000) $micsec=999;
	$date_now_tz = date("Y-m-d",$date_array[1])."T".date("H:i:s",$date_array[1]).".".$micsec."Z";
	//echo "Date: $date_now_tz\n";

	$path_excel="excel-surveys/SFT/".$protocol."/";

	$filesSurveys = scandir($path_excel);

	$nbFiles=0;
	$listFiles=array();

	$fileRefused=false;

	foreach($filesSurveys as $file) {

		if ($file!=".." && $file!="." && (substr($file, strlen($file)-4, 4)==".xls" ||substr($file, strlen($file)-5, 5)==".xlsx")) {

			$nbFiles++;
			$listFiles[]=$file;

			$kartakodThorical = substr($file, 0, 5);

			if (isset($limitFiles) && $limitFiles<$nbFiles) break;

			$tmpfname = $path_excel.$file;
			echo consoleMessage("info", "Opens file ".$tmpfname);

			try {
				/*
				$inputFileType = PHPExcel_IOFactory::identify($tmpfname);
				echo consoleMessage("info", "Input file type ".$inputFileType);
				//$excelReader = PHPExcel_IOFactory::createReaderForFile($inputFileType);
				//$excelObj = $excelReader->load($tmpfname);
				$objReader = PHPExcel_IOFactory::createReader( $inputFileType );
				//$objReader->setLoadSheetsOnly( $sheetname ); // Load specific sheet             
				$excelObj = $objReader->load( $tmpfname );
				$worksheet = $excelObj->getSheet(0);//
				*/

				$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
				echo consoleMessage("info", "Excel type ".$inputFileType);
				$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
				$excelObj->setReadDataOnly(true);
				$spreadsheet = $excelObj->load($tmpfname);
				$worksheet = $spreadsheet->getSheet(0);
				
			}
			catch (\Exception $exception) {
				echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
				echo consoleMessage("error", "can't read Excel file ".$tmpfname);
				exit; 
			}
			$lastRow = $worksheet->getHighestRow();


			switch($protocol) {
				case "std":
					$kartakod=$worksheet->getCell('A9')->getValue();
					$ruttname=$worksheet->getCell('D9')->getValue();
					$datum=$worksheet->getCell('K9')->getValue();
					$inventerare = $worksheet->getCell('C13')->getValue();

					$recorder_name=$worksheet->getCell('B15')->getValue();
					$adress=$worksheet->getCell('B16')->getValue();
					$post=$worksheet->getCell('B17')->getValue();
					$city=$worksheet->getCell('F17')->getValue();
					$email=$worksheet->getCell('N15')->getValue();
					$address1=$recorder_name;
					$address2=$worksheet->getCell('B16')->getValue();
					$tel=$worksheet->getCell('N16')->getValue();
					$mobile=$worksheet->getCell('N17')->getValue();

					$eventRemarks=str_replace('"', "'", $worksheet->getCell('A32')->getValue());
					$notes=str_replace('"', "'", $worksheet->getCell('A35')->getValue());
					$comments=$notes;

					$rowStartTime=23;
					$iRowSpecies=39;
					$colSpeciesCode="R";
					$colTotObs="V";

					break;
				case "vinter":
					$nbPts=20;
					break;
				case "natt":
					$kartakod=$worksheet->getCell('A9')->getValue();
					$ruttname=$worksheet->getCell('D9')->getValue();
					$datum=$worksheet->getCell('L9')->getValue();
					$inventerare = $worksheet->getCell('C13')->getValue();
					$per = $worksheet->getCell('L13')->getValue();

					if (trim($worksheet->getCell('U10')->getValue())=="X" || trim($worksheet->getCell('U10')->getValue())=="x")
						$mammalsCounted="ja";
					else $mammalsCounted="nej";
					if (trim($worksheet->getCell('U11')->getValue())=="X" || trim($worksheet->getCell('U11')->getValue())=="x")
						$amphibiansCounted="ja";
					else $amphibiansCounted="nej";

					$recorder_name=$worksheet->getCell('B15')->getValue();
					$adress=$worksheet->getCell('B16')->getValue();
					$post=$worksheet->getCell('B17')->getValue();
					$city=$worksheet->getCell('F17')->getValue();
					$email=$worksheet->getCell('M15')->getValue();
					$address1=$recorder_name;
					$address2=$worksheet->getCell('B16')->getValue();
					$tel=$worksheet->getCell('M16')->getValue();
					$mobile=$worksheet->getCell('M17')->getValue();

					$cloudStart=$worksheet->getCell('B21')->getValue();
					$tempStart=$worksheet->getCell('C21')->getValue();
					$windStart=$worksheet->getCell('D21')->getValue();
					$precipStart=$worksheet->getCell('E21')->getValue();

					$cloudEnd=$worksheet->getCell('F21')->getValue();
					$tempEnd=$worksheet->getCell('G21')->getValue();
					$windEnd=$worksheet->getCell('H21')->getValue();
					$precipEnd=$worksheet->getCell('I21')->getValue();

					$eventRemarks=str_replace('"', "'", $worksheet->getCell('A164')->getValue());
					$notes="";
					$comments=$notes;

					$rowStartTime=25;
					$iRowSpecies=30;
					$colSpeciesCode="V";
					$colTotObs="Z";

					break;
				case "kust":
					$nbPts=1;
					break;
			}

			if ($kartakodThorical!=$kartakod) {
				echo consoleMessage("error", "Filename (".$kartakodThorical.") and kartakod in the file (".$kartakod.") don't match ");
				$fileRefused=true;
			}
			echo consoleMessage("info", "Inveterare : ".$inventerare); 

			// in case of a 6 digit date, we add 20
			if (strlen($datum)==6) $datum="20".$datum;

			$date_survey=date("Y-m-d", strtotime($datum))."T00:00:00Z";

			echo consoleMessage("info", "Kartakod / ruttnamn / datum : ".$kartakod." / ".$ruttname." / ".$datum." / ".$date_survey);

			if (isset($array_sites[$kartakod])) {

				// check if this person exists
				$explInv=explode("-", $inventerare);
				if (!isset($explInv[0]) || strlen($explInv[0])!=6) {
					echo consoleMessage("error", "Birthdate not valid ".$inventerare);
					$fileRefused=true;
				}
				else {
					$birthdate=$explInv[0];
					$year=substr($birthdate, 0, 2);

					$birthdate_format=($year<=20 ? "20".$year : "19".$year)."-".substr($birthdate, 2, 2)."-".substr($birthdate, 4, 2);
				}
				
				$filter = ['internalPersonId' => $inventerare];
				//$filter = [];
				$options = [];
				$query = new MongoDB\Driver\Query($filter, $options); 
				//db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
				// 
			    $mng = new MongoDB\Driver\Manager(); // Driver Object created

				$rows = $mng->executeQuery("ecodata.person", $query);

				$rowsToArray=$rows->toArray();

				if (count($rowsToArray)!=0) {
					$userId = (isset($rowsToArray[0]->userId) ? $rowsToArray[0]->userId : $commonFields["userId"]);
					$personId = $rowsToArray[0]->personId;
					echo consoleMessage("info", "Person exists in database: ".$personId);
				}
				else {

					$userId = $commonFields["userId"];
					$personId = generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");
					$explFN=explode(" ", $recorder_name);

					$arr_json_person.='{
						"dateCreated" : ISODate("'.$date_now_tz.'"),
						"lastUpdated" : ISODate("'.$date_now_tz.'"),
						"personId" : "'.$personId.'",
						"firstName" : "'.$explFN[0].'",
						"lastName" : "'.$explFN[1].'",
						"birthdate" : "'.$birthdate_format.'",
						"email" : "'.$email.'",
						"phoneNum" : "'.$tel.'",
						"mobileNum" : "'.$mobile.'",
						"address1" : "'.$address1.'",
						"address2" : "'.$address2.'",
						"postCode" : "'.$post.'",
						"town" : "'.$city.'",
						"projects": [ "'.$commonFields[$protocol]["projectId"].'" ],
						"internalPersonId" : "'.$inventerare.'"
					},';

					echo consoleMessage("info", "Person to be created with personid: ".$personId);
				}


				// find the start time and finsh time
				$start_time=2359;
				$finish_time=0;

				$minutesSpentObserving='"minutesSpentObserving" : [
						{';
				$timeOfObservation='"timeOfObservation" : [
						{';

				$colStartTime="B";
				$colStartMin="K";

				for ($i=1; $i<=$nbPts; $i++) {
					$timeofObs=$worksheet->getCell($colStartTime++.$rowStartTime)->getValue();
					$minSpentObs=$worksheet->getCell($colStartMin++.$rowStartTime)->getValue();

					switch($protocol) {
						case "std":
							$ind="p".$i;
							
							if ($timeofObs!="") {
								$timeOfObservation.='
								"TidP'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$timeofObs.'",';
							}
							if ($minSpentObs!="") {
								$minutesSpentObserving.='
								"TidL'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$minSpentObs.'",';
							}
							break;
						case "vinter":
							$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);
							break;
						case "natt":

							$ind="p".str_pad($i, 2, '0', STR_PAD_LEFT);

							$timeOfObservation.='
							"TidP'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$timeofObs.'",';

							break;
					}
					
					if ($timeofObs!="") {
						$timeofObs=intval($timeofObs);

						if ($timeofObs>2400) {
							echo consoleMessage("error", "ERROR time value ".$timeofObs." for P".$i);
							$fileRefused=true;
						}
						if ($timeofObs<$start_time)
							$start_time=$timeofObs;

						// add 24 hours to the night tmes, to help comparing
						if ($timeofObs<1200)
							$timeofObs+=2400;

						if ($timeofObs>$finish_time)
							$finish_time=$timeofObs;

						if ($timeofObs>2400) $timeofObs-=2400;
					}
				}

				if ($start_time>2400) $start_time-=2400;
				if ($finish_time>2400) $finish_time-=2400;

				$start_time_brut=$start_time;

				$start_time=convertTime($start_time, "24H");

				// add 5 minutes 
				$finish_time_5min=str_pad($finish_time, 4, '0', STR_PAD_LEFT);
				$hours=intval(substr($finish_time_5min, 0, 2));
				$minutes=intval(substr($finish_time_5min, 2, 2));
				if ($minutes>=55) {
					$minutes=str_pad(($minutes+5)-60, 2, '0', STR_PAD_LEFT);

					if ($hours==23) $hours=0;
					else $hours++;
				}
				else {
					$minutes+=5;
				}
				$finish_time=intval($hours.$minutes);

				$finish_time=convertTime($finish_time, "24H");

				//$eventTime=date("H:i", strtotime($rtEvents["datum"]));

				$distanceCovered='"distanceCovered" : [
						{
							';

				$iL="B";
				for ($i=1; $i<=$nbPts; $i++) {
					$distanceCovered.='
					"distanceOnL'.str_pad($i, 2, '0', STR_PAD_LEFT).'" : "'.$worksheet->getCell($iL."27")->getValue().'",';
					$iL++;
				}

				$distanceCovered[strlen($distanceCovered)-1]=' ';
				$distanceCovered.='}],';

				$eventDate=date("Y-m-d", strtotime($datum))."T".$start_time.":00Z";
				
				$minutesSpentObserving[strlen($minutesSpentObserving)-1]=' ';
				$minutesSpentObserving.='}],';
				$timeOfObservation[strlen($timeOfObservation)-1]=' ';
				$timeOfObservation.='}],';
				
				$arrJaGps=["X", "x", "ja", "Ja", "JA"];
				$isGpsUsed=(in_array($worksheet->getCell('T31')->getValue(), $arrJaGps)  ? "ja" : "nej");
				$activityId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");
				$eventID=$activityId;
				$outputId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");

				$helpers="[{}]";

				foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
					$data_field[$animals]="";
				}
				$data_field["mammalsOnRoad"]="";

				$listId = "dr158";

				// loop on the rows
				while ($worksheet->getCell($colTotObs.$iRowSpecies)->getValue() != "") {

					//echo "V".$iRowSpecies.":".$worksheet->getCell("V".$iRowSpecies)->getOldCalculatedValue()."(".$worksheet->getCell("S".$iRowSpecies)->getOldCalculatedValue()." - ".$worksheet->getCell("T".$iRowSpecies)->getOldCalculatedValue().")\n";
					if ($worksheet->getCell($colTotObs.$iRowSpecies)->getOldCalculatedValue()==1) {
					
						$art=$worksheet->getCell($colSpeciesCode.$iRowSpecies)->getValue();

						switch($protocol) {
							case "std":
								if ($art>="700" && $art<="799") {
									$animals="mammals";
									$speciesFieldName="speciesMammals";
								}
								else {
									$animals="birds";
									$speciesFieldName="species";
								}
								$animalsDataField=$animals;

								break;

							case "natt":
								$mammalsMode="";
								if(strlen($art)>3) {
									$mammalsMode=$art[3];
									$art=substr($art, 0, 3);
								}

								if ($art>="128" && $art<="138" && $mammalsMode=="k") {
									$animals="owls";
									$animalsDataField=$animals;
									$speciesFieldName="speciesYoungOwl";
								}
								elseif ($art>="001" && $art<="699") {
									$animals="birds";
									$animalsDataField=$animals;
									$speciesFieldName="species";
								}
								elseif ($art>="700" && $art<="799") {
									$animals="mammals";
									if ($mammalsMode=="T") {
										$animalsDataField="mammalsOnRoad";
										$speciesFieldName="speciesMammalsOnRoad";
									}
									else {
										$animalsDataField="mammals";
										$speciesFieldName="speciesMammals";
									}

								}
								elseif ($art>="800" && $art<="899") {
									$animals="amphibians";
									$animalsDataField=$animals;
									$speciesFieldName="speciesAmphibians";
								}

								break;
						}



						$data_field[$animalsDataField].='{';

						$arrP=array();
						$arrL=array();

						$colP="B";
						$colL="J";
						for ($iP=1; $iP<=$nbPts ; $iP++) {
							$arrP[$iP]=($worksheet->getCell($colP.$iRowSpecies)->getValue()!="" ? $worksheet->getCell($colP.$iRowSpecies)->getValue() : 0);
							$arrL[$iP]=($worksheet->getCell($colL.$iRowSpecies)->getValue()!="" ? $worksheet->getCell($colL.$iRowSpecies)->getValue() : 0);

							$data_field[$animalsDataField].='"P'.str_pad($iP, 2, '0', STR_PAD_LEFT).'": "'.$arrP[$iP].'",
							';
							$data_field[$animalsDataField].='"L'.str_pad($iP, 2, '0', STR_PAD_LEFT).'": "'.$arrL[$iP].'",
							';

							$colP++;
							$colL++;
						}

						switch($protocol) {
							case "std":
								// total - ind
								$arrP[0]=$worksheet->getCell("S".$iRowSpecies)->getOldCalculatedValue();
								$arrL[0]=$worksheet->getCell("T".$iRowSpecies)->getOldCalculatedValue();
								$IC=$arrP[0]+$arrL[0];

								$data_field[$animalsDataField].='"pointCount" : '.$arrP[0].',
								';
								$data_field[$animalsDataField].='"lineCount" : '.$arrL[0].',
								';
								break;
							case "vinter":
								break;
							case "natt":
								$IC=$worksheet->getCell("X".$iRowSpecies)->getOldCalculatedValue();

								break;
						}


						if (isset($array_species_art[$art])) {
							$speciesFound++;
							$listId=$commonFields["listSpeciesId"][$animals];
							$sn=$array_species_art[$art]["sn"];
							$guid=$array_species_art[$art]["lsid"];
							$name=$array_species_art[$art]["nameSWE"];
						}
						else {
							if ($debug) echo consoleMessage("error", "No species guid for art ".$art);
							$speciesNotFound++;
							$listId="error-unmatched";
							$guid="";
							$name=$worksheet->getCell('A'.$iRowSpecies)->getValue();
							$sn=$worksheet->getCell('A'.$iRowSpecies)->getValue();

							$fileRefused=true;

							/*
							$array_species_guid[$animals][$rtRecords["scientificname"]]="-1";

							if (isset($arrSpeciesNotFound[$rtRecords["scientificname"]]) && $arrSpeciesNotFound[$rtRecords["scientificname"]]>0)
								$arrSpeciesNotFound[$rtRecords["scientificname"]]++;
							else $arrSpeciesNotFound[$rtRecords["scientificname"]]=1;
							*/
						}
						$outputSpeciesId=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");



						$data_field[$animalsDataField].='
							"'.$speciesFieldName.'" : {
								"listId" : "'.$listId.'",
								"commonName" : "",
								"outputSpeciesId" : "'.$outputSpeciesId.'",
								"scientificName" : "'.$sn.'",
								"name" : "'.$name.'",
								"guid" : "'.$guid.'"
							},
							';
						$data_field[$animalsDataField].='"individualCount" : '.$IC.'
								},';


						$occurenceID=generate_uniqId_format("xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx");


						$arr_json_record.='{
							"dateCreated" : ISODate("'.$date_now_tz.'"),
							"lastUpdated" : ISODate("'.$date_now_tz.'"),
							"occurrenceID" : "'.$occurenceID.'",
							"status" : "active",
							"recordedBy" : "'.$recorder_name.'",
							"rightsHolder" : "'.$commonFields["rightsHolder"].'",
							"institutionID" : "'.$commonFields["institutionID"].'",
							"institutionCode" : "'.$commonFields["institutionCode"].'",
							"basisOfRecord" : "'.$commonFields["basisOfRecord"].'",
							"datasetID" : "'.$commonFields[$protocol]["projectActivityId"].'",
							"datasetName" : "'.$commonFields[$protocol]["datasetName"].'",
							"licence" : "'.$commonFields["licence"].'",
							"locationID" : "'.$array_sites[$kartakod]["locationID"].'",
							"locationName" : "'.$array_sites[$kartakod]["locationName"].'",
							"locationRemarks" : "",
							"eventID" : "'.$eventID.'",
							"eventTime" : "'.$start_time.'",
							"eventRemarks" : "'.$eventRemarks.'",
							"notes" : "'.$notes.'",
							"guid" : "'.$guid.'",
							"name" : "'.$name.'",
							"scientificName" : "'.$sn.'",
							"multimedia" : [ ],
							"activityId" : "'.$activityId.'",
							"decimalLatitude" : '.$array_sites[$kartakod]["decimalLatitude"].',
							"decimalLongitude" : '.$array_sites[$kartakod]["decimalLongitude"].',
							"eventDate" : "'.$eventDate.'",
							"individualCount" : '.$IC.',
							"outputId" : "'.$outputId.'",
							"outputSpeciesId" : "'.$outputSpeciesId.'",
							"projectActivityId" : "'.$commonFields[$protocol]["projectActivityId"].'",
							"projectId" : "'.$commonFields[$protocol]["projectId"].'",
							"userId" : "'.$commonFields["userId"].'"
						},';

					}

					$iRowSpecies++;

				}

				// replace last comma by 
				foreach ($commonFields["listSpeciesId"] as $animals => $listId) {
					if (strlen($data_field[$animals])>0)
						$data_field[$animals][strlen($data_field[$animals])-1]=' ';
				}
				if (isset($data_field["mammalsOnRoad"]) && strlen($data_field["mammalsOnRoad"])>0) $data_field["mammalsOnRoad"][strlen($data_field["mammalsOnRoad"])-1]=' ';
				//echo "data_field: ".$data_field[$animals]."\n";



				$specific_fields="";

				switch ($protocol) {

					case "std":

						$specific_fields.='
						'.$timeOfObservation.'
						'.$distanceCovered.'
						'.$minutesSpentObserving.'
						"isGpsUsed" : "'.$isGpsUsed.'",
						"mammalObservations" : [
							'.$data_field["mammals"].'
						],
						'
						;

					break;

					case "natt":

						$specific_fields.=
						'"cloudsStart" : "'. $cloudStart.'",
						"temperatureStart" : "'. $tempStart.'",
						"windStart" : "'. $windStart.'",
						"precipitationStart" : "'. $precipStart.'",
						"cloudsEnd" : "'. $cloudEnd.'",
						"temperatureEnd" : "'. $tempEnd.'",
						"windEnd" : "'. $windEnd.'",
						"precipitationEnd" : "'. $precipEnd.'",
						"period" : "'.$per.'",'.
						$timeOfObservation.
						'
						"mammalObservations" : [
							'.$data_field["mammals"].'
						],
						"mammalsCounted" : "ja",
						"mammalObservationsOnRoad" : [
							'.$data_field["mammalsOnRoad"].'
						],
						"youngOwlObservations" : [
							'.$data_field["owls"].'
						],
						"amphibiansCounted" : "ja",
						"amphibianObservations" : [
							'.$data_field["amphibians"].'
						],';

					break;


				}
				

				$arr_json_activity.='{
					"activityId" : "'.$activityId.'",
					"assessment" : false,
					"dateCreated" : ISODate("'.$date_now_tz.'"),
					"lastUpdated" : ISODate("'.$date_now_tz.'"),
					"progress" : "planned",
					"projectActivityId" : "'.$commonFields[$protocol]["projectActivityId"].'",
					"projectId" : "'.$commonFields[$protocol]["projectId"].'",
					"projectStage" : "",
					"siteId" : "'.$array_sites[$kartakod]["locationID"].'",
					"status" : "active",
					"type" : "'.$commonFields[$protocol]["type"].'",
					"userId" : "'.$commonFields["userId"].'",
					"personId" : "'.$personId.'",
					"mainTheme" : "",
					"verificationStatus" : "not verified"
				},';

				$arr_json_output.='{
					"activityId" : "'.$activityId.'",
					"dateCreated" : ISODate("'.$date_now_tz.'"),
					"lastUpdated" : ISODate("'.$date_now_tz.'"),
					"outputId" : "'.$outputId.'",
					"status" : "active",
					"outputNotCompleted" : false,
					"data" : {
						"eventRemarks" : "'.$eventRemarks.'",
						"surveyFinishTime" : "'.$finish_time.'",
						"locationAccuracy" : 50,
						"comments" : "'.$comments.'",
						"surveyDate" : "'.$date_survey.'",
						'.$specific_fields.'
						"locationHiddenLatitude" : '.$array_sites[$kartakod]["decimalLatitude"].',
						"locationLatitude" : '.$array_sites[$kartakod]["decimalLatitude"].',
						"locationSource" : "Google maps",
						"recordedBy" : "'.$recorder_name.'",
						"helpers" : '.$helpers.',
						"surveyStartTime" : "'.$start_time.'",
						"locationCentroidLongitude" : null,
						"observations" : [
							'.$data_field["birds"].'
						],
						"location" : "'.$array_sites[$kartakod]["locationID"].'",
						"locationLongitude" : '.$array_sites[$kartakod]["decimalLongitude"].',
						"locationHiddenLongitude" : '.$array_sites[$kartakod]["decimalLongitude"].',
						"locationCentroidLatitude" : null
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
					"name" : "'.$commonFields[$protocol]["name"].'",
					"dataOrigin" : "'.$dataOrigin.'"
				},';




			}
			else {
				echo consoleMessage("error", "UNKNOW SITE");
				$fileRefused=true;
			}

		}

	}

	// replace last comma by 
	$arr_json_output[strlen($arr_json_output)-1]=' ';
	$arr_json_output.=']';
	$arr_json_activity[strlen($arr_json_activity)-1]=' ';
	$arr_json_activity.=']';
	$arr_json_record[strlen($arr_json_record)-1]=' ';
	$arr_json_record.=']';
	$arr_json_person[strlen($arr_json_person)-1]=' ';
	$arr_json_person.=']';

	//echo $arr_json_output;
	//echo "\n";

	if ($fileRefused) {
		echo consoleMessage("error", "NO FILE CREATED FOR ".$file);
	}
	elseif ($nbFiles==0) {
		echo consoleMessage("error", "NO FILE TO CREATE");
	}
	else {
		for ($i=1;$i<=4;$i++) {
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
				case 4:
					$typeO="person";
					$json=$arr_json_person;
					break;
			}
			$filename_json='excel_json_'.$database.'_'.$protocol.'_'.$typeO.'s_'.date("Y-m-d-His").'.json';
			$path='dump_json_sft_sebms/'.$database.'/'.$protocol."/".$filename_json;
			//echo 'db.'.$typeO.'.remove({"dateCreated" : {$gte: new ISODate("'.date("Y-m-d").'T01:15:31Z")}})'."\n";
			echo 'mongoimport --db ecodata --collection '.$typeO.' --jsonArray --file '.$path."\n";
			//$json = json_encode($arr_rt, JSON_UNESCAPED_SLASHES); 
			if ($fp = fopen($path, 'w')) {
				fwrite($fp, $json);
				fclose($fp);
			}
			else echo consoleMessage("error", "can't create file ".$path);

			//echo 'PATH: '.$path."\n\n";

		}

		$ratioSpecies = $speciesFound / ($speciesFound+$speciesNotFound);
	
		echo consoleMessage("info", "Species ratio found in the species lists : ".$speciesFound." / ".($speciesFound+$speciesNotFound)." = ".number_format($ratioSpecies*100, 2)."%");

		if ($ratioSpecies!=1) {
			var_dump($arrSpeciesNotFound);
		}

		echo "scp dump_json_sft_sebms/".$database."/".$protocol."/excel_json_* ubuntu@89.45.234.73:/home/ubuntu/convert-SFT-SEBMS-to-MongoDB/dump_json_sft_sebms/".$database."/".$protocol."/\n";

		if (!$debug) {
			// move files to OK folder
			foreach ($listFiles as $fil) {
				exec('mv "'.$path_excel.$fil.'" '.$path_excel."OK/");
			}
			echo consoleMessage("info", $nbFiles." files analyzed and converted in json");
		}

	}

	echo consoleMessage("info", "Script ends");
}


/*

select DISTINCT karta as sitename,  'STD' as scheme
FROM totalstandard WHERE persnr IN ('490804-2', '760602-1', '690726-1', '610308-1', '960313-1', '800829-1', '830117-1', '691226-1', '680226-1', '610226-1')
union
SELECT DISTINCT kartatx as sitename, 'NATT' as scheme
FROM totalnatt WHERE persnr IN ('490804-2', '760602-1', '690726-1', '610308-1', '960313-1', '800829-1', '830117-1', '691226-1', '680226-1', '610226-1')
UNION
SELECT DISTINCT ruta as sitename, 'KUST' as scheme
FROM totalkustfagel200 WHERE persnr IN ('490804-2', '760602-1', '690726-1', '610308-1', '960313-1', '800829-1', '830117-1', '691226-1', '680226-1', '610226-1')
order by scheme, sitename

*/
?>