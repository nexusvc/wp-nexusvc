<?php

$sql = "CREATE TABLE `{$table_name}` (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    hash varchar(191) NOT NULL,
    token varchar(12) NOT NULL,
    used tinyint(1) DEFAULT 0 NOT NULL,
    validated tinyint(1) DEFAULT 0 NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at datetime NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY  (id)
) $charset_collate;";
