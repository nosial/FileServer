<?php

    namespace FileServer\Classes\Configuration;

    use FileServer\Enums\StorageType;

    class ServerConfiguration
    {
        private string $name;
        private StorageType $storageType;
        private ?string $uploadPassword;
        private ?string $password;
        private bool $readOnly;
        private bool $filterExtensions;
        private array $allowedExtensions;
        private int $maxExecutionTime;
        private int $maxFileSize;
        private ?string $workingPath;

        /**
         * ServerConfiguration constructor.
         *
         * @param array $data The configuration data
         */
        public function __construct(array $data)
        {
            $this->name = $data['name'];
            $this->storageType = StorageType::from(mb_strtoupper($data['storage_type']));
            $this->password = $data['password'];
            $this->uploadPassword = $data['upload_password'];
            $this->readOnly = (bool)$data['read_only'];
            $this->filterExtensions = (bool)$data['filter_extensions'];
            $this->allowedExtensions = $data['allowed_extensions'];
            $this->maxExecutionTime = (int)$data['max_execution_time'];
            $this->maxFileSize = (int)$data['max_file_size'];
            $this->workingPath = $data['working_path'];
        }

        /**
         * Returns the name of the server
         *
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * Returns the storage type of the server.
         *
         * @return StorageType The storage type
         */
        public function getStorageType(): StorageType
        {
            return $this->storageType;
        }

        /**
         * Returns the password for the server.
         *
         * @return string|null The password or null if not set
         */
        public function getPassword(): ?string
        {
            return $this->password;
        }

        /**
         * Returns the upload password or null if not set
         *
         * @return string|null
         */
        public function getUploadPassword(): ?string
        {
            return $this->uploadPassword;
        }

        /**
         * Returns true if the server is in read-only mode.
         *
         * @return bool True if read-only, false otherwise
         */
        public function isReadOnly(): bool
        {
            return $this->readOnly;
        }

        /**
         * Returns true if the server is filtering file extensions.
         *
         * @return bool True if filtering extensions, false otherwise
         */
        public function isFilteringExtensions(): bool
        {
            return $this->filterExtensions;
        }

        /**
         * Returns the allowed file extensions.
         *
         * @return array The allowed file extensions
         */
        public function getAllowedExtensions(): array
        {
            return $this->allowedExtensions;
        }

        /**
         * Returns the maximum execution time in seconds.
         *
         * @return int The maximum execution time
         */
        public function getMaxExecutionTime(): int
        {
            return $this->maxExecutionTime;
        }

        /**
         * Returns the maximum file size in bytes.
         *
         * @return int The maximum file size
         */
        public function getMaxFileSize(): int
        {
            return $this->maxFileSize;
        }

        /**
         * Returns the working path for working with temporary files, if null the method will return the system's
         * temporary directory instead.
         *
         * @return string The working path
         */
        public function getWorkingPath(): string
        {
            if($this->workingPath === null)
            {
                return sys_get_temp_dir();
            }

            return $this->workingPath;
        }
    }