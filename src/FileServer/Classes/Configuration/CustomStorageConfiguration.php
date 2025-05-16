<?php

    namespace FileServer\Classes\Configuration;

    class CustomStorageConfiguration
    {
        private ?string $package;
        private ?string $class;
        private ?array $config;

        /**
         * CustomStorageConfiguration constructor.
         *
         * @param array $data The configuration data
         */
        public function __construct(array $data)
        {
            $this->package = $data['package'];
            $this->class = $data['class'];
            $this->config = $data['config'];
        }

        /**
         * Returns the package name of the custom storage
         *
         * @return string|null The package name
         */
        public function getPackage(): ?string
        {
            return $this->package;
        }

        /**
         * Returns the class name of the custom storage
         *
         * @return string|null The class name
         */
        public function getClass(): ?string
        {
            return $this->class;
        }

        /**
         * Returns the options for the custom storage
         *
         * @return array|null The options
         */
        public function getConfig(): ?array
        {
            return $this->config;
        }
    }