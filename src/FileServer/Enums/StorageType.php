<?php

    namespace FileServer\Enums;

    enum StorageType : string
    {
        case LOCAL = 'LOCAL';
        case PROXY = 'PROXY';
        case CUSTOM = 'CUSTOM';
    }
