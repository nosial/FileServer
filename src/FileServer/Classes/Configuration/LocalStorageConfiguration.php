<?php

    namespace FileServer\Classes\Configuration;

    class LocalStorageConfiguration
    {
        private string $storageDirectory;
        private int $maxFileSize;
        private int $maxStorageSize;
        private bool $returnContentSize;

        /**
         * Constructor for LocalStorageConfiguration.
         *
         * @param array $data The configuration data containing 'path' and 'max_size'.
         */
        public function __construct(array $data)
        {
            $this->storageDirectory = $data['storage_directory'];
            $this->maxFileSize = $data['max_file_size'];
            $this->maxStorageSize = $data['max_storage_size'];
            $this->returnContentSize = $data['return_content_size'] ?? false;
        }

        /**
         * Get the path to the local storage directory.
         *
         * @return string The path to the local storage directory.
         */
        public function getStorageDirectory(): string
        {
            return $this->storageDirectory;
        }

        /**
         * Gets the maximum size of a file upload
         *
         * @return int The maximum size of a file that can be uploaded
         */
        public function getMaxFileSize(): int
        {
            return $this->maxFileSize;
        }

        /**
         * Gets the maximum storage size of the local storage in byets
         *
         * @return int The maximum size of the local storage
         */
        public function getMaxStorageSize(): int
        {
            return $this->maxStorageSize;
        }

        /**
         * Check if the content size should be returned.
         *
         * @return bool True if the content size should be returned, false otherwise.
         */
        public function shouldReturnContentSize(): bool
        {
            return $this->returnContentSize;
        }
    }