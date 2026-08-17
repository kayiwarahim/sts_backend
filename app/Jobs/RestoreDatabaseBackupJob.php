<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class RestoreDatabaseBackupJob
    implements ShouldQueue
{
    use Queueable;

    public int $timeout = 2400;

    public int $tries = 1;

    public function __construct(
        public int $backupId,
        public int $restoredBy
    ) {
    }

    public function handle(
        DatabaseBackupService $service
    ): void {
        $backup =
            DatabaseBackup::findOrFail(
                $this->backupId
            );

        $admin =
            User::findOrFail(
                $this->restoredBy
            );

        /*
        |--------------------------------------------------------------------------
        | Store snapshot because restoring DB can replace this metadata row.
        |--------------------------------------------------------------------------
        */

        $backupSnapshot = [
            'reference' =>
                $backup->reference,

            'filename' =>
                $backup->filename,

            'disk' =>
                $backup->disk,

            'path' =>
                $backup->path,

            'database_name' =>
                $backup->database_name,

            'type' =>
                $backup->type,

            'size_bytes' =>
                $backup->size_bytes,

            'checksum' =>
                $backup->checksum,

            'created_by' =>
                $backup->created_by,

            'metadata' =>
                $backup->metadata,
        ];

        /*
        |--------------------------------------------------------------------------
        | 1. Create pre-restore safety backup
        |--------------------------------------------------------------------------
        */

        $safety =
            $service->createRecord(
                'pre_restore',
                $admin
            );

        $service->create(
            $safety
        );

        $safetySnapshot = [
            'reference' =>
                $safety->reference,

            'filename' =>
                $safety->filename,

            'disk' =>
                $safety->disk,

            'path' =>
                $safety->path,

            'database_name' =>
                $safety->database_name,

            'type' =>
                'pre_restore',

            'size_bytes' =>
                $safety->size_bytes,

            'checksum' =>
                $safety->checksum,

            'created_by' =>
                $admin->id,

            'metadata' =>
                $safety->metadata,
        ];

        $backup->update([
            'status' =>
                'restoring',

            'restored_by' =>
                $admin->id,

            'error_message' =>
                null,
        ]);

        $maintenanceEnabled =
            false;

        try {

            /*
            |--------------------------------------------------------------------------
            | 2. Maintenance mode
            |--------------------------------------------------------------------------
            */

            Artisan::call(
                'down',
                [
                    '--retry' =>
                        60,
                ]
            );

            $maintenanceEnabled =
                true;

            /*
            |--------------------------------------------------------------------------
            | 3. Close existing DB connection before restore
            |--------------------------------------------------------------------------
            */

            DB::disconnect();

            /*
            |--------------------------------------------------------------------------
            | 4. Restore
            |--------------------------------------------------------------------------
            */

            $service->restore(
                $backup
            );

            /*
            |--------------------------------------------------------------------------
            | 5. Reconnect against restored DB
            |--------------------------------------------------------------------------
            */

            DB::purge();
            DB::reconnect();

            /*
            |--------------------------------------------------------------------------
            | 6. Re-register restored backup metadata if snapshot predates it
            |--------------------------------------------------------------------------
            */

            $restoredBackup =
                DatabaseBackup::updateOrCreate(
                    [
                        'reference' =>
                            $backupSnapshot[
                                'reference'
                            ],
                    ],
                    array_merge(
                        $backupSnapshot,
                        [
                            'status' =>
                                'restored',

                            'restored_by' =>
                                $admin->id,

                            'restored_at' =>
                                now(),

                            'completed_at' =>
                                now(),

                            'error_message' =>
                                null,
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Re-register safety backup metadata too
            |--------------------------------------------------------------------------
            */

            DatabaseBackup::updateOrCreate(
                [
                    'reference' =>
                        $safetySnapshot[
                            'reference'
                        ],
                ],
                array_merge(
                    $safetySnapshot,
                    [
                        'status' =>
                            'completed',

                        'completed_at' =>
                            now(),
                    ]
                )
            );

            /*
            |--------------------------------------------------------------------------
            | 7. Clear application caches
            |--------------------------------------------------------------------------
            */

            Artisan::call(
                'optimize:clear'
            );

            /*
            |--------------------------------------------------------------------------
            | 8. Flag reconciliation requirement
            |--------------------------------------------------------------------------
            */

            $metadata =
                $restoredBackup
                    ->metadata
                ?? [];

            $metadata[
                'reconciliation_required'
            ] = true;

            $metadata[
                'restored_at'
            ] =
                now()->toIso8601String();

            $metadata[
                'pre_restore_backup_reference'
            ] =
                $safetySnapshot[
                    'reference'
                ];

            $restoredBackup->update([
                'metadata' =>
                    $metadata,
            ]);

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Database may have changed, therefore lookup by stable reference.
            |--------------------------------------------------------------------------
            */

            try {

                DB::purge();
                DB::reconnect();

                DatabaseBackup::updateOrCreate(
                    [
                        'reference' =>
                            $backupSnapshot[
                                'reference'
                            ],
                    ],
                    array_merge(
                        $backupSnapshot,
                        [
                            'status' =>
                                'failed',

                            'restored_by' =>
                                $admin->id,

                            'error_message' =>
                                $e->getMessage(),
                        ]
                    )
                );

            } catch (Throwable) {
                /*
                 * Original exception is more important.
                 */
            }

            throw $e;

        } finally {

            if (
                $maintenanceEnabled
            ) {
                Artisan::call(
                    'up'
                );
            }
        }
    }
}