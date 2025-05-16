<?php

    namespace FileServer\Classes;

    class Validator
    {
        public static function sanitizeFilename(string $filename): string
        {
            // TODO: Implement this in the most secured and compatible way possible to prevent everything but the filename, excluding paths, just the filename itself.
        }

        public static function validFilename(string $filename): bool
        {
            // TODO: Implement this to check if the given filename is a valid filename and does not include anything like a sub-path or escape strings or invalid/illegal characters
        }
    }