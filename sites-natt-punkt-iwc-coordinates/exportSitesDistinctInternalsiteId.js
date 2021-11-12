//mongo ecodata exportSitesDistinctInternalsiteId.js > out.csv
db.site.aggregate([
	   {"$match":{
        "projects":"b7eee643-d5fe-465e-af38-36b217440bd2"
     }},
    {"$group" : {
    	_id:"$internalSiteId", count:{$sum:1}
    }},
    {"$project": {
        "internalSiteId" : 1,
        "count" : 1
    }},
]).forEach(function(site){
  print(site._id+","+site.count);
});;