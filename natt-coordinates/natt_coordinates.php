<?php
$database="SFT";
require "../lib/config.php";
require "../lib/functions.php";
//require_once "lib/PHPExcel/Classes/PHPExcel.php";
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

echo consoleMessage("info", "Script starts");

echo consoleMessage("info", "DEBUG example command :");
echo consoleMessage("info", "php natt_coordinates.php");

$debug=false;

if (isset($argv[1]) && $argv[1]=="debug") {
	$debug=true;
	echo consoleMessage("info", "DEBUG mode");
}

define("MINWESTVALUE", 148000);
define("MAXWESTVALUE", 1875000);
define("MINNORTHVALUE", 643000);
define("MAXNORTHVALUE", 7547000);

$arrLinesToAdd=array();

$path_excel_to_scan="KoordinatfilerNatt/";
$path_excel_result="NattPOSITIONSMASTER.xls";

$filesSurveys = scandir($path_excel_to_scan);

$nbFiles=0;
$listFiles=array();
$recapFiles=array();
$nbOK=0;

$fileRefused=false;

$kartaTxReadyToAdd=array();

foreach($filesSurveys as $file) {

	if ($file!=".." && $file!="." && (substr($file, strlen($file)-4, 4)==".xls" ||substr($file, strlen($file)-5, 5)==".xlsx")) {

		$nbFiles++;
		$listFiles[]=$file;

		if (isset($limitFiles) && $limitFiles<$nbFiles) break;

		$tmpfname = $path_excel_to_scan.$file;
		echo consoleMessage("info", "Opens file ".$tmpfname);

		/*
		$excelReader = PHPExcel_IOFactory::createReaderForFile($tmpfname);
		$excelObj = $excelReader->load($tmpfname);
		*/
		$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmpfname);
		$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
		$spreadsheet = $excelObj->load($tmpfname);
		$worksheet = $spreadsheet->getSheet(0);

		$iSheet=0;

		//$sheetCount = $excelObj->getSheetCount();

		//while ($iSheet < $sheetCount) {
			//$worksheet = $excelObj->getSheet($iSheet);

			echo consoleMessage("info", "Worksheet ".$iSheet);

			$iR=12;

			$arrLines=array();

			$okCoord=true;
			$okRuttNamn=true;

			while ($okCoord && $iR<=31 && $worksheet->getCell('A'.$iR)->getValue()!="" && is_numeric($worksheet->getCell('A'.$iR)->getValue())) {
				$line=array();

				if ($worksheet->getCell('B'.$iR)->getValue() > $worksheet->getCell('C'.$iR)->getValue()) {
					$wekoord=$worksheet->getCell('C'.$iR)->getValue();
					$nskoord=$worksheet->getCell('B'.$iR)->getValue();
				}
				else {
					$wekoord=$worksheet->getCell('B'.$iR)->getValue();
					$nskoord=$worksheet->getCell('C'.$iR)->getValue();
				}

				// control check on coordinates
				if ($wekoord<MINWESTVALUE || $wekoord>MAXWESTVALUE || $nskoord<MINNORTHVALUE || $nskoord>MAXNORTHVALUE) {
					$okCoord=false;
				}

				$okKartaTx=false;

				if (trim($worksheet->getCell('E'.$iR)->getCalculatedValue())!="" and strlen(trim($worksheet->getCell('E'.$iR)->getCalculatedValue()))==5) {
					if (trim($worksheet->getCell('C3')->getCalculatedValue())!="" && $worksheet->getCell('C3')->getCalculatedValue()==$worksheet->getCell('E'.$iR)->getCalculatedValue())
						$okKartaTx=true;
				}

				$line["punkt"]=$worksheet->getCell('A'.$iR)->getValue();
				$line["wekoord"]=$wekoord;
				$line["nskoord"]=$nskoord;
				$line["birthdate"]=$worksheet->getCell('D'.$iR)->getCalculatedValue();
				$line["rutt"]=$worksheet->getCell('E'.$iR)->getCalculatedValue();
				$line["ruttnamn"]=$worksheet->getCell('F'.$iR)->getCalculatedValue();
				$line["filename"]=$file;

				$arrLines[]=$line;

				$iR++;

			}

			if (!$okCoord) {
				echo consoleMessage("error", "Out of range coordinates (format??) ".$wekoord."/".$nskoord);
				$CR="ERROR- Out of range coordinates (format??) ".$wekoord."/".$nskoord." (".$iR.")";

			}
			elseif (!$okKartaTx) {
				echo consoleMessage("error", "kartaTx ruttkod");
				$CR="ERROR- kartaTx error";

			}
			elseif ($iR!=32) {
				echo consoleMessage("error", "Wrong ind at th end of the loop :".$iR);
				$CR="ERROR- wrong format, line number (".$iR.")";

			}
			elseif (in_array($line["rutt"], $kartaTxReadyToAdd)) {
				echo consoleMessage("error", "Duplicate in the file :".$line["rutt"]);
				$CR="ERROR- Duplicate in the file :".$line["rutt"];
			}
			else {

				foreach($arrLines as $line) {
					$arrLinesToAdd[]=$line;
				}
				$CR="OK";
				$nbOK++;

				$kartaTxReadyToAdd[]=$line["rutt"];
			}
			//$iSheet++;
		//}


		$recapLine=array();
		$recapLine["file"]=$file;
		$recapLine["CR"]=$CR;
		$recapFiles[]=$recapLine;
	}

}

