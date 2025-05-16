<?php

    namespace FileServer\Exceptions;

    use Exception;
    use Throwable;

    /**
     * Class UploadException
     *
     * This exception is thrown when there is an internal error during the file upload process.
     *
     * @package FileServer\Exceptions
     */
    class UploadException extends Exception
    {
        /**
         * UploadException constructor.
         *
         * @param string $message The error message.
         * @param int $code The error code.
         * @param Throwable|null $previous The previous throwable used for exception chaining.
         */
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }