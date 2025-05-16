<?php

    namespace FileServer\Classes\Handlers;

    use Exception;
    use FileServer\Classes\Configuration;
    use FileServer\Classes\IndexStorageManager;
    use FileServer\Exceptions\ServerException;
    use FileServer\Exceptions\UploadException;
    use FileServer\FileServer;
    use FileServer\Interfaces\UploadHandlerInterface;
    use FileServer\Objects\IndexStorageRecord;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    class LocalHandler implements UploadHandlerInterface
    {
        // Configurable constants
        private const int MAX_FILENAME_LENGTH = 255;
        private const string FILENAME_PATTERN = '/^[a-zA-Z0-9_\-. ]+$/u'; // Safe characters only
        private const int BUFFER_SIZE = 8192; // 8 KB buffer size

        /**
         * @inheritDoc
         */
        public static function handleUpload(string $uuid): array
        {
            // Ensure the request is POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT')
            {
                FileServer::basicResponse(405, 'Method Not Allowed');
                exit;
            }

            $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
            $tmpName = null;
            $fileSize = 0;

            if ($isMultipart)
            {
                // Check for multiple files
                if (count($_FILES) > 1)
                {
                    throw new ServerException('Multiple files not allowed');
                }

                $fileKey = key($_FILES);
                if (!$fileKey)
                {
                    throw new ServerException('No file provided');
                }

                $file = $_FILES[$fileKey];

                // Handle array uploads
                if (is_array($file['name']))
                {
                    if (count($file['name']) > 1)
                    {
                        throw new ServerException('Multiple files not allowed');
                    }

                    $fileName = $file['name'][0];
                    $tmpName = $file['tmp_name'][0];
                    $uploadError = $file['error'][0];
                }
                else
                {
                    $fileName = $file['name'];
                    $tmpName = $file['tmp_name'];
                    $uploadError = $file['error'];
                }

                if ($uploadError !== UPLOAD_ERR_OK)
                {
                    throw new ServerException("Upload error: " . $uploadError);
                }

            }
            else
            {
                // Raw upload: get filename from headers
                // Try X-Filename header
                $fileName = self::getHeaderValue('X-Filename');

                // If not found, check Content-Disposition
                if (!$fileName)
                {
                    $contentDisposition = self::getHeaderValue('Content-Disposition');
                    if ($contentDisposition)
                    {
                        preg_match('/filename="?([^"\r\n]+)"?/', $contentDisposition, $matches);
                        if (!empty($matches[1]))
                        {
                            $fileName = $matches[1];
                        }
                    }
                }
            }

            // Validate filename length
            if (!is_null($fileName) && mb_strlen($fileName) > self::MAX_FILENAME_LENGTH)
            {
                throw new ServerException('Filename exceeds ' . self::MAX_FILENAME_LENGTH . ' characters');
            }

            // Sanitize filename: remove path info
            if(!is_null($fileName))
            {
                $fileName = basename($fileName);

                // Validate filename characters
                if (!preg_match(self::FILENAME_PATTERN, $fileName))
                {
                    throw new ServerException('Invalid filename characters');
                }
            }

            // Prepare storage path
            $baseStoragePath = Configuration::getLocalStorageConfiguration()->getStorageDirectory();
            if (!is_dir($baseStoragePath) && !mkdir($baseStoragePath, 0755, true))
            {
                throw new UploadException('Failed to create storage directory');
            }

            if(is_null($fileName))
            {
                $targetPath = $baseStoragePath . DIRECTORY_SEPARATOR . $uuid;
            }
            else
            {
                $targetPath = $baseStoragePath . DIRECTORY_SEPARATOR . $fileName;
            }

            // Initialize SHA-256 hashing context
            if ($isMultipart)
            {
                if (!is_uploaded_file($tmpName))
                {
                    throw new UploadException("Invalid uploaded file");
                }

                // Open file and stream into hash context
                $inputStream = fopen($tmpName, 'rb');
                if (!$inputStream)
                {
                    throw new UploadException("Failed to open temporary file");
                }

                $fileHandle = fopen($targetPath, 'wb');
                if (!$fileHandle)
                {
                    @fclose($inputStream);
                    @unlink($tmpName);
                    throw new UploadException("Failed to open target file for writing");
                }

                while (!feof($inputStream))
                {
                    if($fileSize > Configuration::getLocalStorageConfiguration()->getMaxFileSize())
                    {
                        fclose($inputStream);
                        fclose($fileHandle);
                        unlink($targetPath);

                        throw new UploadException(sprintf('File size exceeds maximum limit of %d bytes', Configuration::getLocalStorageConfiguration()->getMaxFileSize()));
                    }

                    $buffer = fread($inputStream, self::BUFFER_SIZE);
                    $bytesRead = strlen($buffer);

                    if ($bytesRead === 0)
                    {
                        continue;
                    }

                    fwrite($fileHandle, $buffer);
                    $fileSize += $bytesRead;
                }

                fclose($inputStream);
                fclose($fileHandle);
            }
            else
            {
                // Stream raw upload to file
                $inputStream = fopen('php://input', 'rb');
                if (!$inputStream)
                {
                    throw new UploadException('Failed to read input stream');
                }

                $fileHandle = fopen($targetPath, 'wb');
                if (!$fileHandle)
                {
                    fclose($inputStream);
                    throw new UploadException('Failed to open target file for writing');
                }

                while (!feof($inputStream))
                {
                    if($fileSize > Configuration::getLocalStorageConfiguration()->getMaxFileSize())
                    {
                        fclose($inputStream);
                        fclose($fileHandle);
                        unlink($targetPath);
    
                        throw new UploadException(sprintf('File size exceeds maximum limit of %d bytes', Configuration::getLocalStorageConfiguration()->getMaxFileSize()));
                    }

                    $buffer = fread($inputStream, self::BUFFER_SIZE);
                    $bytesRead = strlen($buffer);

                    if ($bytesRead === 0)
                    {
                        continue;
                    }

                    fwrite($fileHandle, $buffer);
                    $fileSize += $bytesRead;
                }

                fclose($inputStream);
                fclose($fileHandle);
            }

            // Update storage record
            try
            {
                if(!is_null($fileName))
                {
                    IndexStorageManager::updateUploadName($uuid, $fileName);
                }

                IndexStorageManager::updateUploadSize($uuid, $fileSize);
            }
            catch (Exception $e)
            {
                @unlink($targetPath); // Attempt to delete file
                throw new UploadException('Failed to update storage record: ' . $e->getMessage(), $e->getCode(), $e);
            }
            finally
            {
                // Clean up temporary file if it exists
                if ($isMultipart && file_exists($tmpName))
                {
                    @unlink($tmpName);
                }
            }

            // Success, return the pointers for later
            return [
                'filename' => $fileName,
                'filepath' => $targetPath,
                'size' => $fileSize,
            ];
        }

        /**
         * Helper to safely retrieve HTTP headers across server environments
         *
         * @param string $headerName The name of the header to retrieve
         * @return string|null The value of the header or null if not set
         */
        private static function getHeaderValue(string $headerName): ?string
        {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
            return $_SERVER[$key] ?? null;
        }

        /**
         * Calculates the total size of all files in the local storage directory
         *
         * @return int The total size of all files in the local storage directory in bytes
         */
        private static function calculateTotalStorageSize(): int
        {
            $storageDirectory = Configuration::getLocalStorageConfiguration()->getStorageDirectory();
            $totalSize = 0;
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageDirectory));
            foreach ($files as $file)
            {
                if ($file->isFile())
                {
                    $totalSize += $file->getSize();
                }
            }

            return $totalSize;
        }

        /**
         * @inheritDoc
         */
        public static function handleDownload(IndexStorageRecord $record): void
        {
            $filePath = $record->getPointers()['filepath'];
            if (!file_exists($filePath))
            {
                FileServer::basicResponse(404, 'File not found');
                return;
            }

            // Set appropriate headers
            header('Content-Type: application/octet-stream');
            if(Configuration::getLocalStorageConfiguration()->shouldReturnContentSize())
            {
                header('Content-Length: ' . $record->getSize());
            }

            header("Content-Disposition: attachment; filename=\"" . rawurlencode($record->getName()) . "\"");

            // Stream file in chunks
            $handle = fopen($filePath, 'rb');
            while (!feof($handle))
            {
                echo fread($handle, 8192);
                ob_flush();
                flush();
            }

            fclose($handle);
            exit;
        }

        /**
         * @inheritDoc
         */
        public static function handleDelete(IndexStorageRecord $record): void
        {
            $filePath = $record->getPointers()['filepath'];

            if (file_exists($filePath) && !@unlink($filePath))
            {
                throw new ServerException("Failed to delete file: " . $filePath);
            }

            // Clean up UUID directory if empty
            $dirPath = dirname($filePath);
            if (is_dir($dirPath) && count(scandir($dirPath)) === 2)
            {
                rmdir($dirPath);
            }
        }
    }