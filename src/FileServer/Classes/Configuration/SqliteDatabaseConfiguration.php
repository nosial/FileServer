<?php

    namespace FileServer\Classes\Configuration;

    class SqliteDatabaseConfiguration
    {
        private string $path;

        /**
         * Constructs the SqliteDatabaseConfiguration object
         *
         * @param array $data
         */
        public function __construct(array $data)
        {
            $this->path = $data['path'];
        }

        /**
         * Returns the path of the SQLite database
         *
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }
    }