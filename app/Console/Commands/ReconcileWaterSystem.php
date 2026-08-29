<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReconciliationPersistenceService;
use App\Services\RelworxReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileWaterSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'water:reconcile
                            {--user= : User ID used for reconciliation scope}
                            {--internal-only : Run only internal payment/STS reconciliation}
                            {--provider-only : Run only Relworx provider reconciliation}';

    /**
     * The console command description.
     */
    protected $description =
        'Reconcile payments, ledger allocations, STS vending and Relworx provider transactions';

    public function __construct(
        protected ReconciliationPersistenceService $reconciliationService,
        protected RelworxReconciliationService $relworxReconciliationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();

        $this->info(
            'Water Management System Reconciliation'
        );

        $this->line(
            str_repeat('-', 50)
        );

        /*
        |--------------------------------------------------------------------------
        | Resolve reconciliation user
        |--------------------------------------------------------------------------
        */

        $user = $this->resolveUser();

        if (! $user) {
            $this->error(
                'No suitable reconciliation user was found.'
            );

            $this->line(
                'Provide one using --user=ID or create a Super Admin user.'
            );

            return self::FAILURE;
        }

        $this->line(
            'Running as: '.
            $user->name.
            ' (#'.
            $user->id.
            ')'
        );

        if ($user->isSuperAdmin()) {
            $this->line(
                'Scope: Entire platform'
            );
        } else {
            $this->line(
                'Scope: Organization #'.
                ($user->organization_id ?? 'N/A')
            );
        }

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Validate options
        |--------------------------------------------------------------------------
        */

        if (
            $this->option('internal-only') &&
            $this->option('provider-only')
        ) {
            $this->error(
                'You cannot use --internal-only and --provider-only together.'
            );

            return self::INVALID;
        }

        $hasFailure = false;

        /*
        |--------------------------------------------------------------------------
        | Internal reconciliation
        |--------------------------------------------------------------------------
        */

        if (! $this->option('provider-only')) {

            try {

                $this->components->task(
                    'Running internal payment and STS reconciliation',
                    function () use (
                        $user,
                        &$internalResult
                    ) {
                        $internalResult =
                            $this
                                ->reconciliationService
                                ->run($user);
                    }
                );

                $this->displayInternalResults(
                    $internalResult
                );

            } catch (Throwable $e) {

                $hasFailure = true;

                $this->error(
                    'Internal reconciliation failed: '.
                    $e->getMessage()
                );
            }

            $this->newLine();
        }

        /*
        |--------------------------------------------------------------------------
        | Relworx reconciliation
        |--------------------------------------------------------------------------
        */

        if (! $this->option('internal-only')) {

            try {

                $this->components->task(
                    'Running Relworx provider reconciliation',
                    function () use (
                        $user,
                        &$providerResult
                    ) {
                        $providerResult =
                            $this
                                ->relworxReconciliationService
                                ->run($user);
                    }
                );

                $this->displayProviderResults(
                    $providerResult
                );

                if (
                    ($providerResult['provider_errors'] ?? 0)
                    > 0
                ) {
                    $hasFailure = true;
                }

            } catch (Throwable $e) {

                $hasFailure = true;

                $this->error(
                    'Relworx reconciliation failed: '.
                    $e->getMessage()
                );
            }

            $this->newLine();
        }

        /*
        |--------------------------------------------------------------------------
        | Final result
        |--------------------------------------------------------------------------
        */

        $this->line(
            str_repeat('-', 50)
        );

        if ($hasFailure) {

            $this->warn(
                'Reconciliation completed with errors.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Reconciliation completed successfully.'
        );

        return self::SUCCESS;
    }

    /**
     * Resolve the user whose permissions determine
     * reconciliation scope.
     */
    protected function resolveUser(): ?User
    {
        /*
        |--------------------------------------------------------------------------
        | Explicit user
        |--------------------------------------------------------------------------
        */

        if ($this->option('user')) {

            return User::find(
                $this->option('user')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prefer Super Admin
        |--------------------------------------------------------------------------
        */

        $superAdmin =
            User::role('Super Admin')
                ->first();

        if ($superAdmin) {
            return $superAdmin;
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        return User::query()
            ->whereNotNull(
                'organization_id'
            )
            ->orderBy('id')
            ->first();
    }

    /**
     * Display internal reconciliation results.
     */
    protected function displayInternalResults(
        array $result
    ): void {

        $payments =
            $result['payments'] ?? [];

        $sts =
            $result['sts'] ?? [];

        $this->newLine();

        $this->info(
            'Internal Reconciliation'
        );

        $this->table(
            [
                'Type',
                'Total',
                'Matched',
                'Partial',
                'Unmatched',
            ],
            [
                [
                    'Payments',
                    $payments['total'] ?? 0,
                    $payments['matched'] ?? 0,
                    $payments['partial'] ?? 0,
                    $payments['unmatched'] ?? 0,
                ],
                [
                    'STS',
                    $sts['total'] ?? 0,
                    $sts['matched'] ?? 0,
                    $sts['partial'] ?? 0,
                    $sts['unmatched'] ?? 0,
                ],
            ]
        );
    }

    /**
     * Display Relworx reconciliation results.
     */
    protected function displayProviderResults(
        array $result
    ): void {

        $this->newLine();

        $this->info(
            'Relworx Provider Reconciliation'
        );

        $this->table(
            [
                'Total',
                'Matched',
                'Partial',
                'Unmatched',
                'Provider Errors',
            ],
            [
                [
                    $result['total'] ?? 0,
                    $result['matched'] ?? 0,
                    $result['partial'] ?? 0,
                    $result['unmatched'] ?? 0,
                    $result['provider_errors'] ?? 0,
                ],
            ]
        );
    }
}
