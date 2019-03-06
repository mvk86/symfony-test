###### Script which will read the CSV file, parse the contents and then insert the data into a MySQL database table

In order to deploy the project on the local computer:
1. Clone this repo to your local machine
2. Go to the project root dir and run "composer update" command in a terminal
3. Edit **.env** file and put correct data into **DATABASE_URL** variable
4. To create the DB from the DATABASE_URL variable run the command: "**php bin/console doctrine:database:create**"
5. Execute migration files: **php bin/console doctrine:migrations:migrate**

To parse csv file run the **php bin/console app:parse-csv test** for testing purposes or **php bin/console app:parse-csv** to import data into DB.

_CSV file(s) that needs to be imported should be added to **csv** folder inside project root_ 