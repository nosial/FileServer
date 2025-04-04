<?php

    namespace FileServer;

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
            print("Hello World from net.nosial.file_server!" . PHP_EOL);
            return 0;
        }
    }