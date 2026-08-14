<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\User;
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
                'email' => 'landlord@example.com',
                'name' => 'Default Landlord',
                'password' => bcrypt('Password@123'),
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
        | 5. Units
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
        | 6. Sample Tenant
        |--------------------------------------------------------------------------
        */

        $tenant = Tenant::updateOrCreate(
            [
                'email' => 'tenant@example.com',
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
            'Landlord: landlord@example.com'
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
        );
    }
}