<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LandlordRegistrationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\WaterTariffController;
use App\Http\Controllers\Api\BillingConfigurationController;
use App\Http\Controllers\Api\TenancyController;
use App\Http\Controllers\Api\MeterAssignmentController;


/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );

    Route::post(
        '/register/landlord',
        [LandlordRegistrationController::class, 'register']
    );

});


/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/me',
        [AuthController::class, 'me']
    );

    Route::post(
        '/auth/logout',
        [AuthController::class, 'logout']
    );


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Super Admin')
        ->prefix('admin')
        ->group(function () {

            Route::apiResource(
                'organizations',
                OrganizationController::class
            )->middleware(
                'permission:organizations.view'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Organization Scoped
    |--------------------------------------------------------------------------
    */

    Route::middleware('organization')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Properties
            |--------------------------------------------------------------------------
            */

            Route::get(
                'properties',
                [PropertyController::class, 'index']
            )->middleware('permission:properties.view');

            Route::post(
                'properties',
                [PropertyController::class, 'store']
            )->middleware('permission:properties.create');

            Route::get(
                'properties/{property}',
                [PropertyController::class, 'show']
            )->middleware('permission:properties.view');

            Route::put(
                'properties/{property}',
                [PropertyController::class, 'update']
            )->middleware('permission:properties.update');

            Route::patch(
                'properties/{property}',
                [PropertyController::class, 'update']
            )->middleware('permission:properties.update');

            Route::delete(
                'properties/{property}',
                [PropertyController::class, 'destroy']
            )->middleware('permission:properties.delete');


            /*
            |--------------------------------------------------------------------------
            | Units
            |--------------------------------------------------------------------------
            */

            Route::get(
                'properties/{property}/units',
                [UnitController::class, 'index']
            )->middleware(
                'permission:units.view'
            );

            Route::post(
                'properties/{property}/units',
                [UnitController::class, 'store']
            )->middleware(
                'permission:units.create'
            );

            Route::get(
                'units/{unit}',
                [UnitController::class, 'show']
            )->middleware(
                'permission:units.view'
            );

            Route::put(
                'units/{unit}',
                [UnitController::class, 'update']
            )->middleware(
                'permission:units.update'
            );

            Route::patch(
                'units/{unit}',
                [UnitController::class, 'update']
            )->middleware(
                'permission:units.update'
            );

            Route::delete(
                'units/{unit}',
                [UnitController::class, 'destroy']
            )->middleware(
                'permission:units.delete'
            );


            /*
            |--------------------------------------------------------------------------
            | Tenants
            |--------------------------------------------------------------------------
            */

            Route::get(
                'tenants',
                [TenantController::class, 'index']
            )->middleware('permission:tenants.view');

            Route::post(
                'tenants',
                [TenantController::class, 'store']
            )->middleware('permission:tenants.create');

            Route::get(
                'tenants/{tenant}',
                [TenantController::class, 'show']
            )->middleware('permission:tenants.view');

            Route::put(
                'tenants/{tenant}',
                [TenantController::class, 'update']
            )->middleware('permission:tenants.update');

            Route::patch(
                'tenants/{tenant}',
                [TenantController::class, 'update']
            )->middleware('permission:tenants.update');

            Route::delete(
                'tenants/{tenant}',
                [TenantController::class, 'destroy']
            )->middleware('permission:tenants.delete');


            /*
            |--------------------------------------------------------------------------
            | Meters
            |--------------------------------------------------------------------------
            */

            Route::get(
                'meters',
                [MeterController::class, 'index']
            )->middleware('permission:meters.view');

            Route::post(
                'meters',
                [MeterController::class, 'store']
            )->middleware('permission:meters.create');

            Route::get(
                'meters/{meter}',
                [MeterController::class, 'show']
            )->middleware('permission:meters.view');

            Route::put(
                'meters/{meter}',
                [MeterController::class, 'update']
            )->middleware('permission:meters.update');

            Route::patch(
                'meters/{meter}',
                [MeterController::class, 'update']
            )->middleware('permission:meters.update');

            Route::delete(
                'meters/{meter}',
                [MeterController::class, 'destroy']
            )->middleware('permission:meters.delete');


            /*
            |--------------------------------------------------------------------------
            | Tenancies
            |--------------------------------------------------------------------------
            */

            Route::get(
                'tenancies',
                [TenancyController::class, 'index']
            )->middleware(
                'permission:tenancies.view'
            );

            Route::post(
                'tenancies',
                [TenancyController::class, 'store']
            )->middleware(
                'permission:tenancies.create'
            );

            Route::get(
                'tenancies/{tenancy}',
                [TenancyController::class, 'show']
            )->middleware(
                'permission:tenancies.view'
            );

            Route::put(
                'tenancies/{tenancy}',
                [TenancyController::class, 'update']
            )->middleware(
                'permission:tenancies.update'
            );

            Route::patch(
                'tenancies/{tenancy}',
                [TenancyController::class, 'update']
            )->middleware(
                'permission:tenancies.update'
            );

            Route::delete(
                'tenancies/{tenancy}',
                [TenancyController::class, 'destroy']
            )->middleware(
                'permission:tenancies.delete'
            );


            /*
            |--------------------------------------------------------------------------
            | Meter Assignments
            |--------------------------------------------------------------------------
            */

            Route::get(
                'meter-assignments',
                [MeterAssignmentController::class, 'index']
            )->middleware(
                'permission:meter_assignments.view'
            );

            Route::post(
                'meter-assignments',
                [MeterAssignmentController::class, 'store']
            )->middleware(
                'permission:meter_assignments.create'
            );

            Route::get(
                'meter-assignments/{meterAssignment}',
                [MeterAssignmentController::class, 'show']
            )->middleware(
                'permission:meter_assignments.view'
            );

            Route::put(
                'meter-assignments/{meterAssignment}',
                [MeterAssignmentController::class, 'update']
            )->middleware(
                'permission:meter_assignments.update'
            );

            Route::patch(
                'meter-assignments/{meterAssignment}',
                [MeterAssignmentController::class, 'update']
            )->middleware(
                'permission:meter_assignments.update'
            );

            Route::delete(
                'meter-assignments/{meterAssignment}',
                [MeterAssignmentController::class, 'destroy']
            )->middleware(
                'permission:meter_assignments.delete'
            );

            /*
            |--------------------------------------------------------------------------
            | Water Tariffs
            |--------------------------------------------------------------------------
            */

            Route::get(
                'water-tariffs',
                [WaterTariffController::class, 'index']
            )->middleware(
                'permission:water_tariffs.view'
            );

            Route::post(
                'water-tariffs',
                [WaterTariffController::class, 'store']
            )->middleware(
                'permission:water_tariffs.create'
            );

            Route::get(
                'water-tariffs/{waterTariff}',
                [WaterTariffController::class, 'show']
            )->middleware(
                'permission:water_tariffs.view'
            );

            Route::put(
                'water-tariffs/{waterTariff}',
                [WaterTariffController::class, 'update']
            )->middleware(
                'permission:water_tariffs.update'
            );

            Route::patch(
                'water-tariffs/{waterTariff}',
                [WaterTariffController::class, 'update']
            )->middleware(
                'permission:water_tariffs.update'
            );


            /*
            |--------------------------------------------------------------------------
            | Billing Configurations
            |--------------------------------------------------------------------------
            */

            Route::get(
                'billing-configurations',
                [BillingConfigurationController::class, 'index']
            )->middleware(
                'permission:billing_configurations.view'
            );

            Route::post(
                'billing-configurations',
                [BillingConfigurationController::class, 'store']
            )->middleware(
                'permission:billing_configurations.create'
            );

            Route::get(
                'billing-configurations/{billingConfiguration}',
                [BillingConfigurationController::class, 'show']
            )->middleware(
                'permission:billing_configurations.view'
            );

            Route::put(
                'billing-configurations/{billingConfiguration}',
                [BillingConfigurationController::class, 'update']
            )->middleware(
                'permission:billing_configurations.update'
            );

            Route::patch(
                'billing-configurations/{billingConfiguration}',
                [BillingConfigurationController::class, 'update']
            )->middleware(
                'permission:billing_configurations.update'
            );
        });

});