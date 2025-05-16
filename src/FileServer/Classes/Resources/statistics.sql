create table statistics
(
    uuid          varchar(36)      not null comment 'The UUID reference of the index storage'
        primary key comment 'The Unique Universal Identifier reference index',
    downloads     bigint default 0 not null comment 'The amount the downloads the index has had',
    last_download timestamp        null comment 'The Timestamp for when the file was last downloaded, null means the file has never been downloaded.',
    constraint statistics_uuid_uindex
        unique (uuid) comment 'The Unique Universal Identifier reference index',
    constraint statistics_index_storage_uuid_fk
        foreign key (uuid) references uploads (uuid)
            on update cascade on delete cascade
)
    comment 'Table for housing statistics';

