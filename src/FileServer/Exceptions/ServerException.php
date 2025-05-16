<?php

    namespace FileServer\Exceptions;

    use Exception;
    use Throwable;

    class ServerException extends Exception
    {
        /**
         * @inheritDoc
         */
        public function __construct(string $message="", int $code=0, ?Throwable $previous=null)
        {
            parent::__construct($message, $code, $previous);
        }
    }