# ITSM

# Installation
## Requirements:
- PHP
- Apache
- MySQL

## Installation steps

1. Place the release zip in your webroot and extract it.

2. Create a config folder and within that a sql.ini file in the folder above the webroot for ITSM.
```
[database]
servername = 
username = 
password = 
dbname = 
```

3. Fill in the details in the ini file.

4. Run scripts/itsm_install.sql on your database server 

5. Run the following command on your SQL server
```sql
INSERT INTO `itsm_ob_operators` (`id`, `lastname`, `firstname`, `email`, `phone`, `username`, `password`, `allowlogin`, `firstlineincidents`, `secondlineincidents`, `reqforchange`, `simplechange`, `extchange`, `problems`, `operations`, `assets`, `persons`, `operators`, `buildings`, `customers`, `suppliers`, `groups`, `events`, `ubm`, `reporting`, `isadmin`) VALUES
(1, 'admin', 'admin', 'admin@admin.local', '-', 'admin', '$2y$10$t5Orw2/kokDF16Nx9g9N..qKY32jOwl/vQZUVR7zKyXSTid670rle', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);
```

6. ITSM is now installed and should be ready to run. 

## First login

Username: admin

Password: admin

## Updating

**Caution: The ITSM update_db.sql script only applies the database changes since last version, don't skip versions! If an update doesn't have a update_db.sql, there are no database changes in the update**

1. To update ITSM, download the release that follows up your current release and unzip it over the current version.
2. Run scripts/update_db.sql
3. Delete update_db.sql so it can make room for the next update.