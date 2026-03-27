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

4. Run scripts/itsm_install.sql and scripts/insert_first_admin_account.sql on your database server 

5. ITSM is now installed and should be ready to run. 

## First login

Username: admin
Password: admin