<?php

    # This is a simple single index file for loading in FileServer and passing on the request to be handled by the
    # server, modify this to your adjustments but this file in its current form will work

    # Load ncc & import FileServer
    require 'ncc';
    import('net.nosial.fileserver');

    try
    {
        # Pass on the request handler
        \FileServer\FileServer::handleRequest();
    }
    catch(Exception $e)
    {
        # Handle the exception
        http_response_code(500);
        print(sprintf("Internal Server Error: %s", $e->getMessage()));
    }