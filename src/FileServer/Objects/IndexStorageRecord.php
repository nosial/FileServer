<?php

    namespace FileServer\Objects;

    use DateMalformedStringException;
    use DateTime;
    use FileServer\Enums\IndexStorageStatus;
    use FileServer\Enums\StorageType;
    use FileServer\Interfaces\SerializableInterface;
    use InvalidArgumentException;

    class IndexStorageRecord implements SerializableInterface
    {
        private string $uuid;
        private IndexStorageStatus $status;
        private ?string $name;
        private int $size;
        private StorageType $storageType;
        private ?array $pointers;
        private DateTime $created;

        /**
         * IndexStorageRecord constructor.
         *
         * @param array $data The data to initialize the object with
         * @throws InvalidArgumentException
         */
        public function __construct(array $data)
        {
            $this->uuid = $data['uuid'];
            $this->status = IndexStorageStatus::from(mb_strtoupper($data['status']));
            $this->name = $data['name'];
            $this->size = $data['size'];
            $this->storageType = StorageType::from(mb_strtoupper($data['storage_type']));
            $this->pointers = $data['pointers'];

            try
            {
                $this->created = new DateTime($data['created']);
            }
            catch (DateMalformedStringException $e)
            {
                throw new InvalidArgumentException('The given created field is not a valid date.', $e->getCode(), $e);
            }
        }

        /**
         * Returns the UUID of the index storage record.
         *
         * @return string The UUID of the index storage record.
         */
        public function getUuid(): string
        {
            return $this->uuid;
        }

        /**
         * Sets the UUID of the index storage record.
         *
         * @param string $uuid The UUID of the index storage record.
         */
        public function setUuid(string $uuid): void
        {
            $this->uuid = $uuid;
        }

        /**
         * Returns the status of the index storage record.
         *
         * @return IndexStorageStatus The status of the index storage record.
         */
        public function getStatus(): IndexStorageStatus
        {
            return $this->status;
        }

        public function setStatus(IndexStorageStatus $status): void
        {
            $this->status = $status;
        }

        /**
         * Returns the name of the index storage record.
         *
         * @return string The name of the index storage record.
         */
        public function getName(): string
        {
            if($this->name === null)
            {
                return $this->uuid;
            }

            return $this->name;
        }

        /**
         * Sets the name of the index storage record.
         *
         * @param string|null $name The name of the index storage record.
         */
        public function setName(?string $name): void
        {
            $this->name = $name;
        }

        /**
         * Returns the size of the index storage record.
         *
         * @return int The size of the index storage record.
         */
        public function getSize(): int
        {
            return $this->size;
        }

        /**
         * Sets the size of the index storage record.
         *
         * @param int $size The size of the index storage record.
         */
        public function setSize(int $size): void
        {
            $this->size = $size;
        }

        /**
         * Returns the storage type of the index storage record.
         *
         * @return StorageType The storage type of the index storage record.
         */
        public function getStorageType(): StorageType
        {
            return $this->storageType;
        }

        /**
         * Sets the storage type of the index storage record.
         *
         * @param StorageType $storageType The storage type of the index storage record.
         */
        public function setStorageType(StorageType $storageType): void
        {
            $this->storageType = $storageType;
        }

        /**
         * Returns the pointers of the index storage record.
         *
         * @return array|null The pointers of the index storage record.
         */
        public function getPointers(): ?array
        {
            return $this->pointers;
        }

        /**
         * Sets the pointers of the index storage record.
         *
         * @param array|null $pointers The pointers of the index storage record.
         */
        public function setPointers(?array $pointers): void
        {
            $this->pointers = $pointers;
        }

        /**
         * Returns the created date of the index storage record.
         *
         * @return DateTime The created date of the index storage record.
         */
        public function getCreated(): DateTime
        {
            return $this->created;
        }

        /**
         * Sets the created date of the index storage record.
         *
         * @param DateTime $created The created date of the index storage record.
         */
        public function setCreated(DateTime $created): void
        {
            $this->created = $created;
        }

        /**
         * @inheritDoc
         */
        public function toArray(): array
        {
            return [
                'uuid' => $this->uuid,
                'status' => $this->status->value,
                'name' => $this->name,
                'size' => $this->size,
                'storage_type' => $this->storageType->value,
                'pointers' => $this->pointers,
                'created' => $this->created->format('Y-m-d H:i:s')
            ];
        }

        /**
         * @inheritDoc
         */
        public static function fromArray(array $data): IndexStorageRecord
        {
            return new self($data);
        }
    }