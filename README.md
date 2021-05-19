# convert-SFT-SEBMS-to-MongoDB
convert-SFT-SEBMS-database-to-MongoDB

**Convert data from PostgreSQL database to MongoDB JSON objects**

**Requirements**
 - PHP 7 or greater
 - PSQL 
 - MongoDB
```
sudo apt-get install php-mongodb
```

**Install**
 - rename config.template.php in config.php
 - edit the file with your PSQL config
 
 **Execute ing the script**
```
php convert_SFT_std_natt.php
```


** check the number of rows per collections **
```
mongo ecodata < script/stats_rows_mongo.js
```


** Clean the mongo database **
 - clean all the activities and records associated to a project
 - remove the output without any activityId linked to it
```
php script/cleanMongo.php SEBMS-slinga 
php script/cleanMongo.php clean 
```
Options : "SEBMS-punktlokal", "SEBMS-slinga", "SFT-std", "SFT-natt", "SFT-vinter", "SFT-sommar"
You can as well use "clean" in case you only want to clean the database


** Update the sites owners **
php script/updateOwnedSites.php 

** access mongo database **
sudo apt-get install php-mongodb php-dom php-gd php-zip


** transform excel forms to json **
sudo apt install composer
sudo apt-get install php-mbstring
composer require phpoffice/phpspreadsheet


extracting only a sample 
mongoexport --collection=site --db=ecodata --out=sites_sample_std.json --query "{'projects':'89383d0f-9735-4fe7-8eb4-8b2e9e9b7b5c'}" --limit 20
mongoimport --db ecodata --collection site --file sites_sample_std.json