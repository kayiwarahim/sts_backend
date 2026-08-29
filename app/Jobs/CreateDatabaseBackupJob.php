<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public ?int $backupId = null,
        public string $type = 'scheduled',
        public ?int $createdBy = null
    ) {}

    public function handle(
        DatabaseBackupService $service
    ): void {
        if (
            $this->backupId
        ) {
            $backup =
                DatabaseBackup::findOrFail(
                    $this->backupId
                );

        } else {

            $user =
                $this->createdBy
                    ? User::find(
                        $this->createdBy
                    )
                    : null;

            $backup =
                $service->createRecord(
                    $this->type,
                    $user
                );
        }

        $backup =
            $service->create(
                $backup
            );

        AuditLog::create([
            'user_id' => $this->createdBy,

            'organization_id' => null,

            'action' => 'database_backup_created',

            'auditable_type' => DatabaseBackup::class,

            'auditable_id' => $backup->id,

            'old_values' => null,

            'new_values' => [
                'reference' => $backup->reference,

                'filename' => $backup->filename,

                'type' => $backup->type,

                'size_bytes' => $backup->size_bytes,

                'checksum' => $backup->checksum,
            ],

            'ip_address' => null,

            'user_agent' => 'Queue Worker',

            'description' => sprintf(
                'Database backup %s created successfully.',
                $backup->reference
            ),
        ]);
    }

    public function failed(
        Throwable $exception
    ): void {
        if (
            $this->backupId
        ) {
            DatabaseBackup::whereKey(
                $this->backupId
            )->update([
                'status' => 'failed',

                'completed_at' => now(),

                'error_message' => $exception
                    ->getMessage(),
            ]);
        }

        AuditLog::create([
            'user_id' => $this->createdBy,

            'organization_id' => null,

            'action' => 'database_backup_failed',

            'auditable_type' => DatabaseBackup::class,

            'auditable_id' => $this->backupId,

            'old_values' => null,

            'new_values' => [
                'error' => $exception
                    ->getMessage(),
            ],

            'ip_address' => null,

            'user_agent' => 'Queue Worker',

            'description' => 'Database backup creation failed.',
        ]);
    }
}
