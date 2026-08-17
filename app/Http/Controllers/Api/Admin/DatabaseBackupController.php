<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateDatabaseBackupJob;
use App\Jobs\PruneDatabaseBackupsJob;
use App\Jobs\RestoreDatabaseBackupJob;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $perPage =
            min(
                max(
                    (int)
                    $request->input(
                        'per_page',
                        20
                    ),
                    1
                ),
                100
            );

        $backups =
            DatabaseBackup::query()
                ->with([
                    'createdBy:id,name,email',
                    'restoredBy:id,name,email',
                ])
                ->latest()
                ->paginate(
                    $perPage
                );

        $reconciliationRequired =
            DatabaseBackup::query()
                ->where(
                    'status',
                    'restored'
                )
                ->get()
                ->contains(
                    function (
                        DatabaseBackup $backup
                    ) {
                        return (
                            $backup
                                ->metadata[
                                    'reconciliation_required'
                                ]
                            ?? false
                        ) === true;
                    }
                );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $backups,

            'meta' => [
                'reconciliation_required' =>
                    $reconciliationRequired,

                'scheduled_retention_days' =>
                    (int) config(
                        'database_backups.retention.scheduled_days',
                        30
                    ),

                'pre_restore_retention_days' =>
                    (int) config(
                        'database_backups.retention.pre_restore_days',
                        14
                    ),
            ],
        ]);
    }

    public function store(
        Request $request,
        DatabaseBackupService $service
    ): JsonResponse {
        $backup =
            $service->createRecord(
                'manual',
                $request->user()
            );

        CreateDatabaseBackupJob::dispatch(
            $backup->id,
            'manual',
            $request->user()->id
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database backup creation started.',

            'data' =>
                $backup,
        ], 202);
    }

    public function show(
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): JsonResponse {
        return response()->json([
            'success' =>
                true,

            'data' => [
                'backup' =>
                    $databaseBackup->load([
                        'createdBy:id,name,email',
                        'restoredBy:id,name,email',
                    ]),

                'file_exists' =>
                    Storage::disk(
                        $databaseBackup->disk
                    )->exists(
                        $databaseBackup->path
                    ),

                'verified' =>
                    $service->verify(
                        $databaseBackup
                    ),
            ],
        ]);
    }

    public function download(
        DatabaseBackup $databaseBackup
    ): StreamedResponse {
        abort_unless(
            $databaseBackup
                ->isRestorable(),
            422,
            'Backup is not available for download.'
        );

        $disk =
            Storage::disk(
                $databaseBackup->disk
            );

        abort_unless(
            $disk->exists(
                $databaseBackup->path
            ),
            404,
            'Backup file not found.'
        );

        return $disk->download(
            $databaseBackup->path,
            $databaseBackup->filename,
            [
                'Content-Type' =>
                    'application/gzip',
            ]
        );
    }

    public function restore(
        Request $request,
        DatabaseBackup $databaseBackup
    ): JsonResponse {
        $validated =
            $request->validate([
                'password' => [
                    'required',
                    'string',
                ],

                'confirmation' => [
                    'required',
                    'string',
                    'in:RESTORE DATABASE',
                ],
            ]);

        if (
            !Hash::check(
                $validated['password'],
                $request
                    ->user()
                    ->password
            )
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Your administrator password is incorrect.',
            ], 422);
        }

        abort_unless(
            $databaseBackup
                ->isRestorable(),
            422,
            'This backup cannot be restored.'
        );

        AuditLog::create([
            'user_id' =>
                $request->user()->id,

            'organization_id' =>
                null,

            'action' =>
                'database_restore_requested',

            'auditable_type' =>
                DatabaseBackup::class,

            'auditable_id' =>
                $databaseBackup->id,

            'old_values' =>
                null,

            'new_values' => [
                'reference' =>
                    $databaseBackup
                        ->reference,

                'filename' =>
                    $databaseBackup
                        ->filename,
            ],

            'ip_address' =>
                $request->ip(),

            'user_agent' =>
                $request->userAgent(),

            'description' =>
                sprintf(
                    'Database restore requested for %s.',
                    $databaseBackup
                        ->reference
                ),
        ]);

        RestoreDatabaseBackupJob::dispatch(
            $databaseBackup->id,
            $request->user()->id
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database restore has been queued. A safety backup will be created before restoration.',
        ], 202);
    }

    public function destroy(
        Request $request,
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): JsonResponse {
        $snapshot = [
            'reference' =>
                $databaseBackup
                    ->reference,

            'filename' =>
                $databaseBackup
                    ->filename,

            'type' =>
                $databaseBackup
                    ->type,

            'status' =>
                $databaseBackup
                    ->status,

            'size_bytes' =>
                $databaseBackup
                    ->size_bytes,
        ];

        $backupId =
            $databaseBackup->id;

        $service->delete(
            $databaseBackup
        );

        AuditLog::create([
            'user_id' =>
                $request->user()->id,

            'organization_id' =>
                null,

            'action' =>
                'database_backup_deleted',

            'auditable_type' =>
                DatabaseBackup::class,

            'auditable_id' =>
                $backupId,

            'old_values' =>
                $snapshot,

            'new_values' =>
                null,

            'ip_address' =>
                $request->ip(),

            'user_agent' =>
                $request->userAgent(),

            'description' =>
                sprintf(
                    'Database backup %s was deleted.',
                    $snapshot[
                        'reference'
                    ]
                ),
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database backup deleted successfully.',
        ]);
    }

    public function prune(
        Request $request
    ): JsonResponse {
        PruneDatabaseBackupsJob::dispatch(
            $request
                ->user()
                ->id
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Old database backup pruning has been queued.',
        ], 202);
    }

    public function markReconciled(
        Request $request,
        DatabaseBackup $databaseBackup
    ): JsonResponse {
        abort_unless(
            $databaseBackup
                ->status ===
                'restored',
            422,
            'Only restored backups can be marked as reconciled.'
        );

        $metadata =
            $databaseBackup
                ->metadata
            ?? [];

        $metadata[
            'reconciliation_required'
        ] =
            false;

        $metadata[
            'reconciliation_completed_at'
        ] =
            now()
                ->toIso8601String();

        $metadata[
            'reconciliation_completed_by'
        ] =
            $request
                ->user()
                ->id;

        $databaseBackup->update([
            'metadata' =>
                $metadata,
        ]);

        AuditLog::create([
            'user_id' =>
                $request
                    ->user()
                    ->id,

            'organization_id' =>
                null,

            'action' =>
                'database_restore_reconciled',

            'auditable_type' =>
                DatabaseBackup::class,

            'auditable_id' =>
                $databaseBackup
                    ->id,

            'old_values' => [
                'reconciliation_required' =>
                    true,
            ],

            'new_values' => [
                'reconciliation_required' =>
                    false,

                'reconciliation_completed_at' =>
                    $metadata[
                        'reconciliation_completed_at'
                    ],
            ],

            'ip_address' =>
                $request->ip(),

            'user_agent' =>
                $request
                    ->userAgent(),

            'description' =>
                sprintf(
                    'Reconciliation completed after restore %s.',
                    $databaseBackup
                        ->reference
                ),
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Restore reconciliation has been marked as completed.',

            'data' =>
                $databaseBackup
                    ->fresh(),
        ]);
    }
}