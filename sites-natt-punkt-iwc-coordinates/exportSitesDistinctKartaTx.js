//mongo ecodata exportSitesDistinctKartaTx.js > out.csv
/*
db.site.aggregate([
	   {"$match":{
        "projects":"d0b2f329-c394-464b-b5ab-e1e205585a7c"
     }},
    {"$group" : {
    	_id:"$kartaTx", count:{$sum:1}
    }},
    {"$project": {
        "kartaTx" : 1,
        "count" : 1
    }},
]).forEach(function(site){
  print(site._id+","+site.count);
});;
*/

/*
db.site.aggregate([
       {"$match":{
        "projects":"b7eee643-d5fe-465e-af38-36b217440bd2"
     }},
    {"$group" : {
        _id:"$dateCreated", count:{$sum:1}
    }},
    {"$project": {
        "dateCreated" : 1,
        "count" : 1
    }},
]).forEach(function(site){
  print(site._id+","+site.count);
});
*/

/*
db.site.aggregate([
       {"$match":{
        "projects":"b7eee643-d5fe-465e-af38-36b217440bd2"
     }},
    {"$group" : {
        _id:"$owner", count:{$sum:1}
    }},
    {"$project": {
        "owner" : 1,
        "count" : 1
    }},
])
*/

//mongo ecodata exportSitesDistinctKartaTx.js > out.csv
db.output.aggregate([
{"$match": {
          "status" : "active"
      }},
      {"$lookup": {
          "from": "activity",
          "localField": "activityId",
          "foreignField": "activityId",
          "as": "actID"
        }},
        {"$unwind": "$activityId"},
      {"$match": {
          "actID.projectId" : "b7eee643-d5fe-465e-af38-36b217440bd2",
          "actID.verificationStatus" : "approved"
      }},
        {"$project": {
          "data.surveyDate" :1,
          "actID.siteId": 1
      }},
      {"$lookup": {
          "from": "site",
          "localField": "actID.siteId",
          "foreignField": "siteId",
          "as": "siteID"
        }},
        {"$unwind": "$siteID"},
        {"$project": {
          "data.surveyDate" :1,
          "actID.siteId": 1,
          "siteID.adminProperties.internalSiteId": 1
      }}

  ]).forEach(function(output){
  print(output.data.surveyDate+","+output.actID[0].siteId+","+output.siteID.adminProperties.internalSiteId);
});;

//mongo ecodata exportSitesDistinctKartaTx.js > out.csv
db.output.aggregate([
{"$match": {
          "status" : "active"
      }},
      {"$lookup": {
          "from": "activity",
          "localField": "activityId",
          "foreignField": "activityId",
          "as": "actID"
        }},
        {"$unwind": "$activityId"},
      {"$match": {
          "actID.projectActivityId" : "ccace44f-c37a-44de-a586-7880128046d3",
          "actID.verificationStatus" : "approved"
      }},
        {"$project": {
          "data.period" :1,
          "data.surveyDate" :1,
          "actID.siteId": 1
      }},
      {"$lookup": {
          "from": "site",
          "localField": "actID.siteId",
          "foreignField": "siteId",
          "as": "siteID"
        }},
        {"$unwind": "$siteID"},
        {"$project": {
          "data.period" :1,
          "data.surveyDate" :1,
          "actID.siteId": 1,
          "siteID.adminProperties.internalSiteId": 1
      }}

  ]).forEach(function(output){
  print(output.data.surveyDate+","+output.actID[0].siteId+","+output.siteID.adminProperties.internalSiteId);
});;
