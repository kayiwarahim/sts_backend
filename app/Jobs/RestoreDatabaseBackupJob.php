<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 2400;

    public int $tries = 1;

    public function __construct(
        public int $backupId,
        public int $restoredBy
    ) {}

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

        $backupSnapshot = [
            'reference' => $backup->reference,

            'filename' => $backup->filename,

            'disk' => $backup->disk,

            'path' => $backup->path,

            'database_name' => $backup->database_name,

            'type' => $backup->type,

            'size_bytes' => $backup->size_bytes,

            'checksum' => $backup->checksum,

            'created_by' => $backup->created_by,

            'metadata' => $backup->metadata,
        ];

        /*
        |--------------------------------------------------------------------------
        | Create safety backup
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
            'reference' => $safety->reference,

            'filename' => $safety->filename,

            'disk' => $safety->disk,

            'path' => $safety->path,

            'database_name' => $safety->database_name,

            'type' => 'pre_restore',

            'size_bytes' => $safety->size_bytes,

            'checksum' => $safety->checksum,

            'created_by' => $admin->id,

            'metadata' => $safety->metadata,
        ];

        $backup->update([
            'status' => 'restoring',

            'restored_by' => $admin->id,

            'error_message' => null,
        ]);

        $maintenanceEnabled =
            false;

        try {

            Artisan::call(
                'down',
                [
                    '--retry' => 60,
                ]
            );

            $maintenanceEnabled =
                true;

            DB::disconnect();

            $service->restore(
                $backup
            );

            DB::purge();
            DB::reconnect();

            $restoredBackup =
                DatabaseBackup::updateOrCreate(
                    [
                        'reference' => $backupSnapshot[
                                'reference'
                            ],
                    ],
                    array_merge(
                        $backupSnapshot,
                        [
                            'status' => 'restored',

                            'restored_by' => $admin->id,

                            'restored_at' => now(),

                            'completed_at' => now(),

                            'error_message' => null,
                        ]
                    )
                );

            DatabaseBackup::updateOrCreate(
                [
                    'reference' => $safetySnapshot[
                            'reference'
                        ],
                ],
                array_merge(
                    $safetySnapshot,
                    [
                        'status' => 'completed',

                        'completed_at' => now(),
                    ]
                )
            );

            Artisan::call(
                'optimize:clear'
            );

            /*
            |--------------------------------------------------------------------------
            | Require reconciliation
            |--------------------------------------------------------------------------
            */

            $metadata =
                $restoredBackup
                    ->metadata
                ?? [];

            $metadata[
                'reconciliation_required'
            ] =
                true;

            $metadata[
                'reconciliation_completed_at'
            ] =
                null;

            $metadata[
                'restored_at'
            ] =
                now()
                    ->toIso8601String();

            $metadata[
                'pre_restore_backup_reference'
            ] =
                $safetySnapshot[
                    'reference'
                ];

            $restoredBackup->update([
                'metadata' => $metadata,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Restore Audit Log
            |--------------------------------------------------------------------------
            */

            AuditLog::create([
                'user_id' => $admin->id,

                'organization_id' => null,

                'action' => 'database_restored',

                'auditable_type' => DatabaseBackup::class,

                'auditable_id' => $restoredBackup->id,

                'old_values' => null,

                'new_values' => [
                    'backup_reference' => $restoredBackup
                        ->reference,

                    'filename' => $restoredBackup
                        ->filename,

                    'pre_restore_backup_reference' => $safetySnapshot[
                            'reference'
                        ],

                    'reconciliation_required' => true,
                ],

                'ip_address' => null,

                'user_agent' => 'Queue Worker',

                'description' => sprintf(
                    'Database restored using backup %s. Reconciliation is required.',
                    $restoredBackup
                        ->reference
                ),
            ]);

        } catch (Throwable $e) {

            try {

                DB::purge();
                DB::reconnect();

                DatabaseBackup::updateOrCreate(
                    [
                        'reference' => $backupSnapshot[
                                'reference'
                            ],
                    ],
                    array_merge(
                        $backupSnapshot,
                        [
                            'status' => 'failed',

                            'restored_by' => $admin->id,

                            'error_message' => $e
                                ->getMessage(),
                        ]
                    )
                );

                AuditLog::create([
                    'user_id' => $admin->id,

                    'organization_id' => null,

                    'action' => 'database_restore_failed',

                    'auditable_type' => DatabaseBackup::class,

                    'auditable_id' => null,

                    'old_values' => null,

                    'new_values' => [
                        'backup_reference' => $backupSnapshot[
                                'reference'
                            ],

                        'error' => $e
                            ->getMessage(),
                    ],

                    'ip_address' => null,

                    'user_agent' => 'Queue Worker',

                    'description' => sprintf(
                        'Database restore failed for backup %s.',
                        $backupSnapshot[
                            'reference'
                        ]
                    ),
                ]);

            } catch (Throwable) {
                /*
                 * Preserve original restore exception.
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