//print_r($arrLinesToAdd);

if ($fileRefused || count($arrLinesToAdd)<=0) {
	echo consoleMessage("error", "NO FILE CREATED FOR ".$file);
}
else {

	$path_excel_result="NattPOSITIONSMASTER.xls";
	
	/*$excelReader = PHPExcel_IOFactory::createReaderForFile($path_excel_result);
	$excelObj = $excelReader->load($path_excel_result);
	$worksheet = $excelObj->getSheet(0);*/

	$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($path_excel_result);
	$excelObj = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
	$spreadsheet = $excelObj->load($path_excel_result);
	$worksheet = $spreadsheet->getSheet(0);

	$lastRow = $worksheet->getHighestRow();

	echo consoleMessage("info", "Nb lines to add ".count($arrLinesToAdd)." => start from ".$lastRow);

	$iR=$lastRow;
	foreach($arrLinesToAdd as $line) {
		$iR++;
//print_r($line);
		$worksheet->setCellValue('A'.$iR, $line["punkt"]);
		$worksheet->setCellValue('B'.$iR, $line["wekoord"]);
		$worksheet->setCellValue('C'.$iR, $line["nskoord"]);
		//$worksheet->setCellValue('D'.$iR, $line["birthdate"]);
		$worksheet->setCellValue('D'.$iR, $line["rutt"]);
		$worksheet->setCellValue('E'.$iR, $line["ruttnamn"]);
		$worksheet->setCellValue('H'.$iR, "?");
		$worksheet->setCellValue('I'.$iR, "Automated-mathieusscript");
		$worksheet->setCellValue('J'.$iR, $line["filename"]);
	}

	$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xls");
	$writer->save($path_excel_result);

	/*$objWriter = PHPExcel_IOFactory::createWriter($excelObj, 'Excel2007');
	$objWriter->save($path_excel_result);*/

	echo consoleMessage("info", $nbFiles." files analyzed and added to ".$path_excel_result);

	


}

foreach ($recapFiles as $id => $recap) {
	if ($recap["CR"]=="OK") {
		// move files to OK folder
		exec('mv "'.$path_excel_to_scan.$recap["file"].'" '.$path_excel_to_scan."OK/");
	}
	echo "#".$id." ".$recap["file"]. " => ".$recap["CR"]."\n";
}
echo consoleMessage("info", $nbOK." OK files among ".count($recapFiles). " => ".number_format(($nbOK*100/count($recapFiles)), 2)." %");

echo consoleMessage("info", "Script ends");
?>