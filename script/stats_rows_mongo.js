
print("\nPROJECT : total");
db.project.count();

print("\nPROJECTACTIVITY : total");
db.projectActivity.count();

print("\nPROJECTACTIVITY : Project ID + count + project name")
db.projectActivity.aggregate([
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

print("\nPROJECTACTIVITY : total");
db.activity.count();

print("\nPROJECTACTIVITY : Project ID + count + project name")
db.activity.aggregate([
  {"$lookup": {
      "from": "projectActivity",
      "localField": "projectActivityId",
      "foreignField": "projectActivityId",
      "as": "prID"
    }},
    {"$group" : {
      _id:"$projectActivityId", count:{$sum:1},
        "projectactivityname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectID" : 1,
        "projectactivityname" : 1,
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
        "projectActivityId" : "$actID.projectActivityId",
    }},
    {"$lookup": {
      "from": "projectActivity",
      "localField": "projectActivityId",
      "foreignField": "projectActivityId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projectActivityId", 
      count:{$sum:1},
      "projectactivityname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectActivityId" : 1,
        "projectactivityname" : 1,
        "count" : 1
    }},
]);



print("\nRECORD : total");
db.record.count();

print("\nRECORD : ProjectActivity ID + count + projectActivity name")
db.record.aggregate([
	{"$lookup": {
      "from": "projectActivity",
      "localField": "projectActivityId",
      "foreignField": "projectActivityId",
      "as": "prID"
    }},
    {"$group" : {
    	_id:"$projectActivityId", 
      count:{$sum:1},
      "projectactivityname" : {"$first":"$prID.name"},
    }},
    {"$project": {
        "projectActivityID" : 1,
        "projectactivityname" : 1,
        "count" : 1
    }},
]);