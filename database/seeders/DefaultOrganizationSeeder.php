<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\BillingConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Organization
        |--------------------------------------------------------------------------
        */

        $organization = Organization::updateOrCreate(
            [
                'name' => 'Default Water Management',
                'registration_number' => Str::random(10),
                'phone' => 'DEFAULT',
                'email' => 'default@example.com',
                'address' => 'Kampala, Uganda',
                'status' => 'active',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Landlord User
        |--------------------------------------------------------------------------
        */

        $landlord = User::updateOrCreate(
            [
                'email' => 'rahimkayiwa@gmail.com',
                'name' => 'Default Landlord',
                'password' => bcrypt('Landlord@1234'),
                'organization_id' => $organization->id,
                'email_verified_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Assign Landlord Role
        |--------------------------------------------------------------------------
        */

        if (method_exists($landlord, 'assignRole')) {
            $landlord->assignRole('Landlord');
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Property
        |--------------------------------------------------------------------------
        */

        $property = Property::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'property_code' => 'PROP-001',
            ],
            [
                'name' => 'Default Residential Property',
                'address' => 'Kampala, Uganda',
                'city' => 'Kampala',
                'district' => 'Central',
                'latitude' => 0.0,
                'longitude' => 0.0,
                'status' => 'active',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Billing Configuration
        |--------------------------------------------------------------------------
        */

        $billingConfiguration = BillingConfiguration::updateOrCreate(
            [
                'property_id' => $property->id,
                'name' => 'Default Billing Configuration',

                'water_percentage' => 75.00,
                'service_fee_percentage' => 5.00,
                'vat_percentage' => 10.00,
                'gateway_fee_percentage' => 4.00,
                'landlord_percentage' => 3.00,
                'saas_percentage' => 3.00,

                'effective_from' => now()->toDateString(),
                'effective_to' => null,

                'status' => 'active',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Units
        |--------------------------------------------------------------------------
        */

        $units = [
            [
                'property_id' => $property->id,
                'unit_number' => '101',
                'floor' => '1',
                'description' => 'First floor unit',
                'status' => 'occupied',
            ],
            [
                'property_id' => $property->id,
                'unit_number' => '102',
                'floor' => '1',
                'description' => 'First floor unit',
                'status' => 'vacant',
            ],
        ];

        foreach ($units as $unitData) {

            Unit::updateOrCreate(
                [
                    'property_id' => $property->id,
                    'unit_number' => $unitData['unit_number'],
                    'floor' => $unitData['floor'],
                    'description' => $unitData['description'],
                    'status' => $unitData['status'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Sample Tenant
        |--------------------------------------------------------------------------
        */

        $tenant = Tenant::updateOrCreate(
            [
                'email' => 'devkrahim@gmail.com',
            ],
            [
                'organization_id' => $organization->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '256700000001',
                'status' => 'active',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Output
        |--------------------------------------------------------------------------
        */

        $this->command->info(
            'Default organization created: ' .
            $organization->name
        );

        $this->command->info(
            'Billing Configuration created: ' .
            $billingConfiguration->name
        );

        $this->command->info(
            'Landlord: rahimkayiwa@gmail.com'
        );

        $this->command->info(
            'Password: Password@123'
        );

        $this->command->info(
            'Property: ' .
            $property->name
        );

        $this->command->info(
            'Units created: ' .
            count($units)
        );

        $this->command->info(
            'Tenant: tenant@example.com'
            . ' (' . $tenant->first_name . ' ' . $tenant->last_name . ')'
        );
    }
}