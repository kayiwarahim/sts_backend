<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    public function createRecord(
        string $type = 'manual',
        ?User $user = null
    ): DatabaseBackup {
        $database =
            $this->databaseConfig();

        $reference =
            'DBB-' .
            now()->format('YmdHis') .
            '-' .
            strtoupper(
                Str::random(8)
            );

        $filename =
            'sts-' .
            now()->format(
                'Y-m-d-His'
            ) .
            '-' .
            strtolower(
                Str::random(6)
            ) .
            '.sql.gz';

        $directory =
            trim(
                config(
                    'database_backups.directory',
                    'database-backups'
                ),
                '/'
            );

        return DatabaseBackup::create([
            'reference' =>
                $reference,

            'filename' =>
                $filename,

            'disk' =>
                config(
                    'database_backups.disk',
                    'local'
                ),

            'path' =>
                $directory .
                '/' .
                $filename,

            'database_name' =>
                $database['database'],

            'type' =>
                $type,

            'status' =>
                'pending',

            'created_by' =>
                $user?->id,

            'metadata' => [
                'connection' =>
                    config(
                        'database.default'
                    ),

                'driver' =>
                    $database['driver'],
            ],
        ]);
    }

    public function create(
        DatabaseBackup $backup
    ): DatabaseBackup {
        if (
            config('database.default')
            !== 'mysql'
        ) {
            throw new RuntimeException(
                'Database backup currently supports MySQL only.'
            );
        }

        $backup->update([
            'status' =>
                'processing',

            'started_at' =>
                now(),

            'completed_at' =>
                null,

            'error_message' =>
                null,
        ]);

        $storage =
            Storage::disk(
                $backup->disk
            );

        $directory =
            dirname(
                $backup->path
            );

        $storage->makeDirectory(
            $directory
        );

        $gzPath =
            $storage->path(
                $backup->path
            );

        $sqlPath =
            $gzPath .
            '.working.sql';

        $credentialsPath =
            null;

        try {

            $database =
                $this->databaseConfig();

            $credentialsPath =
                $this
                    ->createTemporaryMysqlConfig(
                        $database
                    );

            $command = [
                config(
                    'database_backups.mysqldump_binary',
                    'mysqldump'
                ),

                '--defaults-extra-file=' .
                    $credentialsPath,

                '--host=' .
                    $database['host'],

                '--port=' .
                    $database['port'],

                '--single-transaction',
                '--quick',

                '--routines',
                '--events',
                '--triggers',

                '--hex-blob',

                '--no-tablespaces',

                '--default-character-set=utf8mb4',

                '--result-file=' .
                    $sqlPath,

                $database['database'],
            ];

            $result =
                Process::timeout(
                    (int) config(
                        'database_backups.backup_timeout',
                        900
                    )
                )->run(
                    $command
                );

            if (
                $result->failed()
            ) {
                throw new RuntimeException(
                    'mysqldump failed: ' .
                    trim(
                        $result
                            ->errorOutput()
                    )
                );
            }

            if (
                !is_file($sqlPath)
                ||
                filesize($sqlPath) <= 0
            ) {
                throw new RuntimeException(
                    'mysqldump did not generate a valid SQL file.'
                );
            }

            $this->compressFile(
                $sqlPath,
                $gzPath
            );

            if (
                !is_file($gzPath)
                ||
                filesize($gzPath) <= 0
            ) {
                throw new RuntimeException(
                    'Compressed backup file was not created.'
                );
            }

            @unlink(
                $sqlPath
            );

            $backup->update([
                'status' =>
                    'completed',

                'size_bytes' =>
                    filesize(
                        $gzPath
                    ),

                'checksum' =>
                    hash_file(
                        'sha256',
                        $gzPath
                    ),

                'completed_at' =>
                    now(),

                'error_message' =>
                    null,

                'metadata' =>
                    array_merge(
                        $backup->metadata
                            ?? [],
                        [
                            'compressed' =>
                                true,

                            'format' =>
                                'sql.gz',

                            'mysql_host' =>
                                $database['host'],

                            'mysql_port' =>
                                $database['port'],
                        ]
                    ),
            ]);

            return $backup->fresh();

        } catch (Throwable $e) {

            @unlink(
                $sqlPath
            );

            if (
                is_file(
                    $gzPath
                )
            ) {
                @unlink(
                    $gzPath
                );
            }

            $backup->update([
                'status' =>
                    'failed',

                'completed_at' =>
                    now(),

                'error_message' =>
                    $e->getMessage(),
            ]);

            throw $e;

        } finally {

            if (
                $credentialsPath
            ) {
                @unlink(
                    $credentialsPath
                );
            }
        }
    }

    public function restore(
        DatabaseBackup $backup
    ): void {
        if (
            config('database.default')
            !== 'mysql'
        ) {
            throw new RuntimeException(
                'Database restore currently supports MySQL only.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Internal worker is allowed to restore a record already marked
        | "restoring".
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $backup->status,
                [
                    'completed',
                    'restored',
                    'restoring',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'This database backup cannot be restored.'
            );
        }

        $storage =
            Storage::disk(
                $backup->disk
            );

        if (
            !$storage->exists(
                $backup->path
            )
        ) {
            throw new RuntimeException(
                'Database backup file does not exist.'
            );
        }

        $gzPath =
            $storage->path(
                $backup->path
            );

        /*
        |--------------------------------------------------------------------------
        | Verify checksum
        |--------------------------------------------------------------------------
        */

        if (
            filled(
                $backup->checksum
            )
        ) {
            $currentChecksum =
                hash_file(
                    'sha256',
                    $gzPath
                );

            if (
                !hash_equals(
                    $backup->checksum,
                    $currentChecksum
                )
            ) {
                throw new RuntimeException(
                    'Backup checksum verification failed.'
                );
            }
        }

        $sqlPath =
            $gzPath .
            '.restore.sql';

        $credentialsPath =
            null;

        try {

            $this->decompressFile(
                $gzPath,
                $sqlPath
            );

            if (
                !is_file(
                    $sqlPath
                )
                ||
                filesize(
                    $sqlPath
                ) <= 0
            ) {
                throw new RuntimeException(
                    'Backup could not be decompressed.'
                );
            }

            $database =
                $this->databaseConfig();

            $credentialsPath =
                $this
                    ->createTemporaryMysqlConfig(
                        $database
                    );

            $input =
                fopen(
                    $sqlPath,
                    'rb'
                );

            if (!$input) {
                throw new RuntimeException(
                    'Unable to open SQL backup for restore.'
                );
            }

            try {

                $command = [
                    config(
                        'database_backups.mysql_binary',
                        'mysql'
                    ),

                    '--defaults-extra-file=' .
                        $credentialsPath,

                    '--host=' .
                        $database['host'],

                    '--port=' .
                        $database['port'],

                    '--default-character-set=utf8mb4',

                    $database['database'],
                ];

                $result =
                    Process::timeout(
                        (int) config(
                            'database_backups.restore_timeout',
                            1800
                        )
                    )
                        ->input(
                            $input
                        )
                        ->run(
                            $command
                        );

            } finally {

                fclose(
                    $input
                );
            }

            if (
                $result->failed()
            ) {
                throw new RuntimeException(
                    'MySQL restore failed: ' .
                    trim(
                        $result
                            ->errorOutput()
                    )
                );
            }

        } finally {

            @unlink(
                $sqlPath
            );

            if (
                $credentialsPath
            ) {
                @unlink(
                    $credentialsPath
                );
            }
        }
    }

    public function delete(
        DatabaseBackup $backup
    ): void {
        if (
            in_array(
                $backup->status,
                [
                    'processing',
                    'restoring',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'A backup currently being processed cannot be deleted.'
            );
        }

        Storage::disk(
            $backup->disk
        )->delete(
            $backup->path
        );

        $backup->delete();
    }

    public function verify(
        DatabaseBackup $backup
    ): bool {
        $disk =
            Storage::disk(
                $backup->disk
            );

        if (
            !$disk->exists(
                $backup->path
            )
        ) {
            return false;
        }

        if (
            !$backup->checksum
        ) {
            return false;
        }

        $path =
            $disk->path(
                $backup->path
            );

        return hash_equals(
            $backup->checksum,
            hash_file(
                'sha256',
                $path
            )
        );
    }

    /**
     * Delete backups according to retention policy.
     *
     * Manual backups are NEVER automatically deleted.
     */
    public function prune(): array
    {
        $scheduledDays =
            max(
                1,
                (int) config(
                    'database_backups.retention.scheduled_days',
                    30
                )
            );

        $preRestoreDays =
            max(
                1,
                (int) config(
                    'database_backups.retention.pre_restore_days',
                    14
                )
            );

        $results = [
            'scheduled_deleted' =>
                0,

            'pre_restore_deleted' =>
                0,

            'files_missing' =>
                0,

            'failed' =>
                0,

            'freed_bytes' =>
                0,
        ];

        /*
        |--------------------------------------------------------------------------
        | Scheduled backups
        |--------------------------------------------------------------------------
        */

        DatabaseBackup::query()
            ->where(
                'type',
                'scheduled'
            )
            ->whereIn(
                'status',
                [
                    'completed',
                    'restored',
                    'failed',
                ]
            )
            ->where(
                'created_at',
                '<',
                now()
                    ->subDays(
                        $scheduledDays
                    )
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($backups) use (
                    &$results
                ) {
                    foreach (
                        $backups
                        as $backup
                    ) {
                        try {

                            $size =
                                (int) (
                                    $backup
                                        ->size_bytes
                                    ?? 0
                                );

                            $disk =
                                Storage::disk(
                                    $backup->disk
                                );

                            if (
                                $disk->exists(
                                    $backup->path
                                )
                            ) {
                                $disk->delete(
                                    $backup->path
                                );

                                $results[
                                    'freed_bytes'
                                ] +=
                                    $size;

                            } else {

                                $results[
                                    'files_missing'
                                ]++;
                            }

                            $backup->delete();

                            $results[
                                'scheduled_deleted'
                            ]++;

                        } catch (Throwable) {

                            $results[
                                'failed'
                            ]++;
                        }
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Pre-restore safety backups
        |--------------------------------------------------------------------------
        */

        DatabaseBackup::query()
            ->where(
                'type',
                'pre_restore'
            )
            ->whereIn(
                'status',
                [
                    'completed',
                    'restored',
                    'failed',
                ]
            )
            ->where(
                'created_at',
                '<',
                now()
                    ->subDays(
                        $preRestoreDays
                    )
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($backups) use (
                    &$results
                ) {
                    foreach (
                        $backups
                        as $backup
                    ) {
                        try {

                            $size =
                                (int) (
                                    $backup
                                        ->size_bytes
                                    ?? 0
                                );

                            $disk =
                                Storage::disk(
                                    $backup->disk
                                );

                            if (
                                $disk->exists(
                                    $backup->path
                                )
                            ) {
                                $disk->delete(
                                    $backup->path
                                );

                                $results[
                                    'freed_bytes'
                                ] +=
                                    $size;

                            } else {

                                $results[
                                    'files_missing'
                                ]++;
                            }

                            $backup->delete();

                            $results[
                                'pre_restore_deleted'
                            ]++;

                        } catch (Throwable) {

                            $results[
                                'failed'
                            ]++;
                        }
                    }
                }
            );

        return $results;
    }

    protected function databaseConfig(): array
    {
        $connection =
            config(
                'database.default'
            );

        $config =
            config(
                "database.connections.{$connection}"
            );

        if (
            !is_array($config)
        ) {
            throw new RuntimeException(
                'Database configuration could not be loaded.'
            );
        }

        if (
            ($config['driver'] ?? null)
            !== 'mysql'
        ) {
            throw new RuntimeException(
                'Database backup currently supports MySQL only.'
            );
        }

        foreach (
            [
                'host',
                'port',
                'database',
                'username',
            ]
            as $required
        ) {
            if (
                !filled(
                    $config[
                        $required
                    ] ?? null
                )
            ) {
                throw new RuntimeException(
                    "MySQL {$required} is not configured."
                );
            }
        }

        return [
            'driver' =>
                'mysql',

            'host' =>
                (string)
                $config['host'],

            'port' =>
                (string)
                $config['port'],

            'database' =>
                (string)
                $config['database'],

            'username' =>
                (string)
                $config['username'],

            'password' =>
                (string)
                (
                    $config['password']
                    ?? ''
                ),
        ];
    }

    protected function createTemporaryMysqlConfig(
        array $database
    ): string {
        $directory =
            storage_path(
                'app/private/mysql-temp'
            );

        if (
            !is_dir(
                $directory
            )
        ) {
            mkdir(
                $directory,
                0700,
                true
            );
        }

        $path =
            $directory .
            DIRECTORY_SEPARATOR .
            'mysql-' .
            Str::uuid() .
            '.cnf';

        $username =
            $this->escapeOptionValue(
                $database['username']
            );

        $password =
            $this->escapeOptionValue(
                $database['password']
            );

        $contents =
            "[client]\n" .
            "user=\"{$username}\"\n" .
            "password=\"{$password}\"\n";

        file_put_contents(
            $path,
            $contents,
            LOCK_EX
        );

        @chmod(
            $path,
            0600
        );

        return $path;
    }

    protected function escapeOptionValue(
        string $value
    ): string {
        return str_replace(
            [
                '\\',
                '"',
                "\n",
                "\r",
            ],
            [
                '\\\\',
                '\\"',
                '',
                '',
            ],
            $value
        );
    }

    protected function compressFile(
        string $source,
        string $destination
    ): void {
        if (
            !function_exists(
                'gzopen'
            )
        ) {
            throw new RuntimeException(
                'PHP zlib extension is required for database backups.'
            );
        }

        $input =
            fopen(
                $source,
                'rb'
            );

        $output =
            gzopen(
                $destination,
                'wb9'
            );

        if (
            !$input
            ||
            !$output
        ) {
            if ($input) {
                fclose(
                    $input
                );
            }

            if ($output) {
                gzclose(
                    $output
                );
            }

            throw new RuntimeException(
                'Unable to compress database backup.'
            );
        }

        try {

            while (
                !feof(
                    $input
                )
            ) {
                $buffer =
                    fread(
                        $input,
                        1024 * 1024
                    );

                if (
                    $buffer === false
                ) {
                    throw new RuntimeException(
                        'Failed reading temporary SQL backup.'
                    );
                }

                gzwrite(
                    $output,
                    $buffer
                );
            }

        } finally {

            fclose(
                $input
            );

            gzclose(
                $output
            );
        }
    }

    protected function decompressFile(
        string $source,
        string $destination
    ): void {
        if (
            !function_exists(
                'gzopen'
            )
        ) {
            throw new RuntimeException(
                'PHP zlib extension is required for database restore.'
            );
        }

        $input =
            gzopen(
                $source,
                'rb'
            );

        $output =
            fopen(
                $destination,
                'wb'
            );

        if (
            !$input
            ||
            !$output
        ) {
            if ($input) {
                gzclose(
                    $input
                );
            }

            if ($output) {
                fclose(
                    $output
                );
            }

            throw new RuntimeException(
                'Unable to decompress database backup.'
            );
        }

        try {

            while (
                !gzeof(
                    $input
                )
            ) {
                $buffer =
                    gzread(
                        $input,
                        1024 * 1024
                    );

                if (
                    $buffer === false
                ) {
                    throw new RuntimeException(
                        'Failed reading compressed database backup.'
                    );
                }

                fwrite(
                    $output,
                    $buffer
                );
            }

        } finally {

            gzclose(
                $input
            );

            fclose(
                $output
            );
        }
    }
}