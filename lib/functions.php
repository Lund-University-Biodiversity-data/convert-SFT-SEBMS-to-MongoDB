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



function getJsonPersonsHardcoded() {
	return '
	{
    "_id" : ObjectId("5f77502a4f0ccf943f8de57f"),
    "address1" : "test",
    "bookedSites" : [ ],
    "email" : "martin.green@biol.lu.se",
    "firstName" : "Martin",
    "lastName" : "Green",
    "personId" : "aab3345e-efb6-45bc-97e5-559d3982864c",
    "postCode" : "test",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c"
    ],
    "town" : "Malmö",
    "version" : NumberLong(2),
    "userId" : "12"
},{
    "_id" : ObjectId("5f774fcb4f0ccf943f8de57d"),
    "address1" : "test",
    "bookedSites" : [ ],
    "email" : "lars.b.pettersson@gmail.com",
    "firstName" : "Lars",
    "lastName" : "Pettersson",
    "personId" : "a490bffc-9a20-4276-8219-a5b796e32a96",
    "postCode" : "test",
    "projects" : [
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "1760f3eb-5362-4e80-948d-d32156e34343",
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Dalby",
    "version" : 0,
    "userId" : "13",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2020-10-23T08:33:20.758Z")
},{
    "_id" : ObjectId("5f7aed424f0cb8483adcf93e"),
    "address1" : "test",
    "bookedSites" : [ ],
    "email" : "ake.lindstrom@biol.lu.se",
    "firstName" : "Åke",
    "lastName" : "Lindström",
    "personId" : "331b8f33-b5a7-4f1a-b69f-888e4d36b73d",
    "postCode" : "test",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c"
    ],
    "town" : "Lund",
    "version" : NumberLong(2),
    "userId" : "10"
},{
    "_id" : ObjectId("5f772f704f0ccf943f8de56c"),
    "address1" : "test",
    "bookedSites" : [ ],
    "email" : "mathieu.blanchet@biol.lu.se",
    "firstName" : "Mathieu",
    "lastName" : "Blanchet",
    "personId" : "35af2711-e759-4da9-b0f4-bdd615790289",
    "postCode" : "test",
    "projects" : [
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c"
    ],
    "town" : "Lund",
    "version" : 0,
    "userId" : "5",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2020-10-23T08:33:20.758Z")
},{
    "_id" : ObjectId("5f7eed3f4f0c7c3f0a0efca0"),
    "address1" : "test",
    "bookedSites" : [ ],
    "email" : "fredrik.haas@biol.lu.se",
    "firstName" : "Fredrik",
    "lastName" : "Haas",
    "personId" : "4363c64a-b607-4f90-b988-e5ea28dda7a6",
    "postCode" : "test",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c"
    ],
    "town" : "Lund",
    "version" : NumberLong(1),
    "userId" : "11"
},{
    "_id" : ObjectId("5f771c764f0ccf943f8de561"),
    "address1" : "street",
    "bookedSites" : [ ],
    "email" : "johan.backman@biol.lu.se",
    "firstName" : "Johan",
    "lastName" : "Bäckman",
    "personId" : "5cf25b19-0cb3-4f69-ae63-2d0ce827bbf7",
    "postCode" : "22464",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "town" : "Lund",
    "version" : NumberLong(2),
    "userId" : "14"
},{
    "_id" : ObjectId("5f771b474f0ccf943f8de55e"),
    "address1" : "Abbekåsgatan 13",
    "bookedSites" : [
        "cc007b5b-48e7-41d0-aa96-7690f6993b98"
    ],
    "email" : "ale.magdziarek@gmail.com",
    "firstName" : "Aleksandra",
    "lastName" : "Magdziarek",
    "personId" : "ec390109-e002-4b61-a058-c6e09506cad6",
    "phoneNum" : "0760338540",
    "postCode" : "21440",
    "projects" : [
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c"
    ],
    "town" : "Malmö",
    "version" : NumberLong(6),
    "userId" : "6",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2020-11-27T16:48:25.100Z"),
    "internalPersonId" : "9999",
    "sitesToBook" : [ ]
},';
}
?>