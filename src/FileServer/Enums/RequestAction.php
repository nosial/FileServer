<?php

    namespace FileServer\Enums;

    enum RequestAction
    {
        case UPLOAD;
        case DOWNLOAD;
        case LIST;
        case DELETE;

        /**
         * Attempts to parse the input from a string
         *
         * @param string $input The string input
         * @return RequestAction|null The result, null if no matches were found
         */
        public static function fromString(string $input): ?RequestAction
        {
            return match(strtolower($input))
            {
                'upload' => self::UPLOAD,
                'download' => self::DOWNLOAD,
                'list' => self::LIST,
                'delete' => self::DELETE,
                default => null
            };
        }
    }
