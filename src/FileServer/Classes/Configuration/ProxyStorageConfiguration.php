<?php

    namespace FileServer\Classes\Configuration;

    class ProxyStorageConfiguration
    {
        private string $endpoint;
        private ?string $uploadPassword;
        private ?string $adminPassword;
        private int $maxFileSize;
        private int $curlTimeout;

        /**
         * Constructor to initialize the ProxyConfiguration object with data from the configuration array.
         *
         * @param array $data The configuration data array containing keys 'endpoint', 'password', 'stream_chunk_size', and 'curl_timeout'.
         */
        public function __construct(array $data)
        {
            $this->endpoint = (string)$data['endpoint'];
            $this->uploadPassword = (string)$data['upload_password'] ?? null;
            $this->adminPassword = (string)$data['admin_password'] ?? null;
            $this->maxFileSize = (int)$data['max_file_size'];
            $this->curlTimeout = (int)$data['curl_timeout'];
        }

        /**
         * Returns the endpoint of the proxy server to upload to
         *
         * @return string The endpoint of the proxy server
         */
        public function getEndpoint(): string
        {
            return $this->endpoint;
        }

        /**
         * Optional. Returns the upload password if one is required to upload a file to the proxy server
         *
         * @return string|null The upload password, null if none is set.
         */
        public function getUploadPassword(): ?string
        {
            return $this->uploadPassword;
        }

        /**
         * Optional. Returns the admin password if one is required to manage the proxy server
         *
         * @return string|null The admin password, null if none is set
         */
        public function getAdminPassword(): ?string
        {
            return $this->adminPassword;
        }


        public function getMaxFileSize(): int
        {
            return $this->maxFileSize;
        }

        public function getCurlTimeout(): int
        {
            return $this->curlTimeout;
        }
    }