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

function getArrayPersonsFromMongo ($projectId = null) {

    global $mongoConnection;
    
    $array_persons=array();
    
    /**************************** connection to mongoDB   ***/
    $mng = new MongoDB\Driver\Manager($mongoConnection["url"]); // Driver Object created

    if ($mng) echo consoleMessage("info", "Connection to mongoDb ok");

    $filter = [];
    if (!is_null($projectId)) {
        $filter = ['projects' => $projectId];
    }
    $options = [];
    $query = new MongoDB\Driver\Query($filter, $options); 

    //db.site.find({"projects":"dab767a5-929e-4733-b8eb-c9113194201f"}, {"projects":1, "name":1}).pretty()
    // 
    $rows = $mng->executeQuery("ecodata.person", $query);

    foreach ($rows as $row){
        
        if (!isset($row->internalPersonId)) {
            echo consoleMessage("error", "No internalPersonId for ".$row->firstName." ".$row->lastName." id: ".$row->personId);

        }
        else {
            $array_persons[$row->internalPersonId]["personId"]=$row->personId;
            if (isset($row->ownedSites)) {
                $array_persons[$row->internalPersonId]["ownedSites"]=$row->ownedSites;
            }
            else $array_persons[$row->internalPersonId]["ownedSites"]="";

        }
    }

    return $array_persons;
}


