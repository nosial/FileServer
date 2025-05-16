<?php

    namespace FileServer\Classes\Configuration;

    class SqlDatabaseConfiguration
    {
        private string $host;
        private int $port;
        private string $username;
        private string $password;
        private string $database;

        /**
         * Constructs the SqlDatabaseConfiguration object
         *
         * @param array $data
         */
        public function __construct(array $data)
        {
            $this->host = $data['host'];
            $this->port = (int)$data['port'];
            $this->username = $data['username'];
            $this->password = $data['password'];
            $this->database = $data['database'];
        }

        /**
         * Returns the host of the database
         *
         * @return string
         */
        public function getHost(): string
        {
            return $this->host;
        }

        /**
         * Returns the port of the database
         *
         * @return int
         */
        public function getPort(): int
        {
            return $this->port;
        }

        /**
         * Returns the username of the database
         *
         * @return string
         */
        public function getUsername(): string
        {
            return $this->username;
        }

        /**
         * Returns the password of the database
         *
         * @return string
         */
        public function getPassword(): string
        {
            return $this->password;
        }

        /**
         * Returns the database name
         *
         * @return string
         */
        public function getDatabase(): string
        {
            return $this->database;
        }

        /**
         * Returns the full DSN connection string of the database
         *
         * @return string
         */
        public function getDsn(): string
        {
            return sprintf('mysql:host=%s;port=%d;dbname=%s', $this->host, $this->port, $this->database);
        }
    }