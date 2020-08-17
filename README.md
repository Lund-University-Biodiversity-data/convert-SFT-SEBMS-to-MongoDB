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