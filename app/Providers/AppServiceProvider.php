<?php

namespace App\Providers;

use App\Models\BillingConfiguration;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReconciliationRecord;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterTariff;
use App\Observers\AuditObserver;
use App\Policies\BillingConfigurationPolicy;
use App\Policies\MeterAssignmentPolicy;
use App\Policies\TenancyPolicy;
use App\Policies\WaterTariffPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Super Admin */

        Gate::before(function (
            User $user,
            string $ability
        ) {

            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        Gate::policy(
            Tenancy::class,
            TenancyPolicy::class
        );

        Gate::policy(
            MeterAssignment::class,
            MeterAssignmentPolicy::class
        );

        Gate::policy(
            WaterTariff::class,
            WaterTariffPolicy::class
        );

        Gate::policy(
            BillingConfiguration::class,
            BillingConfigurationPolicy::class
        );

        /* Financial / configuration audit logging */

        $auditedModels = [
            Organization::class,
            Property::class,
            Unit::class,
            Tenant::class,
            Tenancy::class,
            Meter::class,
            MeterAssignment::class,
            WaterTariff::class,
            BillingConfiguration::class,
            Payment::class,
            ReconciliationRecord::class,
        ];

        foreach (
            $auditedModels as $model
        ) {
            $model::observe(
                AuditObserver::class
            );
        }

    }
}
