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
            'water-tariffs.view',
            'water-tariffs.create',
            'water-tariffs.update',
            'water-tariffs.delete',
            'water-tariffs.manage',

            // Billing Configuration
            'billing-configurations.view',
            'billing-configurations.create',
            'billing-configurations.update',
            'billing-configurations.delete',
            'billing-configurations.manage',

            // Meters
            'meters.view',
            'meters.create',
            'meters.update',
            'meters.delete',
            'meters.manage',

            // Meter Assignments
            'meter-assignments.view',
            'meter-assignments.create',
            'meter-assignments.update',
            'meter-assignments.delete',
            'meter-assignments.manage',

            // Meter Readings
            'meter-readings.view',
            'meter-readings.create',
            'meter-readings.update',
            'meter-readings.delete',

            // Meter Events / Tamper
            'meter-events.view',
            'meter-events.create',
            'meter-events.update',
            'meter-events.resolve',
            'meter-events.manage',

            // STS
            'sts.view',
            'sts.generate-token',
            'sts.clear-tamper',
            'sts.recharge',
            'sts.manage',

            // Water Vending
            'water-vending.view',
            'water-vending.create',
            'water-vending.process',
            'water-vending.cancel',
            'water-vending.manage',

            // Payments
            'payments.view',
            'payments.create',
            'payments.process',
            'payments.verify',
            'payments.refund',
            'payments.reverse',
            'payments.manage',

            // Payment Transactions
            'payment-transactions.view',
            'payment-transactions.manage',

            // Payment Providers
            'payment-providers.view',
            'payment-providers.create',
            'payment-providers.update',
            'payment-providers.delete',
            'payment-providers.manage',

            // Payment Provider Accounts
            'payment-provider-accounts.view',
            'payment-provider-accounts.create',
            'payment-provider-accounts.update',
            'payment-provider-accounts.delete',
            'payment-provider-accounts.manage',

            // Payment Webhooks
            'payment-webhooks.view',
            'payment-webhooks.manage',

            // Payment Allocations
            'payment-allocations.view',
            'payment-allocations.create',
            'payment-allocations.update',
            'payment-allocations.manage',

            // Water Wallet
            'water-wallet.view',
            'water-wallet.manage',

            // Water Wallet Transactions
            'water-wallet-transactions.view',
            'water-wallet-transactions.manage',

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
            'landlord-wallet.view',
            'landlord-wallet.manage',

            // Landlord Wallet Transactions
            'landlord-wallet-transactions.view',
            'landlord-wallet-transactions.manage',

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
            'audit-logs.view',
            'audit-logs.export',

            // System Settings
            'system-settings.view',
            'system-settings.create',
            'system-settings.update',
            'system-settings.delete',
            'system-settings.manage',

            // API Credentials
            'api-credentials.view',
            'api-credentials.create',
            'api-credentials.update',
            'api-credentials.delete',
            'api-credentials.manage',

            // Webhooks
            'webhooks.view',
            'webhooks.manage',

            // API Logs
            'api-logs.view',
            'api-logs.export',

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
            'reports.water-consumption',
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

                'water-tariffs.view',
                'water-tariffs.create',
                'water-tariffs.update',

                'billing-configurations.view',
                'billing-configurations.create',
                'billing-configurations.update',

                'meters.view',

                'meter-assignments.view',
                'meter-assignments.create',
                'meter-assignments.update',

                'meter-readings.view',

                'meter-events.view',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water-vending.view',
                'water-vending.create',
                'water-vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'payment-transactions.view',

                'payment-allocations.view',

                'water-wallet.view',

                'water-wallet-transactions.view',

                'nwsc.view',
                'nwsc.accounts.view',
                'nwsc.bills.view',
                'nwsc.payments.view',

                'landlord-wallet.view',
                'landlord-wallet-transactions.view',

                'settlements.view',

                'withdrawals.view',
                'withdrawals.create',

                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.water-consumption',
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

                'water-tariffs.view',

                'billing-configurations.view',

                'meters.view',
                'meter-assignments.view',

                'meter-readings.view',

                'meter-events.view',
                'meter-events.resolve',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water-vending.view',
                'water-vending.create',
                'water-vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'reports.view',
                'reports.water-consumption',
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

                'meter-assignments.view',

                'meter-readings.view',

                'meter-events.view',
                'meter-events.resolve',

                'sts.view',
                'sts.generate-token',
                'sts.recharge',

                'water-vending.view',
                'water-vending.create',
                'water-vending.process',

                'payments.view',
                'payments.create',
                'payments.verify',

                'reports.view',
                'reports.payments',
                'reports.water-consumption',
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

                'payment-transactions.view',

                'payment-allocations.view',

                'water-wallet.view',
                'water-wallet-transactions.view',

                'nwsc.view',
                'nwsc.bills.view',
                'nwsc.payments.view',
                'nwsc.payments.create',
                'nwsc.payments.process',

                'landlord-wallet.view',
                'landlord-wallet-transactions.view',

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
                'meter-readings.view',

                'meter-events.view',
                'meter-events.resolve',

                'sts.view',

                'water-vending.view',

                'payments.view',
                'payment-transactions.view',

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
                'meter-readings.view',

                'sts.view',

                'water-vending.view',
                'water-vending.create',

                'payments.view',
                'payments.create',

                'payment-transactions.view',

                'water-wallet.view',

                'notifications.view',

                'reports.view',
                'reports.water-consumption',
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