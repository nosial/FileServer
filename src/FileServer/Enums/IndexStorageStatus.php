<?php

    namespace FileServer\Enums;

    enum IndexStorageStatus : string
    {
        case UPLOADING = 'UPLOADING';
        case AVAILABLE = 'AVAILABLE';
        case DELETED = 'DELETED';
        case MISSING = 'MISSING';
    }
