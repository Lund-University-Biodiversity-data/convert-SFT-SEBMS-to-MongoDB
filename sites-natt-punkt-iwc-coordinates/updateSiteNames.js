db.site.find({projects:"d0b2f329-c394-464b-b5ab-e1e205585a7c", name:{$regex:/centroid/}}).forEach(function(e,i) {
    e.name=e.name.replace("centroid","NATT");
    db.site.save(e);
});