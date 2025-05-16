<?php

    namespace FileServer\Classes;

    use DateMalformedStringException;
    use DateTime;
    use FileServer\Enums\IndexStorageStatus;
    use FileServer\Enums\StorageType;
    use FileServer\Exceptions\DatabaseException;
    use FileServer\Objects\IndexStorageRecord;
    use InvalidArgumentException;
    use PDO;
    use PDOException;
    use Symfony\Component\Uid\Uuid;

    class IndexStorageManager
    {
        /**
         * Creates a new upload entry in the database. 
         *
         * @param StorageType $type The storage type of the upload. 
         * @param string|null $fileName Optional. The name of the file to be uploaded. If not provided, the UUID will be used as the filename.
         * @param int $size Optional. The size of the file to be uploaded in bytes. Default is 0.
         * @return string The UUID of the created upload entry.
         * @throws DatabaseException If the database operation fails.
         * @throws InvalidArgumentException If the provided filename is invalid or the size is negative.
         */
        public static function createUpload(StorageType $type, ?string $fileName=null, int $size=0): string
        {
            if($fileName !== null)
            {
                $fileName = Validator::sanitizeFilename($fileName);
                if(!Validator::validFilename($fileName))
                {
                    throw new InvalidArgumentException(sprintf('The given filename "%s" is invalid.', $fileName));
                }
            }

            if($size !== null && $size < 0)
            {
                throw new InvalidArgumentException(sprintf('The given filename "%s" is too small.', $fileName));
            }

            try
            {
                $uploadStmt = Database::getConnection()->prepare('INSERT INTO uploads (uuid, storage_type, name, size) VALUES (:uuid, :storage_type, :name, :size)');
                $uploadStmt->bindValue(':uuid', $uuid = Uuid::v4()->toRfc4122());
                $uploadStmt->bindValue(':storage_type', $type->value);
                $uploadStmt->bindValue(':name', $fileName);
                $uploadStmt->bindValue(':size', $size);
                $uploadStmt->execute();

                $statisticsStmt = Database::getConnection()->prepare('INSERT INTO statistics (uuid) VALUES (:uuid)');
                $statisticsStmt->bindValue(':uuid', $uuid);
                $statisticsStmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to create index.', 0, $e);
            }

            return $uuid;
        }

        /**
         * Retrieves an upload entry from the database by its UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry to retrieve.
         * @return IndexStorageRecord|null The upload entry, or null if not found.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function getUpload(string|IndexStorageRecord $uuid): ?IndexStorageRecord
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('SELECT * FROM uploads WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);
                $stmt->execute();

                if($stmt->rowCount() === 0)
                {
                    return null;
                }

                return IndexStorageRecord::fromArray($stmt->fetch(PDO::FETCH_ASSOC));
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to get index.', 0, $e);
            }
        }

        /**
         * Retrieves the download count for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @return int The download count.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function getDownloads(string|IndexStorageRecord $uuid): int
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('SELECT downloads FROM statistics WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);
                $stmt->execute();

                return (int)$stmt->fetchColumn();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to get index.', 0, $e);
            }
        }

        /**
         * Retrieves the last download date for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @return DateTime The last download date.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function getLastDownload(string|IndexStorageRecord $uuid): DateTime
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('SELECT last_download FROM statistics WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);
                $stmt->execute();

                try
                {
                    return new DateTime($stmt->fetchColumn());
                }
                catch (DateMalformedStringException $e)
                {
                    throw new DatabaseException(sprintf('Failed to parse last_download column for %s', $uuid), $e);
                }
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to get index.', 0, $e);
            }
        }

        /**
         * Checks if an upload entry exists in the database by its UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry to check.
         * @return bool True if the upload entry exists, false otherwise.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function uploadExists(string|IndexStorageRecord $uuid): bool
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM uploads WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);
                $stmt->execute();

                return (bool)$stmt->fetchColumn();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to check index existence.', 0, $e);
            }
        }

        /**
         * Deletes an upload entry from the database by its UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry to delete.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function deleteUpload(string|IndexStorageRecord $uuid): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('DELETE FROM uploads WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to delete index.', 0, $e);
            }
        }

        /**
         * Retrieves a list of upload entries from the database.
         *
         * @param int $page The page number to retrieve. Default is 1.
         * @param int $limit The number of entries per page. Default is 100.
         * @return IndexStorageRecord[] An array of IndexStorageRecord objects representing the upload entries.
         * @throws InvalidArgumentException If the provided page or limit is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function getUploads(int $page=1, int $limit=100): array
        {
            if($page < 1)
            {
                throw new InvalidArgumentException(sprintf('The given page "%s" is invalid.', $page));
            }

            if($limit < 1)
            {
                throw new InvalidArgumentException(sprintf('The given limit "%s" is invalid.', $limit));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('SELECT * FROM uploads ORDER BY created LIMIT :offset, :limit');
                $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

                $stmt->execute();

                return array_map(fn(array $row) => IndexStorageRecord::fromArray($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to get indexes.', 0, $e);
            }
        }

        public static function updateUploadName(string|IndexStorageRecord $uuid, string $name): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('UPDATE uploads SET name=:name WHERE uuid=:uuid');
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to update index size.', 0, $e);
            }
        }

        /**
         * Updates the upload size for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @param int $bytes The new size in bytes.
         * @throws InvalidArgumentException If the provided UUID is invalid or the size is negative.
         * @throws DatabaseException If the database operation fails.
         */
        public static function updateUploadSize(string|IndexStorageRecord $uuid, int $bytes): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('UPDATE uploads SET size=:size WHERE uuid=:uuid');
                $stmt->bindValue(':size', $bytes);
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to update index size.', 0, $e);
            }
        }

        /**
         * Updates the upload status for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @param IndexStorageStatus $status The new status.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function updateUploadStatus(string|IndexStorageRecord $uuid, IndexStorageStatus $status): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('UPDATE uploads SET status=:status WHERE uuid=:uuid');
                $stmt->bindValue(':status', $status->value);
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to update index status.', 0, $e);
            }
        }

        /**
         * Updates the upload pointers for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @param array $pointers The new pointers.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function updateUploadPointers(string|IndexStorageRecord $uuid, array $pointers): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('UPDATE uploads SET pointers=:pointers WHERE uuid=:uuid');
                $stmt->bindValue(':pointers', json_encode($pointers));
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to update index pointers.', 0, $e);
            }
        }

        /**
         * Increments the download count for a given UUID.
         *
         * @param string|IndexStorageRecord $uuid The UUID of the upload entry.
         * @throws InvalidArgumentException If the provided UUID is invalid.
         * @throws DatabaseException If the database operation fails.
         */
        public static function incrementDownload(string|IndexStorageRecord $uuid): void
        {
            if($uuid instanceof IndexStorageRecord)
            {
                $uuid = $uuid->getUuid();
            }
            elseif(!Uuid::isValid($uuid))
            {
                throw new InvalidArgumentException(sprintf('The given UUID "%s" is invalid.', $uuid));
            }

            try
            {
                $stmt = Database::getConnection()->prepare('UPDATE statistics SET downloads=downloads+1 AND last_download=CURRENT_TIMESTAMP WHERE uuid=:uuid');
                $stmt->bindValue(':uuid', $uuid);

                $stmt->execute();
            }
            catch (PDOException $e)
            {
                throw new DatabaseException('Failed to increment download count.', 0, $e);
            }
        }
    }