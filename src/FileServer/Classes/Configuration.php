<?php

    namespace FileServer\Classes;

    use FileServer\Classes\Configuration\CustomStorageConfiguration;
    use FileServer\Classes\Configuration\DatabaseConfiguration;
    use FileServer\Classes\Configuration\LocalStorageConfiguration;
    use FileServer\Classes\Configuration\ProxyStorageConfiguration;
    use FileServer\Classes\Configuration\ServerConfiguration;

    class Configuration
    {
        private static ?\ConfigLib\Configuration $configuration = null;
        private static ?ServerConfiguration $serverConfiguration = null;
        private static ?LocalStorageConfiguration $localStorageConfiguration = null;
        private static ?ProxyStorageConfiguration $proxyStorageConfiguration = null;
        private static ?CustomStorageConfiguration $customStorageConfiguration = null;
        private static ?DatabaseConfiguration $databaseConfiguration = null;

        /**
         * Initializes the default settings for the configuration and constructs the private properties
         * for the configuration class
         *
         * @return void
         */
        private static function initializeConfiguration(): void
        {
            self::$configuration = new \ConfigLib\Configuration('fileserver');
            self::$configuration->setDefault('server.name', 'FileServer');
            self::$configuration->setDefault('server.storage_type', 'local');
            self::$configuration->setDefault('server.password', null);
            self::$configuration->setDefault('server.upload_password', null);
            self::$configuration->setDefault('server.read_only', false);
            self::$configuration->setDefault('server.filter_extensions', false);
            self::$configuration->setDefault('server.allowed_extensions', ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx', 'txt']);
            self::$configuration->setDefault('server.max_execution_time', 3600); // 1 hour
            self::$configuration->setDefault('server.max_file_size', 1073741824); // 1GB
            self::$configuration->setDefault('server.working_path', '/etc/fileserver/tmp'); // If null, will default to a temporary directory

            // Database Configuration
            self::$configuration->setDefault('database.driver', 'sql');
            // PDO Configuration
            self::$configuration->setDefault('database.sql.host', '127.0.0.1');
            self::$configuration->setDefault('database.sql.port', '3306');
            self::$configuration->setDefault('database.sql.username', 'root');
            self::$configuration->setDefault('database.sql.password', 'root');
            self::$configuration->setDefault('database.sql.database', 'fileserver');
            // SQLITE Configuration
            self::$configuration->setDefault('database.sqlite.path', '/etc/fileserver/database.sqlite');

            // Local storage configuration
            self::$configuration->setDefault('local_storage.storage_directory', '/var/www/uploads');
            self::$configuration->setDefault('local_storage.max_file_size', 2147483648); // 2GB
            self::$configuration->setDefault('local_storage.max_storage_size', 536870912000); // 500GB Max storage size
            self::$configuration->setDefault('local_storage.return_content_size', true); // If True, Content-Size is returned

            // Proxy storage configuration
            self::$configuration->setDefault('proxy_storage.endpoint', 'https://proxy.example.com/upload');
            self::$configuration->setDefault('proxy_storage.upload_password', null);
            self::$configuration->setDefault('proxy_storage.admin_password', null);
            self::$configuration->setDefault('proxy_storage.max_file_size', 2147483648); // 2GB
            self::$configuration->setDefault('proxy_storage.curl_timeout', 3600); // 1 hour
            self::$configuration->setDefault('proxy_storage.return_content_size', true); // If True, Content-Size is returnedp

            // Custom storage configuration
            self::$configuration->setDefault('custom_storage.package', null);
            self::$configuration->setDefault('custom_storage.class', null);
            self::$configuration->setDefault('custom_storage.config', []);

            // Save & load the configuration
            self::$configuration->save();
            self::$serverConfiguration = new ServerConfiguration(self::$configuration->getConfiguration()['server']);
            self::$localStorageConfiguration = new LocalStorageConfiguration(self::$configuration->getConfiguration()['local_storage']);
            self::$proxyStorageConfiguration = new ProxyStorageConfiguration(self::$configuration->getConfiguration()['proxy_storage']);
            self::$customStorageConfiguration = new CustomStorageConfiguration(self::$configuration->getConfiguration()['custom_storage']);
            self::$databaseConfiguration = new DatabaseConfiguration(self::$configuration->getConfiguration()['database']);
        }

        /**
         * Returns the main ConfigurationLib instance for this instance
         *
         * @return \ConfigLib\Configuration The ConfigurationLib object
         */
        public static function getConfigurationLib(): \ConfigLib\Configuration
        {
            if(self::$configuration === null)
            {
                self::initializeConfiguration();
            }

            return self::$configuration;
        }

        /**
         * Returns the server configuration object
         *
         * @return ServerConfiguration The server configuration object
         */
        public static function getServerConfiguration(): ServerConfiguration
        {
            if(self::$serverConfiguration === null)
            {
                self::initializeConfiguration();
            }

            return self::$serverConfiguration;
        }

        /**
         * Returns the local storage configuration object
         *
         * @return LocalStorageConfiguration The local storage configuration object
         */
        public static function getLocalStorageConfiguration(): LocalStorageConfiguration
        {
            if(self::$localStorageConfiguration === null)
            {
                self::initializeConfiguration();
            }

            return self::$localStorageConfiguration;
        }

        /**
         * Returns the proxy storage configuration object
         *
         * @return ProxyStorageConfiguration The proxy storage configuration object
         */
        public static function getProxyStorageConfiguration(): ProxyStorageConfiguration
        {
            if(self::$proxyStorageConfiguration === null)
            {
                self::initializeConfiguration();
            }

            return self::$proxyStorageConfiguration;
        }

        /**
         * Returns the custom storage configuration object
         *
         * @return CustomStorageConfiguration The custom storage configuration object
         */
        public static function getCustomStorageConfiguration(): CustomStorageConfiguration
        {
            if(self::$customStorageConfiguration === null)
            {
                self::initializeConfiguration();
            }

            return self::$customStorageConfiguration;
        }

        /**
         * Returns the database configuration object
         *
         * @return DatabaseConfiguration The database configuration object
         */
        public static function getDatabaseConfiguration(): DatabaseConfiguration
        {
            if(self::$databaseConfiguration === null)
            {
                self::initializeConfiguration();
            }

            return self::$databaseConfiguration;
        }
    }