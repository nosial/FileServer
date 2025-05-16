<?php

    namespace FileServer\Objects;

    use FileServer\Enums\StorageType;
    use FileServer\Interfaces\SerializableInterface;

    class UploadResult implements SerializableInterface
    {
        private string $fileName;
        private int $fileSize;
        private StorageType $storageType;
        private array $pointers;

        /**
         * Constructor for the UploadResult class.
         *
         * @param string $fileName The name of the uploaded file.
         * @param int $fileSize The size of the uploaded file in bytes.
         * @param StorageType $storageType The storage type of the uploaded file.
         * @param array $pointers The pointers to the uploaded file.
         */
        public function __construct(string $fileName, int $fileSize, StorageType $storageType, array $pointers)
        {
            $this->fileName = $fileName;
            $this->fileSize = $fileSize;
            $this->storageType = $storageType;
            $this->pointers = $pointers;
        }

        /**
         * Get the name of the uploaded file.
         *
         * @return string The name of the uploaded file.
         */
        public function getFileName(): string
        {
            return $this->fileName;
        }

        /**
         * Get the size of the uploaded file.
         *
         * @return int The size of the uploaded file in bytes.
         */
        public function getFileSize(): int
        {
            return $this->fileSize;
        }

        /**
         * Get the storage type of the uploaded file.
         *
         * @return StorageType The storage type of the uploaded file.
         */
        public function getStorageType(): StorageType
        {
            return $this->storageType;
        }

        /**
         * Get the pointers to the uploaded file.
         *
         * @return array The pointers to the uploaded file.
         */
        public function getPointers(): array
        {
            return $this->pointers;
        }

        /**
         * @inheritDoc
         */
        public function toArray(): array
        {
            return [
                'file_name' => $this->fileName,
                'file_size' => $this->fileSize,
                'storage_type' => $this->storageType->value,
                'pointers' => $this->pointers
            ];
        }

        /**
         * @inheritDoc
         */
        public static function fromArray(array $data): SerializableInterface
        {
            return new self(
                $data['file_name'],
                $data['file_size'],
                StorageType::from(mb_strtoupper($data['storage_type'])),
                $data['pointers']
            );
        }
    }