<?php

    namespace FileServer\Exceptions;

    use Exception;
    use Throwable;

    class DatabaseException extends Exception
    {
        /**
         * @inheritDoc
         */
        public function __construct(string $message = "", ?Throwable $previous = null)
        {
            parent::__construct($message, $previous->getCode(), $previous);
        }
    }