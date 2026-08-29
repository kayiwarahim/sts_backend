<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    */

    'disk' => env(
        'DB_BACKUP_DISK',
        'local'
    ),

    'directory' => env(
        'DB_BACKUP_DIRECTORY',
        'database-backups'
    ),

    /*
    |--------------------------------------------------------------------------
    | MySQL executables
    |--------------------------------------------------------------------------
    */

    'mysqldump_binary' => env(
        'MYSQLDUMP_BINARY',
        'mysqldump'
    ),

    'mysql_binary' => env(
        'MYSQL_BINARY',
        'mysql'
    ),

    /*
    |--------------------------------------------------------------------------
    | Process timeouts
    |--------------------------------------------------------------------------
    */

    'backup_timeout' => (int) env(
        'DB_BACKUP_TIMEOUT',
        900
    ),

    'restore_timeout' => (int) env(
        'DB_RESTORE_TIMEOUT',
        1800
    ),

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | Manual backups are never automatically deleted.
    |
    | Scheduled backups:
    |     Default retention = 30 days
    |
    | Pre-restore backups:
    |     Default retention = 14 days
    |--------------------------------------------------------------------------
    */

    'retention' => [

    'scheduled_days' => (int) env(
        'DB_BACKUP_SCHEDULED_RETENTION_DAYS',
        30
    ),

    'pre_restore_days' => (int) env(
        'DB_BACKUP_PRE_RESTORE_RETENTION_DAYS',
        14
    ),

    'manual_auto_delete' => false,
    ],

];
