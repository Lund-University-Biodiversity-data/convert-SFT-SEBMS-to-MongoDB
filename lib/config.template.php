<?php
define("DEFAULT_SERVER", "PROD");
define("PATH_SHARED_FUNCTIONS", "/home/XXX/shared-functions/");
define ("PATH_DOWNLOAD", "/home/XXXX/convert-SFT-SEBMS-to-MongoDB/");

$commonFields=array();

$commonFields["userId"]=0;

$commonFields["status"]="active";
$commonFields["recordedBy"]="";
$commonFields["rightsHolder"]="";
$commonFields["institutionID"]=$commonFields["rightsHolder"];
$commonFields["institutionCode"]=$commonFields["rightsHolder"];
$commonFields["basisOfRecord"]="HumanObservation";
$commonFields["multimedia"]="[ ]";
$commonFields["licence"]="https://creativecommons.org/publicdomain/zero/1.0/";

$mongoConnection["TEST"]="mongodb://localhost";
$mongoConnection["DEV"]="mongodb://canmove-dev.ekol.lu.se";
$mongoConnection["PROD"]="mongodb://89.45.234.73";

switch ($database) {
	case "SFT":
		$DB["host"]="localhost";
		$DB["username"]="postgres";
		$DB["database"]="sft";
		$DB["password"]="";


		$commonFields["listSpeciesId"]["birds"]="dr158";
		$commonFields["listSpeciesId"]["mammals"]="dr159";
		$commonFields["listSpeciesId"]["amphibians"]="dr160";
		$commonFields["listSpeciesId"]["owls"]="dr161";

		// STANDARDRUTTERNA
		$commonFields["std"]["projectId"]="";
		$commonFields["std"]["projectActivityId"]="";
		$commonFields["std"]["datasetId"]=$commonFields["std"]["projectActivityId"];
		$commonFields["std"]["datasetName"]=""; // survey name
		$commonFields["std"]["type"]=""; // activity form
		$commonFields["std"]["name"]=""; // form section

		// NATTRUTTERNA
		// same as std with natt / vinter / sommar

		break;
	case "SEBMS":
		$DB["host"]="localhost";
		$DB["username"]="postgres";
		$DB["database"]="sebms";
		$DB["password"]="";

		$commonFields["listSpeciesId"]="dr156";

		// PUNKTLOKAL
		$commonFields["punktlokal"]["projectId"]="";
		$commonFields["punktlokal"]["projectActivityId"]="";
		$commonFields["punktlokal"]["datasetId"]=$commonFields["punktlokal"]["projectActivityId"];
		$commonFields["punktlokal"]["datasetName"]=""; // survey name
		$commonFields["punktlokal"]["datasourceID"]=[];
		$commonFields["punktlokal"]["type"]=""; // activity form
		$commonFields["punktlokal"]["name"]=""; // form section

		// SLINGA

		break;
}



?>