<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateDatabaseBackupJob
    implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public ?int $backupId = null,
        public string $type = 'scheduled',
        public ?int $createdBy = null
    ) {
    }

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

        $service->create(
            $backup
        );
    }

    public function failed(
        Throwable $exception
    ): void {
        if (
            !$this->backupId
        ) {
            return;
        }

        DatabaseBackup::whereKey(
            $this->backupId
        )->update([
            'status' =>
                'failed',

            'completed_at' =>
                now(),

            'error_message' =>
                $exception
                    ->getMessage(),
        ]);
    }
}