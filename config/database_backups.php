<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    */

    'disk' =>
        env(
            'DB_BACKUP_DISK',
            'local'
        ),

    'directory' =>
        env(
            'DB_BACKUP_DIRECTORY',
            'database-backups'
        ),

    /*
    |--------------------------------------------------------------------------
    | MySQL executables
    |--------------------------------------------------------------------------
    |
    | Windows example:
    |
    | C:\xampp\mysql\bin\mysqldump.exe
    | C:\xampp\mysql\bin\mysql.exe
    |
    | Ubuntu:
    |
    | /usr/bin/mysqldump
    | /usr/bin/mysql
    |
    */

    'mysqldump_binary' =>
        env(
            'MYSQLDUMP_BINARY',
            'mysqldump'
        ),

    'mysql_binary' =>
        env(
            'MYSQL_BINARY',
            'mysql'
        ),

    /*
    |--------------------------------------------------------------------------
    | Process timeouts
    |--------------------------------------------------------------------------
    */

    'backup_timeout' =>
        (int) env(
            'DB_BACKUP_TIMEOUT',
            900
        ),

    'restore_timeout' =>
        (int) env(
            'DB_RESTORE_TIMEOUT',
            1800
        ),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'retention_days' =>
        (int) env(
            'DB_BACKUP_RETENTION_DAYS',
            30
        ),

];