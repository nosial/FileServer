<?php

    namespace FileServer\Classes\Configuration;

    use FileServer\Enums\DatabaseDriverType;

    class DatabaseConfiguration
    {
        private DatabaseDriverType $driver;
        private SqlDatabaseConfiguration $sqlDatabaseConfiguration;
        private SqliteDatabaseConfiguration $sqliteDatabaseConfiguration;

        /**
         * DatabaseConfiguration Public Constructor
         *
         * @param array $data The array data to construct with
         */
        public function __construct(array $data)
        {
            $this->driver = DatabaseDriverType::from(mb_strtoupper($data['driver']));
            $this->sqlDatabaseConfiguration = new SqlDatabaseConfiguration($data['sql']);
            $this->sqliteDatabaseConfiguration = new SqliteDatabaseConfiguration($data['sqlite']);
        }

        /**
         * Returns the driver type used for the database configuration
         *
         * @return DatabaseDriverType The driver type used
         */
        public function getDriver(): DatabaseDriverType
        {
            return $this->driver;
        }

        /**
         * Returns the SQL configuration
         *
         * @return SqlDatabaseConfiguration The SQL Configuration
         */
        public function getSqlDatabaseConfiguration(): SqlDatabaseConfiguration
        {
            return $this->sqlDatabaseConfiguration;
        }

        /**
         * Returns the SQLITE configuration
         *
         * @return SqliteDatabaseConfiguration The SQLITE Configuration
         */
        public function getSqliteDatabaseConfiguration(): SqliteDatabaseConfiguration
        {
            return $this->sqliteDatabaseConfiguration;
        }
    }