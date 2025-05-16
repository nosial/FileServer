<?php

    namespace FileServer\Interfaces;

    use FileServer\Exceptions\ServerException;
    use FileServer\Exceptions\UploadException;
    use FileServer\Objects\IndexStorageRecord;

    interface UploadHandlerInterface
    {
        /**
         * Handles the upload process.
         *
         * @param string $uuid The Unique Universal Identifier of the upload
         * @throws UploadException Thrown if there was a client error during the upload
         * @throws ServerException Thrown if there was a server error during the upload
         * @return array Pointers for the uploaded file, used for retrieving the file later
         */
        public static function handleUpload(string $uuid): array;

        /**
         * Handles the download process by transmitting the file over HTTP
         *
         * @param IndexStorageRecord $record The index storage record to download
         * @throws ServerException Thrown if there was a server error during the download
         * @return void
         */
        public static function handleDownload(IndexStorageRecord $record): void;

        /**
         * Handles the process of deleting the uploaded files from the server
         *
         * @param IndexStorageRecord $record The index storage record to delete
         * @throws ServerException Thrown if there was a server error during the delete operation
         * @return void
         */
        public static function handleDelete(IndexStorageRecord $record): void;
    }