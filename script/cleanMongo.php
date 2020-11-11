<?php
require "lib/functions.php";
echo consoleMessage("info", "Script start.");

$arr_protocol=array("clean", "SEBMS-punktlokal", "SEBMS-slinga", "SFT-std", "SFT-natt", "SFT-vinter", "SFT-sommar");

if (!isset($argv[1]) || !in_array(trim($argv[1]), $arr_protocol)) {
	echo consoleMessage("error", "First parameter missing: ".implode("/", $arr_protocol));
}
else {
	if ($argv[1]!="clean") {

		$explArg= explode("-", $argv[1]);

		$database=$explArg[0];
		$protocol=$explArg[1];

		echo consoleMessage("info", "Clean ".$database." => scheme ".$protocol);

		require "lib/config.php";

		$filename = "script/cleanMongo-".$argv[1].".mongo";
		if ($myfile = fopen($filename, "w")) {
			echo consoleMessage("info", "Script file created : ".$filename);

			fwrite($myfile, 'db.activity.remove({projectId:"'.$commonFields[$protocol]["projectId"].'"});'."\n");
			fwrite($myfile, 'db.record.remove({projectId:"'.$commonFields[$protocol]["projectId"].'"});'."\n");
			fclose($myfile);

			echo consoleMessage("info", "Stats script execution : ".$filename);

			echo shell_exec ( "mongo ecodata < ".$filename );

			echo consoleMessage("info", "Script executed");

		}
		else {
			echo consoleMessage("error", "Can't create script file ".$filename," please check the rights for the folder script/");
		}

	}


	echo consoleMessage("info", "Clean database with unlinked documents");
	echo shell_exec ("mongo ecodata < script/cleanMongo.mongo");

}
echo consoleMessage("info", "Script ends.");

echo consoleMessage("info", "YOU NOW HAVE TO REINDEX ALL DATA THROUGH BIOCOLLECT or ECODATA ADMIN TOOLS");

?>