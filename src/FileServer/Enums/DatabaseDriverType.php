<?php

    namespace FileServer\Enums;

    enum DatabaseDriverType : string
    {
        case SQL = 'SQL';
        case SQLITE = 'SQLITE';
    }
