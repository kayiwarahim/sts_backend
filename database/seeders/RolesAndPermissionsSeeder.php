<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Reset cached roles and permissions
        |--------------------------------------------------------------------------
        */

        app()[PermissionRegistrar::class]->forgetCachedPermissions();


        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Dashboard
            'dashboard.view',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage',

            // Roles & Permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.manage',

            // Organizations / Landlords
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'organizations.delete',
            'organizations.manage',

            // Properties
            'properties.view',
            'properties.create',
            'properties.update',
            'properties.delete',
            'properties.manage',

            // Units
            'units.view',
            'units.create',
            'units.update',
            'units.delete',
            'units.manage',

            // Tenants
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.delete',
            'tenants.manage',

            // Tenancies
            'tenancies.view',
            'tenancies.create',
            'tenancies.update',
            'tenancies.delete',
            'tenancies.manage',

            // Water Tariffs
            'water_tariffs.view',
            'water_tariffs.create',
            'water_tariffs.update',
            'water_tariffs.delete',
            'water_tariffs.manage',

            // Billing Configuration
            'billing_configurations.view',
            'billing_configurations.create',
            'billing_configurations.update',
            'billing_configurations.delete',
            'billing_configurations.manage',

            // Meters
            'meters.view',
            'meters.create',
            'meters.update',
            'meters.delete',
            'meters.manage',

            // Meter Assignments
            'meter_assignments.view',
            'meter_assignments.create',
            'meter_assignments.update',
            'meter_assignments.delete',
            'meter_assignments.manage',

            // Meter Readings
            'meter_readings.view',
            'meter_readings.create',
            'meter_readings.update',
            'meter_readings.delete',

            // Meter Events / Tamper
            'meter_events.view',
            'meter_events.create',
            'meter_events.update',
            'meter_events.resolve',
            'meter_events.manage',

            // STS
            'sts.view',
            'sts.generate-token',
            'sts.clear-tamper',
            'sts.recharge',
            'sts.manage',

            // Water Vending
            'water_vending.view',
            'water_vending.create',
            'water_vending.process',
            'water_vending.cancel',
            'water_vending.manage',

            // Payments
            'payments.view',
            'payments.create',
            'payments.process',
            'payments.verify',
            'payments.refund',
            'payments.reverse',
            'payments.manage',

            // Payment Transactions
            'payment_transactions.view',
            'payment_transactions.manage',

            // Payment Providers
            'payment_providers.view',
            'payment_providers.create',
            'payment_providers.update',
            'payment_providers.delete',
            'payment_providers.manage',

            // Payment Provider Accounts
            'payment_provider-accounts.view',
            'payment_provider-accounts.create',
            'payment_provider-accounts.update',
            'payment_provider-accounts.delete',
            'payment_provider-accounts.manage',

            // Payment Webhooks
            'payment_webhooks.view',
            'payment_webhooks.manage',

            // Payment Allocations
            'payment_allocations.view',
            'payment_allocations.create',
            'payment_allocations.update',
            'payment_allocations.manage',

            // Water Wallet
            'water_wallet.view',
            'water_wallet.manage',

            // Water Wallet Transactions
            'water_wallet-transactions.view',
            'water_wallet-transactions.manage',

            // NWSC
            'nwsc.view',
            'nwsc.accounts.view',
            'nwsc.accounts.create',
            'nwsc.accounts.update',
            'nwsc.accounts.delete',
            'nwsc.bills.view',
            'nwsc.bills.sync',
            'nwsc.payments.view',
            'nwsc.payments.create',
            'nwsc.payments.process',
            'nwsc.manage',

            // Landlord Wallet
            'landlord_wallet.view',
            'landlord_wallet.manage',

            // Landlord Wallet Transactions
            'landlord_wallet-transactions.view',
            'landlord_wallet-transactions.manage',

            // Settlements
            'settlements.view',
            'settlements.create',
            'settlements.process',
            'settlements.approve',
            'settlements.manage',

            // Landlord Withdrawals
            'withdrawals.view',
            'withdrawals.create',
            'withdrawals.approve',
            'withdrawals.process',
            'withdrawals.cancel',
            'withdrawals.manage',

            // Accounting / Ledger
            'ledger.view',
            'ledger.create',
            'ledger.update',
            'ledger.manage',

            // Notifications
            'notifications.view',
            'notifications.send',
            'notifications.manage',

            // Audit Logs
            'audit_logs.view',
            'audit_logs.export',

            // System Settings
            'system_settings.view',
            'system_settings.create',
            'system_settings.update',
            'system_settings.delete',
            'system_settings.manage',

            // API Credentials
            'api_credentials.view',
            'api_credentials.create',
            'api_credentials.update',
            'api_credentials.delete',
            'api_credentials.manage',

            // Webhooks
            'webhooks.view',
            'webhooks.manage',

            // API Logs
            'api_logs.view',
            'api_logs.export',

            // Reconciliation
            'reconciliation.view',
            'reconciliation.create',
            'reconciliation.resolve',
            'reconciliation.export',
            'reconciliation.manage',

            // Reports
            'reports.view',
            'reports.export',
            'reports.financial',
            'reports.water_consumption',
            'reports.payments',
            'reports.settlements',
            'reports.meter',
        ];


        /*
        |--------------------------------------------------------------------------
        | Create Permissions
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $roles = [

            /*
            |--------------------------------------------------------------------------
            | Super Admin
            |--------------------------------------------------------------------------
            */

            'Super Admin' => [
                '*',
            ],


            /*
            |--------------------------------------------------------------------------
            | Landlord
            |--------------------------------------------------------------------------
            */

            'Landlord' => [
                'dashboard.view',

                'properties.view',
                'properties.create',
                'properties.update',
                'properties.delete',

                'units.view',
                'units.create',
                'units.update',
                'units.delete',

                'tenants.view',
                'tenants.create',
                'tenants.update',
                'tenants.delete',

                'tenancies.view',
                'tenancies.create',
                'tenancies.update',
                'tenancies.delete',

                'water_tariffs.view',
                'water_tariffs.create',
                'water_tariffs.update',

                'billing_configurations.view',
                'billing_configurations.create',
                'billing_configurations.update',

                'meters.view',

                'meter_assignments.view',
                'meter_assignments.create',
                'meter_assignments.update',

                'meter_readings.view',

                'meter_events.view',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water_vending.view',
                'water_vending.create',
                'water_vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'payment_transactions.view',

                'payment_allocations.view',

                'water_wallet.view',

                'water_wallet-transactions.view',

                'nwsc.view',
                'nwsc.accounts.view',
                'nwsc.bills.view',
                'nwsc.payments.view',

                'landlord_wallet.view',
                'landlord_wallet-transactions.view',

                'settlements.view',

                'withdrawals.view',
                'withdrawals.create',

                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.water_consumption',
                'reports.payments',
                'reports.settlements',
                'reports.meter',
            ],


            /*
            |--------------------------------------------------------------------------
            | Property Manager
            |--------------------------------------------------------------------------
            */

            'Property Manager' => [
                'dashboard.view',

                'properties.view',
                'properties.update',

                'units.view',
                'units.create',
                'units.update',

                'tenants.view',
                'tenants.create',
                'tenants.update',

                'tenancies.view',
                'tenancies.create',
                'tenancies.update',

                'water_tariffs.view',

                'billing_configurations.view',

                'meters.view',
                'meter_assignments.view',

                'meter_readings.view',

                'meter_events.view',
                'meter_events.resolve',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water_vending.view',
                'water_vending.create',
                'water_vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'reports.view',
                'reports.water_consumption',
                'reports.payments',
                'reports.meter',
            ],


            /*
            |--------------------------------------------------------------------------
            | Staff
            |--------------------------------------------------------------------------
            */

            'Staff' => [
                'dashboard.view',

                'properties.view',

                'units.view',

                'tenants.view',
                'tenants.create',
                'tenants.update',

                'tenancies.view',
                'tenancies.create',
                'tenancies.update',

                'meters.view',

                'meter_assignments.view',

                'meter_readings.view',

                'meter_events.view',
                'meter_events.resolve',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water_vending.view',
                'water_vending.create',
                'water_vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'reports.view',
                'reports.payments',
                'reports.water_consumption',
            ],


            /*
            |--------------------------------------------------------------------------
            | Finance
            |--------------------------------------------------------------------------
            */

            'Finance' => [
                'dashboard.view',

                'payments.view',
                'payments.verify',
                'payments.refund',
                'payments.reverse',

                'payment_transactions.view',

                'payment_allocations.view',

                'water_wallet.view',
                'water_wallet-transactions.view',

                'nwsc.view',
                'nwsc.bills.view',
                'nwsc.payments.view',
                'nwsc.payments.create',
                'nwsc.payments.process',

                'landlord_wallet.view',
                'landlord_wallet-transactions.view',

                'settlements.view',
                'settlements.create',
                'settlements.process',
                'settlements.approve',

                'withdrawals.view',
                'withdrawals.approve',
                'withdrawals.process',

                'ledger.view',
                'ledger.create',
                'ledger.manage',

                'reconciliation.view',
                'reconciliation.create',
                'reconciliation.resolve',
                'reconciliation.export',

                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.payments',
                'reports.settlements',
            ],


            /*
            |--------------------------------------------------------------------------
            | Support
            |--------------------------------------------------------------------------
            */

            'Support' => [
                'dashboard.view',

                'properties.view',
                'units.view',

                'tenants.view',

                'tenancies.view',

                'meters.view',
                'meter_readings.view',

                'meter_events.view',
                'meter_events.resolve',

                'sts.view',

                'water_vending.view',

                'payments.view',
                'payment_transactions.view',

                'notifications.view',

                'reports.view',
            ],


            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */

            'Tenant' => [
                'dashboard.view',

                'meters.view',
                'meter_readings.view',

                'sts.view',

                'water_vending.view',
                'water_vending.create',

                'payments.view',
                'payments.create',

                'payment_transactions.view',

                'water_wallet.view',

                'notifications.view',

                'reports.view',
                'reports.water_consumption',
                'reports.payments',
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Create Roles and Assign Permissions
        |--------------------------------------------------------------------------
        */

        foreach ($roles as $roleName => $rolePermissions) {

            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Super Admin
            |--------------------------------------------------------------------------
            */

            if ($roleName === 'Super Admin') {
                $role->syncPermissions(
                    Permission::all()
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Other Roles
            |--------------------------------------------------------------------------
            */

            $role->syncPermissions(
                Permission::whereIn(
                    'name',
                    $rolePermissions
                )->get()
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Clear Permission Cache
        |--------------------------------------------------------------------------
        */

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}