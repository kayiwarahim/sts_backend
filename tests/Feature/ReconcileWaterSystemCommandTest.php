<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReconciliationPersistenceService;
use App\Services\RelworxReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ReconcileWaterSystemCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user =
            User::create([
                'organization_id' =>
                    null,

                'name' =>
                    'Command Test User',

                'email' =>
                    'command-test@example.com',

                'password' =>
                    Hash::make(
                        'password'
                    ),

                'email_verified_at' =>
                    now(),
            ]);
    }

    public function test_reconciliation_command_runs_internal_and_provider_services(): void
    {
        $internal =
            Mockery::mock(
                ReconciliationPersistenceService::class
            );

        $internal
            ->shouldReceive(
                'run'
            )
            ->once()
            ->andReturn([
                'payments' => [
                    'total' => 4,
                    'matched' => 4,
                    'partial' => 0,
                    'unmatched' => 0,
                ],

                'sts' => [
                    'total' => 4,
                    'matched' => 4,
                    'partial' => 0,
                    'unmatched' => 0,
                ],

                'total' => 8,
            ]);

        $provider =
            Mockery::mock(
                RelworxReconciliationService::class
            );

        $provider
            ->shouldReceive(
                'run'
            )
            ->once()
            ->andReturn([
                'total' => 3,
                'matched' => 3,
                'partial' => 0,
                'unmatched' => 0,
                'provider_errors' => 0,
            ]);

        $this->app->instance(
            ReconciliationPersistenceService::class,
            $internal
        );

        $this->app->instance(
            RelworxReconciliationService::class,
            $provider
        );

        $this
            ->artisan(
                'water:reconcile',
                [
                    '--user' =>
                        $this->user->id,
                ]
            )
            ->expectsOutputToContain(
                'Water Management System Reconciliation'
            )
            ->expectsOutputToContain(
                'Reconciliation completed successfully.'
            )
            ->assertSuccessful();
    }

    public function test_internal_only_does_not_run_relworx_service(): void
    {
        $internal =
            Mockery::mock(
                ReconciliationPersistenceService::class
            );

        $internal
            ->shouldReceive(
                'run'
            )
            ->once()
            ->andReturn([
                'payments' => [
                    'total' => 1,
                    'matched' => 1,
                    'partial' => 0,
                    'unmatched' => 0,
                ],

                'sts' => [
                    'total' => 1,
                    'matched' => 1,
                    'partial' => 0,
                    'unmatched' => 0,
                ],

                'total' => 2,
            ]);

        $provider =
            Mockery::mock(
                RelworxReconciliationService::class
            );

        $provider
            ->shouldNotReceive(
                'run'
            );

        $this->app->instance(
            ReconciliationPersistenceService::class,
            $internal
        );

        $this->app->instance(
            RelworxReconciliationService::class,
            $provider
        );

        $this
            ->artisan(
                'water:reconcile',
                [
                    '--user' =>
                        $this->user->id,

                    '--internal-only' =>
                        true,
                ]
            )
            ->assertSuccessful();
    }

    public function test_provider_only_does_not_run_internal_service(): void
    {
        $internal =
            Mockery::mock(
                ReconciliationPersistenceService::class
            );

        $internal
            ->shouldNotReceive(
                'run'
            );

        $provider =
            Mockery::mock(
                RelworxReconciliationService::class
            );

        $provider
            ->shouldReceive(
                'run'
            )
            ->once()
            ->andReturn([
                'total' => 3,
                'matched' => 3,
                'partial' => 0,
                'unmatched' => 0,
                'provider_errors' => 0,
            ]);

        $this->app->instance(
            ReconciliationPersistenceService::class,
            $internal
        );

        $this->app->instance(
            RelworxReconciliationService::class,
            $provider
        );

        $this
            ->artisan(
                'water:reconcile',
                [
                    '--user' =>
                        $this->user->id,

                    '--provider-only' =>
                        true,
                ]
            )
            ->assertSuccessful();
    }

    public function test_conflicting_command_options_fail(): void
    {
        $this
            ->artisan(
                'water:reconcile',
                [
                    '--user' =>
                        $this->user->id,

                    '--internal-only' =>
                        true,

                    '--provider-only' =>
                        true,
                ]
            )
            ->expectsOutputToContain(
                'You cannot use --internal-only and --provider-only together.'
            )
            ->assertFailed();
    }
}