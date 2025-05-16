create table uploads
(
    uuid         varchar(36)                                           default uuid()              not null comment 'The Primary Universal Unique Identifier for the file upload'
        primary key comment 'The Primary Unique Index for the UUID column',
    status       enum ('UPLOADING', 'AVAILABLE', 'DELETED', 'MISSING') default 'UPLOADING'         not null comment 'The status of the file
 - UPLOADING: The file is currently uploading, depending on the record creation date this can be used as a way to determine if the file upload is incomplete
 - AVAILABLE: The file is available for download
 - DELETED: The file was deleted from the server either manually or by the automated cleanup task
 - MISSING: The file once existed but can no longer be found on the server, this file was not deleted on purpose.',
    name         varchar(255)                                                                      null comment 'The name of the file, including the extension, if null it will fallback to the uuid with no extension',
    size         bigint                                                                            not null comment 'The size of the file in bytes',
    storage_type varchar(32)                                                                       not null comment 'The storage type used to store the file',
    pointers     blob                                                                              null comment 'Pointer data for retrieving the file when requested',
    created      timestamp                                             default current_timestamp() not null comment 'The Timestamp for when this record was created',
    constraint uploads_uuid_uindex
        unique (uuid) comment 'The Primary Unique Index for the UUID column'
)
    comment 'Table for housing indexes for file uploads';

create index uploads_created_index
    on uploads (created);

create index uploads_status_index
    on uploads (status);

