<?php

    namespace FileServer;

    use Exception;
    use FileServer\Classes\Configuration;
    use FileServer\Classes\Handlers\LocalHandler;
    use FileServer\Classes\Handlers\ProxyHandler;
    use FileServer\Classes\IndexStorageManager;
    use FileServer\Enums\IndexStorageStatus;
    use FileServer\Enums\RequestAction;
    use FileServer\Enums\StorageType;
    use FileServer\Exceptions\DatabaseException;
    use FileServer\Exceptions\ServerException;
    use FileServer\Interfaces\UploadHandlerInterface;
    use InvalidArgumentException;
    use ncc\Classes\Runtime;
    use ncc\Exceptions\ImportException;

    class FileServer
    {
        /**
         * Handles the HTTP request for the FileServer
         *
         * @return void
         */
        public static function handleRequest(): void
        {
            try
            {
                switch(self::getRequestAction())
                {
                    case RequestAction::UPLOAD:
                        self::handleUploadRequest();
                        break;

                    case RequestAction::DOWNLOAD:
                        self::handleDownloadRequest();
                        break;

                    case RequestAction::DELETE:
                        self::handleDeleteRequest();
                        break;

                    case RequestAction::LIST:
                        self::handleListRequest();
                        break;

                    default:
                        self::basicResponse(400, 'Bad Request: Unknown request action');
                        break;
                }
            }
            catch(ServerException|DatabaseException $e)
            {
                self::basicResponse(500, 'Internal Server Error: ' . $e->getMessage());
            }
            catch(InvalidArgumentException $e)
            {
                self::basicResponse(400, 'Bad Request: ' . $e->getMessage());
            }
        }

        /**
         * Handles the upload request
         *
         * @return void
         * @throws DatabaseException
         */
        private static function handleUploadRequest(): void
        {
            if(Configuration::getServerConfiguration()->isReadOnly())
            {
                self::basicResponse(403, 'Forbidden: Server is in read-only mode');
                return;
            }

            if(Configuration::getServerConfiguration()->getUploadPassword() !== null)
            {
                if(self::getRequestPassword() === null)
                {
                    self::basicResponse(401, 'Unauthorized: Authentication required', [
                        sprintf("WWW-Authenticate: Bearer realm=\"%s\"", Configuration::getServerConfiguration()->getName())
                    ]);
                    return;
                }

                if(self::getRequestPassword() !== Configuration::getServerConfiguration()->getUploadPassword())
                {
                    self::basicResponse(403, 'Forbidden: Invalid upload password');
                    return;
                }
            }

            $indexStorageUuid = IndexStorageManager::createUpload(Configuration::getServerConfiguration()->getStorageType());

            try
            {
                $pointers = match (Configuration::getServerConfiguration()->getStorageType())
                {
                    StorageType::LOCAL => LocalHandler::handleUpload($indexStorageUuid),
                    StorageType::PROXY => ProxyHandler::handleUpload($indexStorageUuid),
                    StorageType::CUSTOM => function () use ($indexStorageUuid)
                    {
                        /** @var UploadHandlerInterface $class */
                        $class = self::getCustomHandler();
                        if ($class !== null)
                        {
                            yield $class::handleUpload($indexStorageUuid);
                        }

                        yield null;
                    }
                };
            }
            catch (Exceptions\ServerException $e)
            {
                IndexStorageManager::deleteUpload($indexStorageUuid);
                self::basicResponse(400, 'Bad Request: ' . $e->getMessage());
                return;
            }
            catch (Exceptions\UploadException $e)
            {
                IndexStorageManager::deleteUpload($indexStorageUuid);
                self::basicResponse(500, 'Upload Error: ' . $e->getMessage());
                return;
            }

            if(!is_array($pointers))
            {
                IndexStorageManager::deleteUpload($indexStorageUuid);
                self::basicResponse(500, 'Internal Server Error: Invalid upload handler response');
                return;
            }

            IndexStorageManager::updateUploadStatus($indexStorageUuid, IndexStorageStatus::AVAILABLE);
            IndexStorageManager::updateUploadPointers($indexStorageUuid, $pointers);

            // Generate a local URL for the uploaded file <base>/download?uuid=<uuid>
            self::basicResponse(200, sprintf('%s://%s/download?uuid=%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST'], $indexStorageUuid), [
                'Content-Type: text/plain',
                'X-UUID: ' . $indexStorageUuid
            ]);
        }

        /**
         * Handles the download request
         *
         * @return void
         * @throws ServerException Thrown if there was a server-side exception
         */
        private static function handleDownloadRequest(): void
        {
            if(!isset($_GET['uuid']))
            {
                self::basicResponse(400, 'Bad Request: Missing parameter \'uuid\'');
                return;
            }

            try
            {
                $indexStorageRecord = IndexStorageManager::getUpload($_GET['uuid']);
                if($indexStorageRecord === null)
                {
                    self::basicResponse(404, 'File Not Found');
                    return;
                }
            }
            catch(InvalidArgumentException $e)
            {
                self::basicResponse(400, 'Bad Request: ' . $e->getMessage());
                return;
            }
            catch(Exception $e)
            {
                self::basicResponse(500, 'Internal Server Error: ' . get_class($e) . ' raised');
                return;
            }

            switch($indexStorageRecord->getStorageType())
            {
                case StorageType::LOCAL:
                    LocalHandler::handleDownload($indexStorageRecord);
                    break;

                case StorageType::PROXY:
                    ProxyHandler::handleDownload($indexStorageRecord);
                    break;

                case StorageType::CUSTOM:
                    /** @var UploadHandlerInterface $class */
                    $class = self::getCustomHandler();
                    if ($class === null)
                    {
                        break;
                    }

                    $class::handleDownload($indexStorageRecord);
                    break;
            }
        }

        /**
         * Handles a list request, password authentication will be prompted if one is set
         *
         * @return void
         */
        private static function handleListRequest(): void
        {
            if(Configuration::getServerConfiguration()->getPassword() !== null)
            {
                if(self::getRequestPassword() === null)
                {
                    self::basicResponse(401, 'Unauthorized: Authentication required', [
                        sprintf("WWW-Authenticate: Bearer realm=\"%s\"", Configuration::getServerConfiguration()->getName())
                    ]);
                    return;
                }

                if(self::getRequestPassword() !== Configuration::getServerConfiguration()->getPassword())
                {
                    self::basicResponse(403, 'Forbidden: Invalid management password');
                    return;
                }
            }

            $page = 1;
            $limit = 100;

            if(isset($_GET['page']))
            {
                $page = (int)$_GET['page'];
                if($page < 1)
                {
                    self::basicResponse(400, 'Bad Request: Parameter \'page\' cannot be less than 1');
                    return;
                }
            }

            if(isset($_GET['limit']))
            {
                $limit = (int)$_GET['limit'];
                if($limit < 1)
                {
                    self::basicResponse(400, 'Bad Request: Parameter \'limit\' cannot be less than 1');
                    return;
                }
            }

            try
            {
                $results = IndexStorageManager::getUploads($page, $limit);
            }
            catch(DatabaseException)
            {
                self::basicResponse(500, 'Internal Server Error: Failed to list uploads');
                return;
            }
            catch(InvalidArgumentException $e)
            {
                self::basicResponse(400, 'Bad Request:' . $e->getMessage());
                return;
            }

            $returnResults = [];
            foreach($results as $result)
            {
                try
                {
                    $returnResults[] = [
                        'uuid' => $result->getUuid(),
                        'name' => $result->getName(),
                        'size' => $result->getSize(),
                        'created' => $result->getCreated()->getTimestamp(),
                        'last_download' => IndexStorageManager::getLastDownload($result->getUuid()),
                        'downloads' => IndexStorageManager::getDownloads($result->getUuid())
                    ];
                }
                catch (DatabaseException)
                {
                    self::basicResponse(500, 'Internal Server Error: Failed to retrieve statistics for ' . $result->getUuid());
                    return;
                }
            }

            self::basicResponse(
                code: 200,
                text: json_encode($returnResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES),
                contentType: 'application/json'
            );
        }

        /**
         * Handles a delete resource request, a password will be prompted if one is set
         *
         * @return void
         * @throws ServerException Thrown if there was a server-side exception
         */
        private static function handleDeleteRequest(): void
        {
            if(Configuration::getServerConfiguration()->getPassword() !== null)
            {
                if(self::getRequestPassword() === null)
                {
                    self::basicResponse(401, 'Unauthorized: Authentication required', [
                        sprintf("WWW-Authenticate: Bearer realm=\"%s\"", Configuration::getServerConfiguration()->getName())
                    ]);
                    return;
                }

                if(self::getRequestPassword() !== Configuration::getServerConfiguration()->getPassword())
                {
                    self::basicResponse(403, 'Forbidden: Invalid upload password');
                    return;
                }
            }

            if(!isset($_GET['uuid']))
            {
                self::basicResponse(400, 'Bad Request: Missing parameter \'uuid\'');
                return;
            }

            try
            {
                $indexStorageRecord = IndexStorageManager::getUpload($_GET['uuid']);
                if($indexStorageRecord === null)
                {
                    self::basicResponse(404, 'File Not Found');
                    return;
                }
            }
            catch(InvalidArgumentException $e)
            {
                self::basicResponse(400, 'Bad Request: ' . $e->getMessage());
                return;
            }
            catch(Exception $e)
            {
                self::basicResponse(500, 'Internal Server Error: ' . get_class($e) . ' raised');
                return;
            }

            switch($indexStorageRecord->getStorageType())
            {
                case StorageType::LOCAL:
                    LocalHandler::handleDelete($indexStorageRecord);
                    break;

                case StorageType::PROXY:
                    ProxyHandler::handleDelete($indexStorageRecord);
                    break;

                case StorageType::CUSTOM:
                    /** @var UploadHandlerInterface $class */
                    $class = self::getCustomHandler();
                    if ($class === null)
                    {
                        break;
                    }

                    $class::handleDelete($indexStorageRecord);
                    break;
            }
        }

        /**
         * Returns the custom storage handler class name
         *
         * @return string|null The class name or null if not set
         */
        private static function getCustomHandler(): ?string
        {
            if(Configuration::getCustomStorageConfiguration()->getPackage() !== null)
            {
                // Check if the package is set and exists
                if(!Runtime::isImported(Configuration::getCustomStorageConfiguration()->getPackage()))
                {
                    try
                    {
                        Runtime::import(Configuration::getCustomStorageConfiguration()->getPackage());
                    }
                    catch(ImportException $e)
                    {
                        self::basicResponse(500, 'Internal Server Error: Custom storage package not found, ' . $e->getMessage());
                        return null;
                    }
                }
            }

            $class = Configuration::getCustomStorageConfiguration()->getClass();

            // Check if the class is set and exists
            if($class === null)
            {
                self::basicResponse(500, 'Internal Server Error: Custom storage class not set');
                return null;
            }

            if(!class_exists($class))
            {
                self::basicResponse(500, 'Internal Server Error: Custom storage class not found');
                return null;
            }

            // Check if the class implements UploadHandlerInterface
            if(!is_subclass_of($class, UploadHandlerInterface::class))
            {
                self::basicResponse(500, 'Internal Server Error: Custom storage class does not implement UploadHandlerInterface');
                return null;
            }

            return $class;
        }

        /**
         * Returns the request password from the POST/GET parameters or from the HTTP headers
         *
         * @return string|null
         */
        private static function getRequestPassword(): ?string
        {
            // Check for password in GET or POST parameters
            if(isset($_GET['password']))
            {
                return $_GET['password'];
            }
            
            elseif(isset($_POST['password']))
            {
                return $_POST['password'];
            }

            // Check for password in HTTP headers
            if(isset($_SERVER['HTTP_AUTHORIZATION']))
            {
                return str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            }

            return null;
        }

        /**
         * Returns the request action of the client
         *
         * @return RequestAction|null The request action that was detected null if the action couldn't be determined
         */
        private static function getRequestAction(): ?RequestAction
        {
            return match (strtoupper($_SERVER['REQUEST_METHOD']))
            {
                'GET' => self::getUriAction($_GET) ?? RequestAction::DOWNLOAD,
                'POST' => self::getUriAction($_POST) ?? RequestAction::UPLOAD,
                'PUT' => RequestAction::UPLOAD,
                'DELETE' => RequestAction::DELETE,
                default => null,
            };

        }

        /**
         * Extract action from request parameters or URL path
         *
         * @param array $params Request parameters ($_GET or $_POST)
         * @return RequestAction|null The extracted action or null if not found
         */
        private static function getUriAction(array $params): ?RequestAction
        {
            // Check for parameters
            if(isset($params['action']) || isset($params['a']))
            {
                return RequestAction::fromString($params['action'] ?? $params['a']);
            }

            // Check for a trailing path in URL
            $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if(!empty($path))
            {
                $pathParts = explode('/', $path);
                $lastPart = end($pathParts);
                if(!empty($lastPart))
                {
                    return RequestAction::fromString($lastPart);
                }
            }

            return null;
        }

        /**
         * Produces a basic HTTP response with modifiable headers
         *
         * @param int $code The HTTP response code to return
         * @param string $text The HTTP body response to return
         * @param array|null $headers Optional. The headers to provide
         * @param string $contentType Optional. The content type, by default: text/plain
         * @return void
         */
        public static function basicResponse(int $code, string $text, ?array $headers=null, string $contentType='text/plain'): void
        {
            http_response_code($code);
            if($headers !== null)
            {
                foreach($headers as $header)
                {
                    header($header);
                }
            }

            header('Content-Type: ' . $contentType);
            header('X-ServerName: ' . Configuration::getServerConfiguration()->getName());
            print($text);
        }
    }