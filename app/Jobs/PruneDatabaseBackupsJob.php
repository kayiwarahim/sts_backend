<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PruneDatabaseBackupsJob
    implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public ?int $requestedBy = null
    ) {
    }

    public function handle(
        DatabaseBackupService $service
    ): void {
        $result =
            $service->prune();

        AuditLog::create([
            'user_id' =>
                $this->requestedBy,

            'organization_id' =>
                null,

            'action' =>
                'database_backups_pruned',

            'auditable_type' =>
                null,

            'auditable_id' =>
                null,

            'old_values' =>
                null,

            'new_values' =>
                $result,

            'ip_address' =>
                null,

            'user_agent' =>
                'Queue Worker',

            'description' =>
                sprintf(
                    'Database backup pruning completed. Scheduled: %d, pre-restore: %d, freed bytes: %d.',
                    $result[
                        'scheduled_deleted'
                    ],
                    $result[
                        'pre_restore_deleted'
                    ],
                    $result[
                        'freed_bytes'
                    ]
                ),
        ]);
    }

    public function failed(
        Throwable $exception
    ): void {
        AuditLog::create([
            'user_id' =>
                $this->requestedBy,

            'organization_id' =>
                null,

            'action' =>
                'database_backup_prune_failed',

            'auditable_type' =>
                null,

            'auditable_id' =>
                null,

            'old_values' =>
                null,

            'new_values' => [
                'error' =>
                    $exception
                        ->getMessage(),
            ],

            'ip_address' =>
                null,

            'user_agent' =>
                'Queue Worker',

            'description' =>
                'Database backup pruning failed.',
        ]);
    }
}