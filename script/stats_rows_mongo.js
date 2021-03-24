
print("\nPROJECT : total");
db.project.count();


print("\nSITE : total");
db.site.count();

print("\nSITE : Project ID + count + project name")
db.site.aggregate([
	{"$lookup": {
      "from": "project",
      "localField": "projects",
      "foreignField": "projectId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projects", count:{$sum:1},
        "projectname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projects" : 1,
        "projectname" : 1,
        "count" : 1
    }},
]);

print("\nACTIVITY : total");
db.activity.count();

print("\nACTIVITY : Project ID + count + project name")
db.activity.aggregate([
	{"$lookup": {
      "from": "project",
      "localField": "projectId",
      "foreignField": "projectId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projectId", count:{$sum:1},
        "projectname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectID" : 1,
        "projectname" : 1,
        "count" : 1
    }},
]);

print("\nOUTPUT : total");
db.output.count();

print("\nOUTPUT : Project ID + count + project name")
db.output.aggregate([
	{"$lookup": {
      "from": "activity",
      "localField": "activityId",
      "foreignField": "activityId",
      "as": "actID"
    }},
    {"$project" : {
        "projectId" : "$actID.projectId",
    }},
    {"$lookup": {
      "from": "project",
      "localField": "projectId",
      "foreignField": "projectId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projectId", count:{$sum:1},
        "projectname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectID" : 1,
        "projectname" : 1,
        "count" : 1
    }},
]);



print("\nRECORD : total");
db.record.count();

print("\nRECORD : Project ID + count + project name")
db.record.aggregate([
	{"$lookup": {
      "from": "project",
      "localField": "projectId",
      "foreignField": "projectId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projectId", count:{$sum:1},
        "projectname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectID" : 1,
        "projectname" : 1,
        "count" : 1
    }},
]);