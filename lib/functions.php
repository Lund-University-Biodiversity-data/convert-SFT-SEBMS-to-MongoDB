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

function getArraySitesFromMongo ($protocol, $projectId) {

    $array_sites=array();
    
    /**************************** connection to mongoDB   ***/
    $mng = new MongoDB\Driver\Manager($mongo["url"]); // Driver Object created

    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

    //$filter = [];
    $filter = ['projects' => $projectId];
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 
    $rows = $mng->executeQuery("ecodata.site", $query);

    foreach ($rows as $row){
        
        if ($protocol=="natt") {
            if (isset($row->kartaTx))
                $indexSite=$row->kartaTx;
            else {
                echo consoleMessage("info", "No kartaTx for site ".$row->name);
                $indexSite=$row->name;
            } 
        }
        elseif ($protocol=="kust") {
            if (isset($row->name))
                $indexSite=$row->name;
            else {
                echo consoleMessage("info", "No name for site ".$row->name);
                $indexSite=$row->name;
            } 
        }
        else {
            if (isset($row->karta))
                $indexSite=$row->karta;
            else {
                echo consoleMessage("error", "No karta for site ".$row->name);
                $indexSite=$row->name;
            } 

        }   
        $array_sites[$indexSite]=array();

        $array_sites[$indexSite]["locationID"]=$row->siteId;
        $array_sites[$indexSite]["locationName"]=$indexSite;
        $array_sites[$indexSite]["decimalLatitude"]=$row->extent->geometry->decimalLatitude;
        $array_sites[$indexSite]["decimalLongitude"]=$row->extent->geometry->decimalLongitude;

        //$array_sites_req[]="'".$indexSite."'";
    }

    /**************************** connection to mongoDB   ***/

    return $array_sites;
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


function getListUsersAlreadyHardcoded() {
    $tabUserIdPerson=array();
    //LARS
    $tabUserIdPerson[]="520228-1";
    // Mathieu
    $tabUserIdPerson[]="850419-9";
    // Fredrik
    $tabUserIdPerson[]="650802-1";
    // Martin G.
    $tabUserIdPerson[]="631106-1";
    // Åke
    $tabUserIdPerson[]="610814-1";
    // SUsanne B.
    $tabUserIdPerson[]="680226-1";
    // Thomas J
    $tabUserIdPerson[]="631120-1";
    // CHristine O.
    $tabUserIdPerson[]="610308-1";
    // Ola E.
    $tabUserIdPerson[]="760602-1";
    // Lars G.
    $tabUserIdPerson[]="610226-1";
    // Frej S
    $tabUserIdPerson[]="960313-1";

    return $tabUserIdPerson;
}

/*
function getHardCodedUserId() {
    $tabUserIdPerson=array();

    //LARS
    $tabUserIdPerson["520228-1"]=13;
    // Mathieu
    $tabUserIdPerson["850419-9"]=5;
    // Fredrik
    $tabUserIdPerson["650802-1"]=11;
    // Martin G.
    $tabUserIdPerson["631106-1"]=12;
    // Åke
    $tabUserIdPerson["610814-1"]=10;
    // SUsanne B.
    $tabUserIdPerson["680226-1"]=23;
    // Thomas J
    $tabUserIdPerson["631120-1"]=37;
    // CHristine O.
    $tabUserIdPerson["610308-1"]=26;
    // Ola E.
    $tabUserIdPerson["760602-1"]=21;
    // Lars G.
    $tabUserIdPerson["610226-1"]=20;
    // Frej S
    $tabUserIdPerson["960313-1"]=24;

}
*/
function getJsonPersonsHardcoded() {
	return '
    {
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
},
{
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
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea",
        "7d62f39e-2afd-4754-abc6-99ddbbb05f33"
    ],
    "town" : "Dalby",
    "version" : NumberLong(2),
    "userId" : "13",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2021-01-28T17:07:41.197Z"),
    "ownedSites" : [
        "2e2049d4-901f-41ec-b194-38a83b9d9e4c",
        "9fe2556a-ef23-4513-a9b9-f6b4860967e5"
    ],
    "internalPersonId" : "520228-1"
},
{
    "_id" : ObjectId("5f772f704f0ccf943f8de56c"),
    "address1" : "test",
    "bookedSites" : [
        "9e317374-2140-4020-ac98-4fa68bd76cd8"
    ],
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
    "version" : NumberLong(1),
    "userId" : "5",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2020-12-03T09:52:51.747Z"),
    "internalPersonId" : "850419-9"
},
{
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
    "userId" : "11",
        "internalPersonId" : "650802-1"
},
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
    "userId" : "12",
        "internalPersonId" : "631106-1"
},
{
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
    "version" : NumberLong(3),
    "userId" : "10",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "internalPersonId" : "610814-1",
    "lastUpdated" : ISODate("2020-12-08T16:11:59.098Z"),
    "mobileNum" : "",
    "phoneNum" : "",
    "sitesToBook" : [ ]
},
{
    "_id" : ObjectId("5f771b474f0ccf943f8de55e"),
    "address1" : "Abbekåsgatan ",
    "bookedSites" : [
        "cc007b5b-48e7-41d0-aa96-7690f6993b98",
        "82604d7d-5a67-4518-9607-555308c16687",
        "c0dc9520-1ec8-4897-9e53-6733e775729d"
    ],
    "email" : "ale.magdziarek@gmail.com",
    "firstName" : "Aleksandra",
    "lastName" : "Magdziarek",
    "personId" : "ec390109-e002-4b61-a058-c6e09506cad6",
    "phoneNum" : "0123",
    "postCode" : "21440",
    "projects" : [
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750",
        "419a734e-9ad7-4b40-a672-79d243cf8b7c",
        "7d62f39e-2afd-4754-abc6-99ddbbb05f33"
    ],
    "town" : "Malmö",
    "version" : NumberLong(20),
    "userId" : "6",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2021-03-16T07:28:54.808Z"),
    "internalPersonId" : "99999",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "b44aaec8-da0c-420d-9ff5-c575af2de750",
        "1dcacaac-9df2-4c92-a77d-53e1bba89801",
        "6401e84a-b3b0-4de0-a2c8-71c3b2b5438a",
        "ef1e2169-b6fe-4bd5-b82f-d3161d507ce2"
    ]
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069b34"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2020-12-15T13:37:37.053Z"),
    "personId" : "8719bd42-2113-8c76-6384-9b3476ca35cd",
    "firstName" : "SUSANNE",
    "lastName" : "BACKE",
    "gender" : "F",
    "birthDate" : "1968-02-26",
    "email" : "Susanne.Backe@lansstyrelsen.se",
    "phoneNum" : "0924-51091",
    "mobileNum" : "070-6266087",
    "address1" : "SÖDRA ORRBYN 100000",
    "address2" : "",
    "postCode" : "955 91",
    "town" : "RÅNEÅ",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "680226-1",
    "userId" : "23",
    "version" : NumberLong(4),
    "ownedSites" : [
        "450ed23e-21e8-40ce-a0cc-75bee6d49dce"
    ],
    "bookedSites" : [
        "299bd5c3-b7eb-48fe-a2cf-989bb38aa629"
    ],
    "extra" : "",
    "sitesToBook" : [
        ""
    ]
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069b71"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2021-02-22T23:48:27.215Z"),
    "personId" : "0134d6d7-1640-2a9c-5347-d038f6d3ca3c",
    "firstName" : "THOMAS",
    "lastName" : "JOHANSSON",
    "gender" : "M",
    "birthDate" : "1963-11-20",
    "email" : "thomas.m.johansson@lansstyrelsen.se",
    "phoneNum" : "",
    "mobileNum" : "010-2238557",
    "address1" : "LÄNSSTYRELSEN I KALMAR LÄN",
    "address2" : "",
    "postCode" : "391 89",
    "town" : "KALMAR",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "631120-1",
    "userId" : "37",
    "version" : NumberLong(1)
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069cdc"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2020-12-18T14:09:51.347Z"),
    "personId" : "d53ee587-321c-c383-1bfc-7e10e7643c0b",
    "firstName" : "CHRISTIN",
    "lastName" : "OLSSON",
    "gender" : "F",
    "birthDate" : "1961-03-08",
    "email" : "christin.olsson@edu.sundsvall.se",
    "phoneNum" : "0706-300617",
    "mobileNum" : "0706-300617",
    "address1" : "ÖSTBYN 115",
    "address2" : "",
    "postCode" : "855 98",
    "town" : "LIDEN",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "610308-1",
    "userId" : "26",
    "version" : NumberLong(1)
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069ec3"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2020-12-09T12:15:04.657Z"),
    "personId" : "31f1ef53-055f-7bc1-36e7-8cbfe86dddcf",
    "firstName" : "OLA",
    "lastName" : "ERLANDSSON",
    "gender" : "M",
    "birthDate" : "1976-06-02",
    "email" : "oerlandsson@yahoo.com",
    "phoneNum" : "070-3144340",
    "mobileNum" : "070-3144340",
    "address1" : "SJÖRÅVÄGEN 22, LGH 1204",
    "address2" : "",
    "postCode" : "907 54",
    "town" : "UMEÅ",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "760602-1",
    "userId" : "21",
    "version" : NumberLong(4),
    "bookedSites" : [
        "2ba2df00-768a-4f57-a221-09cf10d4ac52"
    ],
    "extra" : "",
    "sitesToBook" : [ ],
    "ownedSites" : [
        "74ad6a59-e766-4074-8a82-1fdec679b0ac"
    ]
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069ec8"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2020-12-09T18:48:16.198Z"),
    "personId" : "b4193241-5a52-bebe-0261-d54a567baf1a",
    "firstName" : "LARS",
    "lastName" : "GEZELIUS",
    "gender" : "M",
    "birthDate" : "1961-02-26",
    "email" : "lars.gezelius@lansstyrelsen.se",
    "phoneNum" : "070-3952285",
    "mobileNum" : "070-3952285",
    "address1" : "S. KRÄNGEVÄGEN 16",
    "address2" : "",
    "postCode" : "585 99",
    "town" : "LINKÖPING",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "610226-1",
    "userId" : "20",
    "version" : NumberLong(5),
    "bookedSites" : [
        "2bb531e5-4ff7-4db7-8dbe-e28b1c070c0e",
        "e96a1dfa-dfc8-4872-a710-826c27bda45d"
    ],
    "extra" : "Bra på fåglar",
    "sitesToBook" : [ ],
    "ownedSites" : [
        "82869b91-96e3-4273-972f-42770d312054"
    ]
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069ecd"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2020-12-21T10:02:22.462Z"),
    "personId" : "a785465a-657d-8530-0e25-a9dadf7d7aa4",
    "firstName" : "frej",
    "lastName" : "Sundqvist",
    "gender" : "",
    "birthDate" : "1996-03-13",
    "email" : "frejsundqvist@protonmail.com",
    "phoneNum" : "",
    "mobileNum" : "sdfa",
    "address1" : "AVANS BYVÄG 18",
    "address2" : "",
    "postCode" : "asdf",
    "town" : "9",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "internalPersonId" : "960313-1",
    "userId" : "24",
    "version" : NumberLong(14),
    "bookedSites" : [
        "d1ba4c70-abdb-42d9-aa69-af12b42b81a4"
    ],
    "extra" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "21c8d80e-366c-4580-932c-2d5970eadd20"
    ]
},
{
    "_id" : ObjectId("5fc9eb354f0c1783ed8bf471"),
    "address1" : "c/o Niklas Carlsson, Abbekåsgatan",
    "bookedSites" : [
        "83c3c0e7-ea64-4626-9d6d-e47e31cac759",
        "bd30e781-4d6b-426f-a1ee-f96a08c77337"
    ],
    "dateCreated" : ISODate("2020-12-04T07:54:29.865Z"),
    "email" : "ale.magdziarek@gmail.com",
    "firstName" : "Aleksandra non-admin",
    "internalPersonId" : "1985-2",
    "lastName" : "Magdziarek",
    "lastUpdated" : ISODate("2021-02-25T07:40:53.281Z"),
    "personId" : "94b81e48-357f-4745-94ef-be661e3520db",
    "phoneNum" : "0760338540",
    "postCode" : "21440",
    "projects" : [
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Malmö",
    "version" : NumberLong(7),
    "userId" : "9",
    "ownedSites" : [
        "c2b0e083-28de-4a21-a150-030a5caa1280"
    ],
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "0123",
    "sitesToBook" : [
        ""
    ]
},
{
    "_id" : ObjectId("600e76894f0c0fabcea36cf4"),
    "address1" : "test",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-01-25T07:43:05.373Z"),
    "email" : "harriet.arnberg@live.se",
    "firstName" : "Harriet",
    "internalPersonId" : "12345678",
    "lastName" : "Arnberg",
    "lastUpdated" : ISODate("2021-01-29T08:04:42.048Z"),
    "personId" : "b121f2f3-e1d0-49bf-a949-f37031ca1842",
    "postCode" : "test",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Malmö",
    "version" : NumberLong(5),
    "userId" : "29",
    "ownedSites" : [
        "4ae12a86-6777-4315-85c9-a315aa9d729e",
        "58f40df2-4fca-4ed8-989f-4df6fe4f31d1"
    ]
},
{
    "_id" : ObjectId("602116694f0c33d3bfec424e"),
    "address1" : "Per jans väg 1",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-08T10:46:01.755Z"),
    "email" : "erik.cronvall@gmail.com",
    "firstName" : "Erik",
    "internalPersonId" : "",
    "lastName" : "Cronvall",
    "lastUpdated" : ISODate("2021-02-15T08:31:51.268Z"),
    "personId" : "de01b59e-d79f-4fc7-b149-25bce81288bb",
    "postCode" : "90355",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Umeå",
    "version" : NumberLong(7),
    "userId" : "31",
    "address2" : "",
    "birthDate" : "Test",
    "extra" : "Test",
    "gender" : "",
    "mobileNum" : "0707802646",
    "phoneNum" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "be343bdb-4199-4446-b880-eda179614616"
    ]
},
{
    "_id" : ObjectId("602117ad4f0c6425ab349d8a"),
    "address1" : "Borrgatan 31",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-08T10:51:25.679Z"),
    "email" : "roger.marklund@gmail.com",
    "firstName" : "Roger",
    "internalPersonId" : "02081150",
    "lastName" : "Marklund",
    "lastUpdated" : ISODate("2021-02-10T10:39:43.460Z"),
    "personId" : "707ebf6a-cf43-4f8b-b3e3-1e3b3eabf941",
    "postCode" : "93334",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Arvidsjaur",
    "version" : NumberLong(3),
    "userId" : "32",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "phoneNum" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "e63de01b-219a-45f9-bb4e-e10338e0d293"
    ]
},
{
    "_id" : ObjectId("60241d904f0cc58f03617386"),
    "address1" : "Test",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-10T17:53:20.475Z"),
    "email" : "anna.stenstrom@lansstyrelsen.se",
    "firstName" : "Anna",
    "internalPersonId" : "02101852",
    "lastName" : "Stenström",
    "lastUpdated" : ISODate("2021-02-10T17:54:47.989Z"),
    "personId" : "60e2cb83-a710-48ce-9edf-45fae1b3b20f",
    "postCode" : "Test",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Test",
    "version" : NumberLong(1),
    "userId" : "33"
},
{
    "_id" : ObjectId("602a33a24f0c122821cc26ca"),
    "address1" : "Älvans väg 169",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-15T08:41:06.631Z"),
    "email" : "magnus.mson@gmail.com",
    "firstName" : "Magnus",
    "internalPersonId" : "d1e02ada-a1ca-49e9-8b14-b4bcba16f1b6",
    "lastName" : "Magnusson",
    "lastUpdated" : ISODate("2021-02-16T08:49:49.897Z"),
    "personId" : "d1e02ada-a1ca-49e9-8b14-b4bcba16f1b6",
    "postCode" : "90750",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Umeå",
    "version" : NumberLong(5),
    "userId" : "35",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "0730215266",
    "phoneNum" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "9540a3da-b44b-4e26-94ec-15a850ad77d3"
    ]
},
{
    "_id" : ObjectId("602af2b54f0c122821cc26d6"),
    "address1" : "Test",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-15T22:16:21.809Z"),
    "email" : "bergsand@telia.com",
    "firstName" : "Tomas",
    "internalPersonId" : "5f41e4da-e4da-4ffd-aeff-e33481048fbc",
    "lastName" : "Bergsand",
    "lastUpdated" : ISODate("2021-02-17T15:30:37.204Z"),
    "personId" : "5f41e4da-e4da-4ffd-aeff-e33481048fbc",
    "postCode" : "448 37",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Test",
    "version" : NumberLong(5),
    "userId" : "34",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "phoneNum" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "0cc3b38a-b24d-4c8e-a6be-d5fc752d6e43",
        "8759deeb-ebf9-4cec-a89c-fe5549daba99"
    ]
},
{
    "_id" : ObjectId("602b120f4f0c122821cc26dc"),
    "address1" : "Hännegår\'n",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-16T00:30:07.705Z"),
    "email" : "listrefjarilar@ckg.se",
    "firstName" : "Carin",
    "internalPersonId" : "c08606c7-339c-4823-a0e9-0c64adea0e44",
    "lastName" : "Kullberg",
    "lastUpdated" : ISODate("2021-02-17T20:55:35.317Z"),
    "personId" : "c08606c7-339c-4823-a0e9-0c64adea0e44",
    "postCode" : "999999999999",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "Dänne",
    "version" : NumberLong(7),
    "userId" : "36",
    "address2" : "",
    "birthDate" : "2132888",
    "extra" : "Väldigt besvärlig och klåfingrig kärring",
    "gender" : "Ja",
    "mobileNum" : "",
    "phoneNum" : "",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "2e20938d-708e-487c-afe0-f4350ff27218",
        "7ba923fd-4760-402c-99e2-a4bddddca655"
    ]
},
{
    "_id" : ObjectId("6037bb924f0cb7eec8afd64c"),
    "address1" : "test",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-02-25T15:00:34.547Z"),
    "email" : "piotr.borowiec@naturvardsverket.se",
    "firstName" : "Piotr",
    "internalPersonId" : "eb322777-47f7-449c-afd1-594bb2750d07",
    "lastName" : "Borowiec",
    "lastUpdated" : ISODate("2021-02-25T15:01:15.482Z"),
    "personId" : "eb322777-47f7-449c-afd1-594bb2750d07",
    "postCode" : "test",
    "projects" : [
        "1fb10915-e6c0-451e-b575-b7e715d5d32f",
        "30634be4-7aac-4ffb-8e5f-5e100ed2a4ea"
    ],
    "town" : "test",
    "version" : NumberLong(1),
    "userId" : "38"
}
,';
}
?>