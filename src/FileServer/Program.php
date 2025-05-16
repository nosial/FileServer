<?php

    namespace FileServer;

    use FileServer\Classes\Configuration;

    class Program
    {
        /**
         * FileServer main entry point
         *
         * @param string[] $args Command-line arguments
         * @return int Exit code
         */
        public static function main(array $args): int
        {
            Configuration::getConfigurationLib();
            return 0;
        }
    }