function getArraySitesFromMongo ($protocol, $projectId) {

    global $mongoConnection;
    
    $array_sites=array();
    
    /**************************** connection to mongoDB   ***/
    $mng = new MongoDB\Driver\Manager($mongoConnection["url"]); // Driver Object created

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
        elseif ($protocol=="punkt" || $protocol=="vinter" || $protocol=="sommar") {
            if (isset($row->adminProperties->internalSiteId))
                $indexSite=$row->adminProperties->internalSiteId;
            else {
                echo consoleMessage("info", "No internalSiteId for site ".$row->name);
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

    // ADD OTHER THERE
    //Johan B.
    $tabUserIdPerson[]="680424-01";
    //Elin P.
    $tabUserIdPerson[]="830117-1";


    // Elin

    return $tabUserIdPerson;
}

function getJsonPersonsHardcoded() {
	return '
   {
    "_id" : ObjectId("5f77502a4f0ccf943f8de57f"),
    "address1" : "test",
    "bookedSites" : [
        "aa0772c4-cead-4902-9039-61c1768925d0",
        "36cb04e9-007f-688c-bc81-2e1063f7856f",
        "e5d03b1d-86b6-3d32-d902-fb8b42027cfd",
        "f72b744e-01f5-9e88-2163-a6c52627c22c",
        "ca8f89a4-1e8a-85e9-5982-72aefbfbdc42",
        "6baee03a-6c5d-4a63-a006-4aae0b6ee1f6",
        "c7f4ec3d-7670-9cbb-5197-700a429b0417",
        "5167e326-ab64-ee72-905a-e965e1ac3b4a",
        "e42b2197-4ce1-5832-5ce4-0ec32bb02d1f",
        "660f424a-45f3-ea8d-49b0-ff40b693500c"
    ],
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
        "50b1cb29-cf33-4d43-a805-b07ae4de1750"
    ],
    "hub": "sft",
    "town" : "Malmö",
    "version" : NumberLong(12),
    "userId" : "12",
    "internalPersonId" : "631106-1",
    "lastUpdated" : ISODate("2021-05-06T09:33:20.609Z"),
    "ownedSites" : [
        "1d227f4d-3870-4947-ba48-421352508c85",
        "97ae65bf-f798-414b-b530-28ce504efc4f",
        "71c30175-6a72-82b4-66ee-46040c622ad3",
        "e78621bc-e705-899f-5320-f099d7ca5b39",
        "48ce1e50-a6bc-0417-8153-878598284179",
        "eda44aa9-35ce-5214-abdd-97c9474e4b84",
        "e4f5ef31-f756-a652-d4e1-508ec5a3b508",
        "2d02b82c-06d2-855b-01ef-b3bfe0ba2baf",
        "32d0a8ec-14d8-f063-4b11-9853a87bc526",
        "b45ab781-39de-02de-c5b1-51cc0eec61b9",
        "3d9330e3-076a-550f-48f1-b8c5150e7886"
    ]
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
        "50b1cb29-cf33-4d43-a805-b07ae4de1750"
    ],
    "hub": "sft",
    "town" : "Lund",
    "version" : NumberLong(1),
    "userId" : "5",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2020-12-03T09:52:51.747Z"),
    "internalPersonId" : "850419-9"
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
    "hub": "sft",
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
    "hub": "sft",
    "internalPersonId" : "610308-1",
    "userId" : "26",
    "version" : NumberLong(1),
    "ownedSites" : [
        "d408156a-bee9-52d2-eef2-b26639a26106"
    ]
},
{
    "_id" : ObjectId("5fc7c01697cb50171e069ec3"),
    "dateCreated" : ISODate("2020-11-30T12:04:26.104Z"),
    "lastUpdated" : ISODate("2021-05-06T16:22:32.319Z"),
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
    "hub": "sft",
    "internalPersonId" : "760602-1",
    "userId" : "21",
    "version" : NumberLong(6),
    "bookedSites" : [
        "2ba2df00-768a-4f57-a221-09cf10d4ac52",
        "81ad9e51-08d0-45e3-be02-7bcc369ddbec",
        "119f69b5-79d8-420a-bd2d-bcaab1c7096c",
        "6daac3c9-3e19-42a9-a3b1-b1cb121fe57d",
        "d94d36a6-c11b-45cb-8c81-77aa86497e6e",
        "039eae9f-c329-4fb5-9c3c-4410c7291a66"
    ],
    "extra" : "",
    "sitesToBook" : [ ],
    "ownedSites" : [
        "2ada1807-b10e-432e-be6a-275078825179",
        "37a537db-afb3-46bf-97a3-2301f5cf7679",
        "ac3b117b-e2a6-4711-b2c7-9543ca0c223b",
        "864eabe2-6bba-4589-9155-fcf2ea7f5fee",
        "7d165bf8-8ca0-4d8d-bd7d-3252c44a1c05",
        "997fa431-6b09-4060-9e38-5d4892609833",
        "df55e005-f480-cdb6-0b35-e377ad657ee3",
        "51f7ece2-bc80-41b0-2ab1-bd873a7414b0",
        "c813a408-f41c-ae40-515f-25212e03e490",
        "ca568c2d-c047-5b12-38b2-e1c283ffb02b",
        "522f03a7-5e08-ea6e-fef2-916876692910",
        "c52cd399-5d01-6a39-1d4d-739ffc4a9ec2",
        "f146f7c4-ecc5-957b-39f5-2e897e358b3b",
        "e3b1b5be-8966-1a9b-be46-1f50518fd67d",
        "4d530db1-1507-b8cb-56a3-91a620b527d5",
        "1c23471e-8d62-8a04-7735-3bf6a264fe07"
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
    "hub": "sft",
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
        "82869b91-96e3-4273-972f-42770d312054",
        "2a234f72-3d22-4af5-88b9-040bc3d491f2",
        "13e3d929-1d2c-4f4b-82af-e677bbe95d7f",
        "d4d8d94e-d4a6-0a4c-2bb4-02e17890c096"
    ]
},
{
    "_id" : ObjectId("5f7aed424f0cb8483adcf93e"),
    "address1" : "Byvägen 9",
    "bookedSites" : [
        "90a1f603-4ecd-8382-2a38-3809ba1f6f20",
        "6e272a3b-f53a-48c9-ac5f-0da3a3122371"
    ],
    "email" : "ake.lindstrom@biol.lu.se",
    "firstName" : "Åke",
    "lastName" : "Lindström",
    "personId" : "331b8f33-b5a7-4f1a-b69f-888e4d36b73d",
    "postCode" : "247 45",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "50b1cb29-cf33-4d43-a805-b07ae4de1750"
    ],
    "hub": "sft",
    "town" : "Torna Hällestad",
    "version" : NumberLong(9),
    "userId" : "10",
    "address2" : "",
    "birthDate" : "1961",
    "extra" : "Det här är ju jättekul!",
    "gender" : "man",
    "internalPersonId" : "610814-1",
    "lastUpdated" : ISODate("2021-04-28T08:22:17.970Z"),
    "mobileNum" : "+46706975931",
    "phoneNum" : "+46706975931",
    "sitesToBook" : "",
    "ownedSites" : [
        "6ecb551f-fa12-4f34-9e88-052e5fc62439",
        "414a40c8-0ffc-44ab-8c9d-a79959b80d33",
        "5ead1d4c-f3be-4308-86e9-667bc15163da"
    ]
},
{
    "_id" : ObjectId("5fc9eb354f0c1783ed8bf471"),
    "address1" : "c/o Niklas Carlsson, Abbekåsgatan",
    "bookedSites" : [
        "ec7d90ac-c537-4f3f-b032-359cb1d914a3",
        "6aa15343-b3a1-4ac8-984d-be8b5742c77a",
        "9178ebf9-81e7-456d-a963-93b397c93fa6",
        "01c0b5c9-71ec-4afd-b394-ce80fbca8b1d"
    ],
    "dateCreated" : ISODate("2020-12-04T07:54:29.865Z"),
    "email" : "ale.magdziarek@gmail.com",
    "firstName" : "Aleksandra non-admin",
    "internalPersonId" : "1985-2",
    "lastName" : "Magdziarek",
    "lastUpdated" : ISODate("2021-05-06T08:28:06.931Z"),
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
    "hub": "sft",
    "town" : "Malmö",
    "version" : NumberLong(30),
    "userId" : "9",
    "ownedSites" : [
        "c2b0e083-28de-4a21-a150-030a5caa1280",
        "dcee0c1d-83c6-4f75-a083-12cdcc0a5fe0",
        "65c43bc0-39aa-4b3a-90c2-45f9a28b8525"
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
    "hub": "sft",
    "town" : "Malmö",
    "version" : NumberLong(5),
    "userId" : "29",
    "ownedSites" : [
        "4ae12a86-6777-4315-85c9-a315aa9d729e",
        "58f40df2-4fca-4ed8-989f-4df6fe4f31d1"
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
    "hub": "sft",
    "internalPersonId" : "680226-1",
    "userId" : "23",
    "version" : NumberLong(4),
    "ownedSites" : [
        "450ed23e-21e8-40ce-a0cc-75bee6d49dce",
        "51d0d07c-316c-41cc-a554-b78e682dee56"
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
    "hub": "sft",
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
    "hub": "sft",
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
    "hub": "sft",
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
    "hub": "sft",
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
    "hub": "sft",
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
    "hub": "sft",
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
    "hub": "sft",
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
    "_id" : ObjectId("5f7eed3f4f0c7c3f0a0efca0"),
    "address1" : "test",
    "bookedSites" : [
        "40036661-4c58-4989-87f0-99228e49207d",
        "dac48fdd-9979-498b-96fc-e647dea6f14b",
        "1587152b-712b-47c0-ac8f-1648ad7fc9e7"
    ],
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
        "50b1cb29-cf33-4d43-a805-b07ae4de1750"
    ],
    "hub": "sft",
    "town" : "Lund",
    "version" : NumberLong(3),
    "userId" : "11",
    "internalPersonId" : "650802-1",
    "lastUpdated" : ISODate("2021-05-05T19:46:14.422Z")
},
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
    "hub": "sft",
    "town" : "Lund",
    "version" : NumberLong(2),
    "userId" : "14",
    "internalPersonId" : "680424-01"
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
    "hub": "sft",
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
    "_id" : ObjectId("5f771b474f0ccf943f8de55e"),
    "address1" : "Abbekåsgatan ",
    "bookedSites" : [
        "bcafb096-816f-4057-9590-1ea0e79c8e0a",
        "a785705d-303b-42c9-9b63-e9363e139e77"
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
        "50b1cb29-cf33-4d43-a805-b07ae4de1750"
    ],
    "hub": "sft",
    "town" : "Malmö",
    "version" : NumberLong(24),
    "userId" : "6",
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "dateCreated" : ISODate("2020-10-23T08:33:20.758Z"),
    "lastUpdated" : ISODate("2021-05-12T07:26:55.333Z"),
    "internalPersonId" : "99999",
    "sitesToBook" : [
        ""
    ],
    "ownedSites" : [
        "57be5c96-f544-4180-9597-a638b00b794e",
        "f01b2602-354a-4343-9a45-b7fdf62f2d16"
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
    "hub": "sft",
    "town" : "test",
    "version" : NumberLong(1),
    "userId" : "38"
},
{
    "_id" : ObjectId("6062eedf1c5c6e492d0880f0"),
    "dateCreated" : ISODate("2021-03-29T11:39:46.992Z"),
    "lastUpdated" : ISODate("2021-05-22T16:37:23.704Z"),
    "personId" : "ad20bcfd-76bd-915d-0187-bea54b214e12",
    "firstName" : "ELIN",
    "lastName" : "PAAKKONEN",
    "gender" : "kvinna",
    "birthDate" : "1983-01-17",
    "email" : "elin.paakkonen@hotmail.com",
    "phoneNum" : "",
    "mobileNum" : "073-7214279",
    "address1" : "DALHEMSVÄGEN 3",
    "address2" : "",
    "postCode" : "691 42",
    "town" : "KARLSKOGA",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16",
        "b7eee643-d5fe-465e-af38-36b217440bd2"
    ],
    "hub": "sft",
    "internalPersonId" : "830117-1",
    "userId" : "62",
    "version" : NumberLong(6),
    "bookedSites" : [
        "60a5ac92-65a9-48f6-9230-c4d2f8fde5a7",
        "59196853-4dca-4f11-a6bc-042f644ee213"
    ],
    "extra" : "",
    "sitesToBook" : "",
    "ownedSites" : [
        "e4d2a85e-703d-4b88-891d-d8c4aa36c251"
    ]
},
{
    "_id" : ObjectId("6093ef144f0c6d5ee8cc2777"),
    "address1" : "test",
    "bookedSites" : [
        "3733d246-6af3-4268-9d2b-012a7ccd78b6"
    ],
    "dateCreated" : ISODate("2021-05-06T13:28:52.331Z"),
    "email" : "fredrik@gmail.com",
    "firstName" : "Fredrik",
    "internalPersonId" : "650802-2",
    "lastName" : "the volunteer",
    "lastUpdated" : ISODate("2021-05-06T13:57:52.358Z"),
    "personId" : "da34267c-4d94-4e89-a216-5ac66e9521fd",
    "postCode" : "test",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16"
    ],
    "hub": "sft",
    "town" : "test",
    "version" : NumberLong(3),
    "address2" : "",
    "birthDate" : "",
    "extra" : "",
    "gender" : "",
    "mobileNum" : "",
    "phoneNum" : "",
    "sitesToBook" : "",
    "userId" : "58"
},
{
    "_id" : ObjectId("60a7a0e74f0cf88439f753ad"),
    "address1" : "Xgatan 1",
    "bookedSites" : [ ],
    "dateCreated" : ISODate("2021-05-21T12:00:39.821Z"),
    "email" : "biocollect-test@biodiversitydata.se",
    "firstName" : "Test",
    "gender" : "annat",
    "internalPersonId" : "03ec61d4-8c37-45d8-8be7-32b1b5b65a31",
    "lastName" : "Volunteer",
    "lastUpdated" : ISODate("2021-05-21T12:04:51.669Z"),
    "personId" : "03ec61d4-8c37-45d8-8be7-32b1b5b65a31",
    "phoneNum" : "11111111111111111",
    "postCode" : "1111",
    "projects" : [
        "89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c",
        "d0b2f329-c394-464b-b5ab-e1e205585a7c",
        "b7eee643-d5fe-465e-af38-36b217440bd2",
        "49f55dc1-a63a-4ebf-962b-4d486db0ab16"
    ],
    "hub": "sft",
    "town" : "Lund",
    "version" : NumberLong(1),
    "userId" : "64"
},
';
}
?>