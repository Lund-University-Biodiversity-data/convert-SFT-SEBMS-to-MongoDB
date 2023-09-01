<?php

$database="SFT";
require dirname(__FILE__)."/../lib/config.php";
$server=DEFAULT_SERVER;
require dirname(__FILE__)."/../lib/functions.php";
require PATH_SHARED_FUNCTIONS."generic-functions.php";
require PATH_SHARED_FUNCTIONS."mongo-functions.php";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$mng = new MongoDB\Driver\Manager($mongoConnection[$server]); // Driver Object created


echo consoleMessage("info", "Script starts.");
echo consoleMessage("info", "example : php script/siteStationRegId.php std forcechange");

//$tmpfname = "script/excel/StnRegId_vs_InternalSiteId_20220922.xlsx";
$tmpfname = "script/excel/StnRegId_vs_InternalSiteId_spkt_20230901.xlsx";

$arr_protocol=array("std", "pkt", "kust");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {

	$protocol=$argv[1];
	$mode=(isset($argv[2]) ? $argv[2] : "");

	try {

		$fileRefused=false;
		$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
		//$consoleTxt.=consoleMessage("info", "Excel type ".$inputFileType);
		$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createreader($inputFileType);
		$spreadsheet = $excelObj->load($tmpfname);
		$excelObjWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $inputFileType);
		$fileUpdated=false;
		$worksheet = $spreadsheet->getSheet(0);
		
	}
	catch (\Exception $exception) {
		$fileRefused=true;
		echo consoleMessage("error", "Caught exception : ".$exception->getMessage());
		echo consoleMessage("error", "can't read Excel file ".$tmpfname);
	}


	if (!$fileRefused) {


		echo consoleMessage("info", "1- read the excel file, add the StaReg ids to the database");

		switch ($protocol) {
			case "std":
				$protocolDwC="SFTstd";
				break;
			case "pkt":
				$protocolDwC="SFTpkt";
				break;
			case "kust":
				$protocolDwC="SFTkfr";
				break;
		}
		// get all the sites
		if ($protocol=="pkt") $projectId=$commonFields["punkt"]["projectId"];
		else $projectId=$commonFields[$protocol]["projectId"];
		$arrSitesDetails=getArraySitesFromMongo($projectId, $server);
		$arrUniqueSites=array();

		// no anonymisezId for KFR
		if ($protocol!="kust") {
						
			// get all the anonymizedId
			$arrAnonymizedIds=array();
			$nbMissing=0;

			foreach ($arrSitesDetails as $isId => $dataSite) {
				if (!isset($dataSite["anonymizedId"]) || $dataSite["anonymizedId"]=="") {
					echo consoleMessage("error", "No anonymizedId in database for site ".$isId);
					$nbMissing++;
				}
				else {
					$arrAnonymizedIds[$dataSite["anonymizedId"]]=$isId;
				}
			}

			echo consoleMessage("info", $nbMissing." missing anonymizedId in database"); 
		}

		$firstRow=2;
		$nbErrors=0;
		$nbOk=0;

		for ($iR=$firstRow;$worksheet->getCell('H'.$iR)->getValue()!=""; $iR++) {

			$protocolFile=$worksheet->getCell('H'.$iR)->getValue();

			$errorInternalSiteId=false;

			if ($protocolFile==$protocolDwC) {

				$ppId=$worksheet->getCell('A'.$iR)->getValue();
				$osId=$worksheet->getCell('B'.$iR)->getValue();
				$anonymizedId=$worksheet->getCell('D'.$iR)->getValue();
				$internalSiteId=$worksheet->getCell('E'.$iR)->getValue();

				if (trim($internalSiteId)=="") {
					echo consoleMessage("warn", "No internalSiteId in file for site anonymizedId ".$anonymizedId);


					// no anonymisezId for KFR
					if ($protocol!="kust") {

						if (!isset($arrAnonymizedIds[$anonymizedId]) || trim($arrAnonymizedIds[$anonymizedId])=="") {
							echo consoleMessage("error", "No site in database matching this anonymizedId ".$anonymizedId);
							$errorInternalSiteId=true;
						}
						else {
							if (trim($internalSiteId)!="" && trim($internalSiteId)!=$arrAnonymizedIds[$anonymizedId]) {
								echo consoleMessage("error", "internalSiteId are different in database and files for anonymizedId ".$anonymizedId. " (file) #".trim($internalSiteId)."# / (database) ".$arrAnonymizedIds[$anonymizedId]);
								$errorInternalSiteId=true;
							}
							else {
								// update the file
								echo consoleMessage("info", "Update ".'E'.$iR." with ".$arrAnonymizedIds[$anonymizedId]);

								$worksheet->getCell('E'.$iR)->setValue($arrAnonymizedIds[$anonymizedId]);
								$fileUpdated=true;
							}
						}
					}

				}

				if (!$errorInternalSiteId) {


					$arrUniqueSites[]=$internalSiteId;

					$okEdit=true;

					if ((isset($arrSitesDetails[$internalSiteId]["StnRegOSId"]) 
						&& $arrSitesDetails[$internalSiteId]["StnRegOSId"]!=""
						&& $arrSitesDetails[$internalSiteId]["StnRegOSId"]!=$osId
					) || (isset($arrSitesDetails[$internalSiteId]["StnRegPPId"]) 
						&& $arrSitesDetails[$internalSiteId]["StnRegPPId"]!=""
						&& $arrSitesDetails[$internalSiteId]["StnRegPPId"]!=$ppId
					)) {

						if ($mode=="forcechange") {
							echo consoleMessage("warn", "StnRegPPId or StnRegOSId already set for ".$internalSiteId.". Value in DDB : ".$arrSitesDetails[$internalSiteId]["StnRegPPId"]."/".$arrSitesDetails[$internalSiteId]["StnRegOSId"].". Values to set : ".$ppId."/".$osId);
						}
						else {
							echo consoleMessage("error", "StnRegPPId or StnRegOSId already set for ".$internalSiteId.". Value in DDB : ".$arrSitesDetails[$internalSiteId]["StnRegPPId"]."/".$arrSitesDetails[$internalSiteId]["StnRegOSId"].". Values to set : ".$ppId."/".$osId);


							$nbErrors++;
							$okEdit=false;
						}
					}

					if ($okEdit) {

						//echo consoleMessage("info", "StnRegPPId or StnRegOSId values to set : ".$ppId."/".$osId." for internalsiteId :".$internalSiteId);

						$filter = [
				    		'adminProperties.internalSiteId' => $internalSiteId
				    	];
						
						$bulk = new MongoDB\Driver\BulkWrite;

					    $options =  ['$set' => [
					    	'adminProperties.StnRegPPId' => $ppId,
					    	'adminProperties.StnRegOSId' => $osId,
					    ]];
					    $updateOptions = ['multi' => false];
					    $bulk->update($filter, $options, $updateOptions); 
					    $result = $mng->executeBulkWrite('ecodata.site', $bulk);

					    
					    $nbOk++;
					}
				

				}
				else {
					$nbErrors++;
				}
			}



			//if ($iR%100==0) echo consoleMessage("info", $iR." line(s) processed");


		}

		if ($fileUpdated) {
			echo consoleMessage("info", "Save the file ".$tmpfname);

			$excelObjWriter->save($tmpfname);
		}

		
		echo consoleMessage("info", $nbOk." line(s) OK.");
		echo consoleMessage("info", $nbErrors." line(s) ERRORED.");

		if ($mode=="fullcheck") {
			echo consoleMessage("info", "2- check if sites have not been found in the file");

			echo consoleMessage("info", count($arrSitesDetails)." site(s) in the database for protocol ".$protocol);
			echo consoleMessage("info", count($arrUniqueSites)." site(s) in the file");

			foreach($arrSitesDetails as $iSI => $dataSite) {
				if (!in_array($iSI, $arrUniqueSites)) {
					echo consoleMessage("error", $iSI." from database is not present in the excel file");

				}
			}

		}


		echo consoleMessage("info", "script ends after ".$iR." line(s) processed");
		

	}

}

echo consoleMessage("info", "script ends.");
