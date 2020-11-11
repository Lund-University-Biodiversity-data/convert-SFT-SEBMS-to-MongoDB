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


// convert a string with HHMM to
// HH:MM AM/PM (IF mode AMPM)
// examples: 
// 2342 => 11:42 PM
// 1114 => 11:14 AM
// 754 => 07:54 AM
// 15 => 00:15 AM, 
// ELSE (mode 24H) HH:MM

function convertTime($time, $mode="AMPM") {

	if ($mode=="AMPM") {
		if ($time>1200){
			$time-=1200;
			$time=str_pad($time, 4, '0', STR_PAD_LEFT);
			$time=substr($time, 0, 2).":".substr($time, 2, 2)." PM";
		}
		else {
			$time=str_pad($time, 4, '0', STR_PAD_LEFT);
			$time=substr($time, 0, 2).":".substr($time, 2, 2)." AM";
		}
	}
	else {
		$time=str_pad($time, 4, '0', STR_PAD_LEFT);
		$time=substr($time, 0, 2).":".substr($time, 2, 2);
	}
	
	return $time;
}

function consoleMessage($type, $message) {
	$msg="[".date("Y-m-d H:i:s")."] ".strtoupper($type)." : ".$message."\n";

	return $msg;
}

$county = array();

$county['AB'] = 'Stockholms län';
$county['AC'] = 'Västerbottens län';
$county['BD'] = 'Norrbottens län';
$county['C'] = 'Uppsala län';
$county['D'] = 'Södermanlands län';
$county['E'] = 'Östergötlands län';
$county['F'] = 'Jönköpings län';
$county['G'] = 'Kronobergs län';
$county['H'] = 'Kalmar län';
$county['I'] = 'Gotlands län';
$county['K'] = 'Blekinge län';
$county['M'] = 'Skåne län';
$county['N'] = 'Hallands län';
$county['O'] = 'Västra Götalands län';
$county['S'] = 'Värmlands län';
$county['T'] = 'Örebro län';
$county['U'] = 'Västmanlands län';
$county['W'] = 'Dalarnas län';
$county['X'] = 'Gävleborgs län';
$county['Y'] = 'Västernorrlands län';
$county['Z'] = 'Jämtlands län';

$province = array();

$province['ÅNG'] = 'Ångermanland';
$province['BLE'] = 'Blekinge';
$province['BOH'] = 'Bohuslän';
$province['DLR'] = 'Dalarna';
$province['DLS'] = 'Dalsland';
$province['GOT'] = 'Gotland';
$province['GST'] = 'Gästrikland';
$province['HAL'] = 'Halland';
$province['HLS'] = 'Hälsingland';
$province['HRJ'] = 'Härjedalen';
$province['JMT'] = 'Jämtland';
$province['LPÅ'] = 'Åsele lappmark';
$province['LPP'] = 'Pite lappmark';
$province['LPT'] = 'Torne lappmark';
$province['LPU'] = 'Lule lappmark';
$province['LPY'] = 'Lycksele lappmark';
$province['MPD'] = 'Medelpad';
$province['NB'] = 'Norrbotten';
$province['NRK'] = 'Närke';
$province['ÖGL'] = 'Östergötland';
$province['ÖLA'] = 'Öland';
$province['SKÅ'] = 'Skåne';
$province['SMÅ'] = 'Småland';
$province['SRM'] = 'Södermanland';
$province['UPL'] = 'Uppland';
$province['VB'] = 'Västerbotten';
$province['VGÖ'] = 'Västergötland';
$province['VRM'] = 'Värmland';
$province['VST'] = 'Västmanland';

?>