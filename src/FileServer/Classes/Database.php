<?php

    namespace FileServer\Classes;

    use FileServer\Enums\DatabaseDriverType;
    use FileServer\Exceptions\DatabaseException;
    use PDO;
    use PDOException;
    use RuntimeException;

    class Database
    {
        private static ?PDO $connection;
        private static bool $initializationChecked = false;

        /**
         * Returns the PDO connection to the database.
         *
         * @return PDO The PDO connection object.
         * @throws DatabaseException If an error occurs while creating the connection.
         */
        public static function getConnection(): PDO
        {
            if(self::$connection === null)
            {
                self::createConnection();
            }

            return self::$connection;
        }

        /**
         * @return void
         * @throws DatabaseException
         */
        private static function createConnection(): void
        {
            if(Configuration::getDatabaseConfiguration()->getDriver() === DatabaseDriverType::SQL)
            {
                self::$connection = new PDO(
                    dsn: Configuration::getDatabaseConfiguration()->getSqlDatabaseConfiguration()->getDsn(),
                    username: Configuration::getDatabaseConfiguration()->getSqlDatabaseConfiguration()->getUsername(),
                    password: Configuration::getDatabaseConfiguration()->getSqlDatabaseConfiguration()->getPassword(),
                );

                // Initialization must be done after connection
                if(!self::$initializationChecked)
                {
                    self::initializeSqlDatabase();
                    self::$initializationChecked = true;
                }
            }

            if(Configuration::getDatabaseConfiguration()->getDriver() === DatabaseDriverType::SQLITE)
            {
                // Initialize the SQLite database if it doesn't exist, must be done before connection
                if(!self::$initializationChecked)
                {
                    self::initializeSqliteSDatabase();
                    self::$initializationChecked = true;
                }

                self::$connection = new PDO(
                    dsn: sprintf('sqlite:%s', Configuration::getDatabaseConfiguration()->getSqliteDatabaseConfiguration()->getPath()),
                );
            }

            // Set the error mode to exception and turn off emulated prepares
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        /**
         * Initializes the SQLite database by copying the initial database file if it doesn't exist.
         *
         * @throws DatabaseException If an error occurs while copying the database file.
         */
        private static function initializeSqliteSDatabase(): void
        {
            if(file_exists(Configuration::getDatabaseConfiguration()->getSqliteDatabaseConfiguration()->getPath()))
            {
                return;
            }

            if(!copy(
                __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'database.sqlite',
                Configuration::getDatabaseConfiguration()->getSqliteDatabaseConfiguration()->getPath()
            ))
            {
                throw new DatabaseException(sprintf('Failed to copy SQLite database file to %s', Configuration::getDatabaseConfiguration()->getSqliteDatabaseConfiguration()->getPath()));
            }
        }

        /**
         * Initializes the SQL database by creating the necessary tables if they do not exist.
         *
         * @throws DatabaseException If an error occurs while creating the tables.
         */

        private static function initializeSqlDatabase(): void
        {
            // Check

            if(!self::sqlTableExists('uploads'))
            {
                $uploadsTable = __DIR__  . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'uploads.sql';
                if(!file_exists($uploadsTable) || !is_readable($uploadsTable))
                {
                    throw new RuntimeException(sprintf('Resource file %s not found', $uploadsTable));
                }

                // Safely load the SQL file to create the table
                try
                {
                    self::$connection->exec(file_get_contents($uploadsTable));
                }
                catch(PDOException $e)
                {
                    throw new DatabaseException('Error creating uploads table: ' . $e->getMessage());
                }
            }

            if(!self::sqlTableExists('statistics'))
            {
                $statisticsTable = __DIR__  . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'statistics.sql';
                if(!file_exists($statisticsTable) || !is_readable($statisticsTable))
                {
                    throw new RuntimeException(sprintf('Resource file %s not found', $statisticsTable));
                }

                // Safely load the SQL file to create the table
                try
                {
                    self::$connection->exec(file_get_contents($statisticsTable));
                }
                catch(PDOException $e)
                {
                    throw new DatabaseException('Error creating statistics table: ' . $e->getMessage());
                }
            }
        }

        /**
         * Checks if the specified table exists in the database.
         *
         * @param string $tableName The name of the table to check.
         * @return bool True if the table exists, false otherwise.
         * @throws DatabaseException If an error occurs while checking the table existence.
         */
        private static function sqlTableExists(string $tableName): bool
        {
            try
            {
                self::$connection->query("SELECT 1 FROM $tableName LIMIT 1");
                return true;
            }
            catch(PDOException $e)
            {
                if($e->getCode() === '42S02') // Table not found
                {
                    return false;
                }

                throw new DatabaseException('Error checking table existence: ' . $e->getMessage());
            }
        }
    }