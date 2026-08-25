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
use App\Http\Controllers\Api\PaymentAllocationController;
use App\Http\Controllers\Api\StsController;
use App\Http\Controllers\Api\MobileMoneyPaymentController;
use App\Http\Controllers\Api\RelworxWebhookController;
use App\Http\Controllers\Api\AdminPortalController;
use App\Http\Controllers\Api\LandlordPortalController;
use App\Http\Controllers\Api\TenantPortalController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ReconciliationRecordController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Admin\DatabaseBackupController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\LandlordTenantController;
use App\Http\Controllers\Api\MeterPurchaseLookupController;


/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/landlord', [LandlordRegistrationController::class, 'register']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1');
});


/*
|--------------------------------------------------------------------------
| Public Mobile Money Routes
|--------------------------------------------------------------------------
*/

Route::get('/water-purchase/meter/{meterNumber}',[MeterPurchaseLookupController::class,'show',])->middleware('throttle:20,1');

Route::prefix('mobile-money')->group(function () {
    Route::post('/payments', [MobileMoneyPaymentController::class, 'initiate'])->middleware('throttle:10,1');
    Route::get('/payments/{reference}/status', [MobileMoneyPaymentController::class, 'status'])->middleware('throttle:30,1');
});


/*
|--------------------------------------------------------------------------
| Relworx Webhook
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/relworx', [RelworxWebhookController::class, 'handle']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | Payment Allocations
    |--------------------------------------------------------------------------
    */

    Route::post('/payments/{payment}/allocate', [PaymentAllocationController::class, 'allocate'])->middleware('permission:payment_allocations.create');


    /*
    |--------------------------------------------------------------------------
    | STS Operations
    |--------------------------------------------------------------------------
    */

    Route::prefix('sts')->group(function () {
        Route::get('/meters/{meter}/info', [StsController::class, 'meterInfo'])->middleware('permission:sts.view');
        Route::post('/meters/{meter}/vend', [StsController::class, 'vend'])->middleware('permission:sts.generate-token');
        Route::post('/meters/{meter}/clear-credit', [StsController::class, 'clearCredit'])->middleware('permission:sts.manage');
        Route::post('/meters/{meter}/clear-tamper', [StsController::class, 'clearTamper'])->middleware('permission:sts.clear-tamper');
    });


    /*
    |--------------------------------------------------------------------------
    | Super Admin Portal
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Super Admin')->prefix('admin')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard/summary', [AdminPortalController::class, 'dashboard'])->middleware('permission:dashboard.view');
        Route::get('/payments', [AdminPortalController::class, 'payments'])->middleware('permission:payments.view');


        /*
        |--------------------------------------------------------------------------
        | Organizations
        |--------------------------------------------------------------------------
        */

        Route::get('/organizations', [OrganizationController::class, 'index'])->middleware('permission:organizations.view');
        Route::post('/organizations', [OrganizationController::class, 'store'])->middleware('permission:organizations.create');
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->middleware('permission:organizations.view');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.update');
        Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.update');
        Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->middleware('permission:organizations.delete');


        /*
        |--------------------------------------------------------------------------
        | Database Backups
        |--------------------------------------------------------------------------
        |
        | No separate backup permissions currently exist in the seeder.
        | These remain protected by the Super Admin role.
        |--------------------------------------------------------------------------
        */

        Route::get('/database-backups', [DatabaseBackupController::class, 'index']);
        Route::post('/database-backups', [DatabaseBackupController::class, 'store']);
        Route::get('/database-backups/{databaseBackup}', [DatabaseBackupController::class, 'show']);
        Route::get('/database-backups/{databaseBackup}/download', [DatabaseBackupController::class, 'download']);
        Route::post('/database-backups/{databaseBackup}/restore', [DatabaseBackupController::class, 'restore']);
        Route::post('/database-backups/prune', [DatabaseBackupController::class, 'prune']);
        Route::post('/database-backups/{databaseBackup}/mark-reconciled', [DatabaseBackupController::class, 'markReconciled']);
        Route::delete('/database-backups/{databaseBackup}', [DatabaseBackupController::class, 'destroy']);


        /*
        |--------------------------------------------------------------------------
        | User Management
        |--------------------------------------------------------------------------
        */

        Route::get('/users/meta', [UserManagementController::class, 'meta'])->middleware('permission:users.view');
        Route::get('/users', [UserManagementController::class, 'index'])->middleware('permission:users.view');
        Route::post('/users', [UserManagementController::class, 'store'])->middleware('permission:users.create');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->middleware('permission:users.view');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->middleware('permission:users.update');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->middleware('permission:users.update');
        Route::post('/users/{user}/send-password-link', [UserManagementController::class, 'resendPasswordSetup'])->middleware('permission:users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->middleware('permission:users.delete');
    });


    /*
    |--------------------------------------------------------------------------
    | Tenant Portal
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Tenant')->prefix('tenant/me')->group(function () {
        Route::get('/dashboard', [TenantPortalController::class, 'dashboard'])->middleware('permission:dashboard.view');
        Route::get('/meter', [TenantPortalController::class, 'meter'])->middleware('permission:meters.view');
        Route::get('/payments', [TenantPortalController::class, 'payments'])->middleware('permission:payments.view');
        Route::get('/tokens', [TenantPortalController::class, 'tokens'])->middleware('permission:sts.view');
    });


    /*
    |--------------------------------------------------------------------------
    | Landlord Portal
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Landlord')->prefix('landlord')->group(function () {

        Route::get('/dashboard/summary', [LandlordPortalController::class, 'dashboard'])->middleware('permission:dashboard.view');
        Route::get('/payments', [LandlordPortalController::class, 'payments'])->middleware('permission:payments.view');
        Route::get('/water-wallet', [LandlordPortalController::class, 'waterWallet'])->middleware('permission:water_wallet.view');


        /*
        |--------------------------------------------------------------------------
        | Landlord Tenant Management
        |--------------------------------------------------------------------------
        */

        Route::get('/tenants', [LandlordTenantController::class, 'index'])->middleware('permission:tenants.view');
        Route::post('/tenants', [LandlordTenantController::class, 'store'])->middleware('permission:tenants.create');
        Route::get('/tenants/{tenant}', [LandlordTenantController::class, 'show'])->middleware('permission:tenants.view');
        Route::put('/tenants/{tenant}', [LandlordTenantController::class, 'update'])->middleware('permission:tenants.update');
        Route::patch('/tenants/{tenant}', [LandlordTenantController::class, 'update'])->middleware('permission:tenants.update');
        Route::post('/tenants/{tenant}/send-password-link', [LandlordTenantController::class, 'resendPasswordSetup'])->middleware('permission:tenants.update');
        Route::delete('/tenants/{tenant}', [LandlordTenantController::class, 'destroy'])->middleware('permission:tenants.delete');
    });


    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::prefix('reports')->group(function () {
        Route::get('/financial-summary', [ReportController::class, 'financialSummary'])->middleware('permission:reports.financial');
        Route::get('/payments', [ReportController::class, 'payments'])->middleware('permission:reports.payments');
        Route::get('/water-vendings', [ReportController::class, 'waterVendings'])->middleware('permission:reports.water_consumption');
        Route::get('/ledger', [ReportController::class, 'ledger'])->middleware('permission:reports.financial');
    });


    /*
    |--------------------------------------------------------------------------
    | Report Exports
    |--------------------------------------------------------------------------
    */

    Route::prefix('exports')->middleware('permission:reports.export')->group(function () {
        Route::get('/payments', [ReportExportController::class, 'payments']);
        Route::get('/water-vendings', [ReportExportController::class, 'waterVendings']);
        Route::get('/ledger', [ReportExportController::class, 'ledger']);
        Route::get('/reconciliation/payments', [ReportExportController::class, 'paymentReconciliation']);
        Route::get('/reconciliation/sts', [ReportExportController::class, 'stsReconciliation']);
    });


    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    */

    Route::prefix('reconciliation')->middleware('permission:reconciliation.view')->group(function () {
        Route::get('/payments', [ReconciliationController::class, 'payments']);
        Route::get('/sts', [ReconciliationController::class, 'sts']);
    });


    /*
    |--------------------------------------------------------------------------
    | Reconciliation Records
    |--------------------------------------------------------------------------
    */

    Route::prefix('reconciliation-records')->group(function () {
        Route::get('/', [ReconciliationRecordController::class, 'index'])->middleware('permission:reconciliation.view');
        Route::post('/run', [ReconciliationRecordController::class, 'run'])->middleware('permission:reconciliation.create');
        Route::patch('/{record}/resolve', [ReconciliationRecordController::class, 'resolve'])->middleware('permission:reconciliation.resolve');
    });


    /*
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    */

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view');


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | These operate on the authenticated user's own notifications.
    |--------------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->middleware('permission:notifications.view');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:notifications.view');
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('permission:notifications.view');
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('permission:notifications.view');
    });


    /*
    |--------------------------------------------------------------------------
    | Organization Scoped Management
    |--------------------------------------------------------------------------
    */

    Route::middleware('organization')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Properties
        |--------------------------------------------------------------------------
        */

        Route::get('properties', [PropertyController::class, 'index'])->middleware('permission:properties.view');
        Route::post('properties', [PropertyController::class, 'store'])->middleware('permission:properties.create');
        Route::get('properties/{property}', [PropertyController::class, 'show'])->middleware('permission:properties.view');
        Route::put('properties/{property}', [PropertyController::class, 'update'])->middleware('permission:properties.update');
        Route::patch('properties/{property}', [PropertyController::class, 'update'])->middleware('permission:properties.update');
        Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->middleware('permission:properties.delete');


        /*
        |--------------------------------------------------------------------------
        | Units
        |--------------------------------------------------------------------------
        */

        Route::get('properties/{property}/units', [UnitController::class, 'index'])->middleware('permission:units.view');
        Route::post('properties/{property}/units', [UnitController::class, 'store'])->middleware('permission:units.create');
        Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
        Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update');
        Route::patch('units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete');


        /*
        |--------------------------------------------------------------------------
        | Tenants
        |--------------------------------------------------------------------------
        */

        Route::get('tenants', [TenantController::class, 'index'])->middleware('permission:tenants.view');
        Route::post('tenants', [TenantController::class, 'store'])->middleware('permission:tenants.create');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('permission:tenants.view');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update');
        Route::patch('tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.delete');


        /*
        |--------------------------------------------------------------------------
        | Meters
        |--------------------------------------------------------------------------
        */

        Route::get('meters', [MeterController::class, 'index'])->middleware('permission:meters.view');
        Route::post('meters', [MeterController::class, 'store'])->middleware('permission:meters.create');
        Route::get('meters/{meter}', [MeterController::class, 'show'])->middleware('permission:meters.view');
        Route::put('meters/{meter}', [MeterController::class, 'update'])->middleware('permission:meters.update');
        Route::patch('meters/{meter}', [MeterController::class, 'update'])->middleware('permission:meters.update');
        Route::delete('meters/{meter}', [MeterController::class, 'destroy'])->middleware('permission:meters.delete');


        /*
        |--------------------------------------------------------------------------
        | Tenancies
        |--------------------------------------------------------------------------
        */

        Route::get('tenancies', [TenancyController::class, 'index'])->middleware('permission:tenancies.view');
        Route::post('tenancies', [TenancyController::class, 'store'])->middleware('permission:tenancies.create');
        Route::get('tenancies/{tenancy}', [TenancyController::class, 'show'])->middleware('permission:tenancies.view');
        Route::put('tenancies/{tenancy}', [TenancyController::class, 'update'])->middleware('permission:tenancies.update');
        Route::patch('tenancies/{tenancy}', [TenancyController::class, 'update'])->middleware('permission:tenancies.update');
        Route::delete('tenancies/{tenancy}', [TenancyController::class, 'destroy'])->middleware('permission:tenancies.delete');
        Route::post('tenancies/{tenancy}/transfer', [TenancyController::class, 'transfer'])->middleware('permission:tenancies.update');


        /*
        |--------------------------------------------------------------------------
        | Meter Assignments
        |--------------------------------------------------------------------------
        */

        Route::get('meter-assignments', [MeterAssignmentController::class, 'index'])->middleware('permission:meter_assignments.view');
        Route::post('meter-assignments', [MeterAssignmentController::class, 'store'])->middleware('permission:meter_assignments.create');
        Route::get('meter-assignments/{meterAssignment}', [MeterAssignmentController::class, 'show'])->middleware('permission:meter_assignments.view');
        Route::put('meter-assignments/{meterAssignment}', [MeterAssignmentController::class, 'update'])->middleware('permission:meter_assignments.update');
        Route::patch('meter-assignments/{meterAssignment}', [MeterAssignmentController::class, 'update'])->middleware('permission:meter_assignments.update');
        Route::delete('meter-assignments/{meterAssignment}', [MeterAssignmentController::class, 'destroy'])->middleware('permission:meter_assignments.delete');
        Route::post('meter-assignments/{meterAssignment}/reassign', [MeterAssignmentController::class, 'reassign'])->middleware('permission:meter_assignments.update');


        /*
        |--------------------------------------------------------------------------
        | Water Tariffs
        |--------------------------------------------------------------------------
        */

        Route::get('water-tariffs', [WaterTariffController::class, 'index'])->middleware('permission:water_tariffs.view');
        Route::post('water-tariffs', [WaterTariffController::class, 'store'])->middleware('permission:water_tariffs.create');
        Route::get('water-tariffs/{waterTariff}', [WaterTariffController::class, 'show'])->middleware('permission:water_tariffs.view');
        Route::put('water-tariffs/{waterTariff}', [WaterTariffController::class, 'update'])->middleware('permission:water_tariffs.update');
        Route::patch('water-tariffs/{waterTariff}', [WaterTariffController::class, 'update'])->middleware('permission:water_tariffs.update');


        /*
        |--------------------------------------------------------------------------
        | Billing Configurations
        |--------------------------------------------------------------------------
        */

        Route::get('billing-configurations', [BillingConfigurationController::class, 'index'])->middleware('permission:billing_configurations.view');
        Route::post('billing-configurations', [BillingConfigurationController::class, 'store'])->middleware('permission:billing_configurations.create');
        Route::get('billing-configurations/{billingConfiguration}', [BillingConfigurationController::class, 'show'])->middleware('permission:billing_configurations.view');
        Route::put('billing-configurations/{billingConfiguration}', [BillingConfigurationController::class, 'update'])->middleware('permission:billing_configurations.update');
        Route::patch('billing-configurations/{billingConfiguration}', [BillingConfigurationController::class, 'update'])->middleware('permission:billing_configurations.update');
    });
});