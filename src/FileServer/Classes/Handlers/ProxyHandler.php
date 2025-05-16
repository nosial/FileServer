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
    use RuntimeException;

    class ProxyHandler implements UploadHandlerInterface
    {
        public const int MAX_FILENAME_LENGTH = 255;
        public const string FILENAME_PATTERN = '/^[a-zA-Z0-9_\-.]+$/';
        public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[4][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/';

        /**
         * @inheritDoc
         */
        public static function handleUpload(string $uuid): array
        {
            // Validate UUID format
            if (!preg_match(self::UUID_PATTERN, $uuid))
            {
                FileServer::basicResponse(400, 'Invalid UUID format');
                exit;
            }

            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT')
            {
                throw new UploadException('Method not allowed', 400);
            }

            // Detect upload type
            $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
            $tmpName = null;

            // Handle multipart uploads
            if ($isMultipart)
            {
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

                // Handle array format uploads
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

                if (!is_uploaded_file($tmpName))
                {
                    throw new ServerException('Invalid uploaded file');
                }
            }
            // Handle raw uploads
            else
            {
                // Check Content-Length early for raw uploads
                $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
                if ($contentLength !== null && $contentLength > Configuration::getProxyStorageConfiguration()->getMaxFileSize())
                {
                    throw new UploadException(sprintf('File size exceeds maximum limit of %d bytes', Configuration::getProxyStorageConfiguration()->getMaxFileSize()));
                }

                $fileName = self::getHeaderValue('X-Filename');
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

            // Validate filename
            if ($fileName !== null)
            {
                if (mb_strlen($fileName) > self::MAX_FILENAME_LENGTH)
                {
                    throw new ServerException("Filename exceeds " . self::MAX_FILENAME_LENGTH . " characters");
                }

                $fileName = basename($fileName);
                if (!preg_match(self::FILENAME_PATTERN, $fileName))
                {
                    throw new ServerException("Invalid filename characters");
                }
            }

            // Initialize streams and tracking
            $inputStream = $isMultipart ? fopen($tmpName, 'rb') : fopen('php://input', 'rb');
            if (!$inputStream)
            {
                throw new UploadException("Failed to open input stream");
            }

            // Initialize hash context
            $hashContext = hash_init('sha256');
            $fileSize = 0;
            $maxSize = Configuration::getProxyStorageConfiguration()->getMaxFileSize();
            $sizeExceeded = false;

            // Configure cURL
            $remoteUrl = Configuration::getProxyStorageConfiguration()->getEndpoint();
            if (!$remoteUrl)
            {
                fclose($inputStream);
                throw new UploadException("Remote endpoint not configured");
            }

            $uploadPassword = Configuration::getProxyStorageConfiguration()->getUploadPassword();

            // Setup cURL options
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $remoteUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_UPLOAD         => true,
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_READFUNCTION   => function ($ch, $fd, $length) use ($inputStream, $hashContext, &$fileSize, $maxSize, &$sizeExceeded)
                {
                    if (!is_resource($inputStream) || feof($inputStream))
                    {
                        return '';
                    }

                    $buffer = fread($inputStream, $length);
                    if ($buffer === false)
                    {
                        return '';
                    }

                    $bytesRead = strlen($buffer);
                    if ($bytesRead === 0)
                    {
                        return '';
                    }

                    $fileSize += $bytesRead;

                    if ($fileSize > $maxSize)
                    {
                        $sizeExceeded = true;
                        fclose($inputStream);
                        return '';
                    }

                    hash_update($hashContext, $buffer);
                    return $buffer;
                },
                CURLOPT_HTTPHEADER     => self::buildRemoteHeaders($fileName, $uploadPassword),
                CURLOPT_HEADER         => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 300
            ]);

            // Execute upload
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            // Parse the response header for X-UUID
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $responseHeaders = substr($response, 0, $headerSize);
            $responseUuid = null;

            foreach (explode("\r\n", $responseHeaders) as $header)
            {
                if (str_starts_with(strtolower($header), 'x-uuid:'))
                {
                    $responseUuid = trim(substr($header, strlen('X-UUID:')));
                    break;
                }
            }

            curl_close($ch);

            // Close input stream
            if (is_resource($inputStream))
            {
                fclose($inputStream);
            }

            // Handle errors
            if ($curlError)
            {
                throw new UploadException("cURL error during upload: " . $curlError);
            }

            if ($sizeExceeded)
            {
                throw new UploadException(sprintf('File size exceeds maximum limit of %d bytes', $maxSize));
            }

            if ($httpCode < 200 || $httpCode >= 300)
            {
                throw new UploadException("Remote upload failed with code $httpCode: " . ($response ?: 'No response body'));
            }

            // Update storage records
            try
            {
                if ($fileName !== null)
                {
                    IndexStorageManager::updateUploadName($uuid, $fileName);
                }

                IndexStorageManager::updateUploadSize($uuid, $fileSize);
            }
            catch (Exception $e)
            {
                throw new UploadException("Storage update failed", $e->getCode(), $e);
            }

            // Clean up temporary file
            if ($isMultipart && file_exists($tmpName))
            {
                @unlink($tmpName);
            }

            return [
                'filename' => $fileName,
                'download_url' => $response,
                'response_uuid' => $responseUuid,
            ];
        }

        /**
         * Builds the remote headers of the request
         *
         * @param string|null $fileName The file name to upload
         * @param string|null $token Optional. The upload token for Bearer authentication
         * @return string[] The result headers
         */
        private static function buildRemoteHeaders(?string $fileName, ?string $token): array
        {
            $headers = [
                'Content-Type: application/octet-stream',
            ];

            if ($fileName !== null)
            {
                $headers[] = 'X-Filename: ' . rawurlencode($fileName);
            }

            if ($token !== null)
            {
                $headers[] = 'Authorization: Bearer ' . $token;
            }

            return $headers;
        }

        /**
         * Returns the value of a header case-insensitively
         *
         * @param string $headerName The name of the header to get the value from
         * @return string|null The value of the header, null if not set.
         */
        private static function getHeaderValue(string $headerName): ?string
        {
            $headers = getallheaders();
            foreach ($headers as $key => $value)
            {
                if (strtolower($key) === strtolower($headerName))
                {
                    return $value;
                }
            }

            return null;
        }

        /**
         * @inheritDoc
         */
        public static function handleDownload(IndexStorageRecord $record): void
        {
            $pointers = $record->getPointers();
            if (empty($pointers) || !isset($pointers['download_url']))
            {
                throw new ServerException("No valid remote URL found in record");
            }

            $fileSize = $record->getSize();
            $uploadPassword = Configuration::getProxyStorageConfiguration()->getUploadPassword();

            // Set up cURL to stream the download
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $pointers['download_url'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => function($ch, $data)
                {
                    echo $data;
                    return strlen($data);
                },
                CURLOPT_HTTPHEADER => $uploadPassword !== null ? ['Authorization: Bearer ' . $uploadPassword] : [],
                CURLOPT_TIMEOUT => Configuration::getProxyStorageConfiguration()->getCurlTimeout()
            ]);

            // Set appropriate headers for the download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $record->getName() . '"');
            if ($fileSize > 0)
            {
                header('Content-Length: ' . $fileSize);
            }
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Execute the request and stream directly to output
            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!$success || ($httpCode < 200 || $httpCode >= 300))
            {
                throw new ServerException("Remote download failed: " . ($error ?: "HTTP $httpCode"), (int)$httpCode);
            }
        }

        /**
         * @inheritDoc
         */
        public static function handleDelete(IndexStorageRecord $record): void
        {
            $pointers = $record->getPointers();
            if (empty($pointers) || !isset($pointers['response_uuid']))
            {
                throw new ServerException("No valid UUID found in record for deletion");
            }

            $uuid = $pointers['response_uuid'];
            $remoteUrl = Configuration::getProxyStorageConfiguration()->getEndpoint();
            
            if (!$remoteUrl)
            {
                throw new ServerException("Remote endpoint not configured");
            }

            // Add UUID as GET parameter
            $deleteUrl = rtrim($remoteUrl, '/') . '?' . http_build_query(['uuid' => $uuid]);
            $adminPassword = Configuration::getProxyStorageConfiguration()->getAdminPassword();

            // Setup cURL options
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $deleteUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => $adminPassword !== null ? ['Authorization: Bearer ' . $adminPassword] : [],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => Configuration::getProxyStorageConfiguration()->getCurlTimeout()
            ]);

            // Execute deletion request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);

            // Handle errors
            if ($error)
            {
                throw new ServerException("cURL error during deletion: " . $error);
            }

            if ($httpCode < 200 || $httpCode >= 300)
            {
                throw new ServerException("Remote deletion failed with code $httpCode: " . ($response ?: 'No response body'));
            }
        }
    }