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

** Clean the mongo database **
 - clean all the activities and records associated to a project
 - remove the output without any activityId linked to it
```
php script/cleanMongo.php SEBMS-slinga 
```
Options : "SEBMS-punktlokal", "SEBMS-slinga", "SFT-std", "SFT-natt", "SFT-vinter", "SFT-sommar"
You can as well use "clean" in case you only want to clean the database


** transform excel forms to json **
sudo apt-get install php-mbstring
composer require phpoffice/phpspreadsheet