1. Run "composer update" command in a terminal
2. Edit .env file and put correct data into DATABASE_URL variable
3. To create the DB from the DATABASE_URL variable run the command: "php bin/console doctrine:database:create"
4. Execute migration files: php bin/console doctrine:migrations:migrate