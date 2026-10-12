<?php

namespace Tests\Feature;

use App\Jobs\RestoreDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseBackupUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config(['database_backups.disk' => 'local']);
    }

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->assignRole('Super Admin');

        return $user;
    }

    private function payload(): array
    {
        return [
            'backup' => UploadedFile::fake()->createWithContent('local.sql.gz', gzencode('-- SQL dump'.PHP_EOL.'SELECT 1;')),
            'password' => 'secret-password',
            'confirmation' => 'RESTORE DATABASE',
        ];
    }

    public function test_upload_registers_backup_and_queues_existing_restore_job(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/database-backups/restore-upload', $this->payload())
            ->assertAccepted()->assertJsonPath('success', true);

        $backup = DatabaseBackup::sole();
        Storage::disk('local')->assertExists($backup->path);
        $this->assertSame('completed', $backup->status);
        $this->assertSame('upload', $backup->metadata['source']);
        $this->assertSame('local.sql.gz', $backup->metadata['original_filename']);
        $this->assertNotSame('local.sql.gz', $backup->filename);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($backup->path)), $backup->checksum);
        $this->assertDatabaseHas('audit_logs', ['action' => 'database_restore_requested', 'auditable_id' => $backup->id]);
        Queue::assertPushed(RestoreDatabaseBackupJob::class, fn ($job) => $job->backupId === $backup->id && $job->restoredBy === $admin->id);
    }

    public function test_bad_password_does_not_store_or_queue_backup(): void
    {
        $payload = $this->payload();
        $payload['password'] = 'incorrect';
        $this->actingAs($this->admin())->postJson('/api/admin/database-backups/restore-upload', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('database_backups', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_invalid_file_confirmation_and_size_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload();
        $payload['backup'] = UploadedFile::fake()->createWithContent('fake.sql.gz', 'not gzip');
        $this->postJson('/api/admin/database-backups/restore-upload', $payload)->assertUnprocessable()->assertJsonValidationErrors('backup');
        $payload = $this->payload();
        $payload['confirmation'] = 'restore';
        $this->postJson('/api/admin/database-backups/restore-upload', $payload)->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        config(['database_backups.upload_max_kilobytes' => 1]);
        $payload = $this->payload();
        $payload['backup'] = UploadedFile::fake()->create('large.sql.gz', 2);
        $this->postJson('/api/admin/database-backups/restore-upload', $payload)->assertUnprocessable()->assertJsonValidationErrors('backup');
        $this->assertDatabaseCount('database_backups', 0);
        Queue::assertNothingPushed();
    }

    public function test_upload_requires_authentication_and_super_admin_role(): void
    {
        $this->postJson('/api/admin/database-backups/restore-upload', $this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/database-backups/restore-upload', $this->payload())->assertForbidden();
        Queue::assertNothingPushed();
    }
}
