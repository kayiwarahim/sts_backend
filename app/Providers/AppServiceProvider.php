<?php

namespace App\Providers;
use App\Models\User;
use App\Models\Tenancy;
use App\Models\MeterAssignment;
use App\Policies\TenancyPolicy;
use App\Policies\MeterAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\WaterTariff;
use App\Models\BillingConfiguration;
use App\Policies\WaterTariffPolicy;
use App\Policies\BillingConfigurationPolicy;

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
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

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
    }
}
