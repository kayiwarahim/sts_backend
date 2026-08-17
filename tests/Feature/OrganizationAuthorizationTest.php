<?php

namespace Tests\Feature;

use App\Models\Meter;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaterTariff;
use App\Services\MeterService;
use App\Services\ReconciliationPersistenceService;
use App\Services\WaterTariffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrganizationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationOne;
    protected Organization $organizationTwo;

    protected User $landlordOne;
    protected User $landlordTwo;
    protected User $superAdmin;

    protected Property $propertyOne;
    protected Property $propertyTwo;

    protected Meter $meterOne;
    protected Meter $meterTwo;

    protected Tenant $tenantOne;
    protected Tenant $tenantTwo;

    protected PaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        Role::findOrCreate(
            'Super Admin',
            'web'
        );

        Role::findOrCreate(
            'Landlord',
            'web'
        );

        /*
        |--------------------------------------------------------------------------
        | Organizations
        |--------------------------------------------------------------------------
        */

        $this->organizationOne =
            Organization::create([
                'name' =>
                    'Organization One',

                'registration_number' =>
                    'ORG-ONE',

                'phone' =>
                    '+256700000001',

                'email' =>
                    'org1@example.com',

                'address' =>
                    'Kampala',

                'status' =>
                    'active',
            ]);

        $this->organizationTwo =
            Organization::create([
                'name' =>
                    'Organization Two',

                'registration_number' =>
                    'ORG-TWO',

                'phone' =>
                    '+256700000002',

                'email' =>
                    'org2@example.com',

                'address' =>
                    'Entebbe',

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        $this->landlordOne =
            User::create([
                'organization_id' =>
                    $this->organizationOne->id,

                'name' =>
                    'Landlord One',

                'email' =>
                    'landlord1@example.com',

                'password' =>
                    Hash::make('password'),

                'email_verified_at' =>
                    now(),
            ]);

        $this->landlordOne
            ->assignRole(
                'Landlord'
            );

        $this->landlordTwo =
            User::create([
                'organization_id' =>
                    $this->organizationTwo->id,

                'name' =>
                    'Landlord Two',

                'email' =>
                    'landlord2@example.com',

                'password' =>
                    Hash::make('password'),

                'email_verified_at' =>
                    now(),
            ]);

        $this->landlordTwo
            ->assignRole(
                'Landlord'
            );

        $this->superAdmin =
            User::create([
                'organization_id' =>
                    null,

                'name' =>
                    'Super Admin',

                'email' =>
                    'admin@example.com',

                'password' =>
                    Hash::make('password'),

                'email_verified_at' =>
                    now(),
            ]);

        $this->superAdmin
            ->assignRole(
                'Super Admin'
            );

        /*
        |--------------------------------------------------------------------------
        | Properties
        |--------------------------------------------------------------------------
        */

        $this->propertyOne =
            Property::create([
                'organization_id' =>
                    $this->organizationOne->id,

                'name' =>
                    'Property One',

                'property_code' =>
                    'PROP-ONE',

                'address' =>
                    'Kampala',

                'city' =>
                    'Kampala',

                'district' =>
                    'Central',

                'latitude' =>
                    0,

                'longitude' =>
                    0,

                'status' =>
                    'active',
            ]);

        $this->propertyTwo =
            Property::create([
                'organization_id' =>
                    $this->organizationTwo->id,

                'name' =>
                    'Property Two',

                'property_code' =>
                    'PROP-TWO',

                'address' =>
                    'Entebbe',

                'city' =>
                    'Entebbe',

                'district' =>
                    'Wakiso',

                'latitude' =>
                    0,

                'longitude' =>
                    0,

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Meters
        |--------------------------------------------------------------------------
        */

        $this->meterOne =
            Meter::create([
                'organization_id' =>
                    $this->organizationOne->id,

                'meter_number' =>
                    'METER-ORG-ONE',

                'serial_number' =>
                    'SERIAL-ORG-ONE',

                'manufacturer' =>
                    'Test',

                'model' =>
                    'STS',

                'meter_type' =>
                    '2',

                'status' =>
                    'active',

                'installed_at' =>
                    now(),
            ]);

        $this->meterTwo =
            Meter::create([
                'organization_id' =>
                    $this->organizationTwo->id,

                'meter_number' =>
                    'METER-ORG-TWO',

                'serial_number' =>
                    'SERIAL-ORG-TWO',

                'manufacturer' =>
                    'Test',

                'model' =>
                    'STS',

                'meter_type' =>
                    '2',

                'status' =>
                    'active',

                'installed_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Tenants
        |--------------------------------------------------------------------------
        */

        $this->tenantOne =
            Tenant::create([
                'organization_id' =>
                    $this->organizationOne->id,

                'first_name' =>
                    'Tenant',

                'last_name' =>
                    'One',

                'phone' =>
                    '256700000011',

                'email' =>
                    'tenant1@example.com',

                'status' =>
                    'active',
            ]);

        $this->tenantTwo =
            Tenant::create([
                'organization_id' =>
                    $this->organizationTwo->id,

                'first_name' =>
                    'Tenant',

                'last_name' =>
                    'Two',

                'phone' =>
                    '256700000022',

                'email' =>
                    'tenant2@example.com',

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Payment Provider
        |--------------------------------------------------------------------------
        */

        $this->provider =
            PaymentProvider::create([
                'name' =>
                    'Security Test Provider',

                'code' =>
                    'SECURITY_TEST',

                'type' =>
                    'aggregator',

                'base_url' =>
                    null,

                'is_active' =>
                    true,

                'configuration' =>
                    null,
            ]);
    }

    public function test_landlord_can_update_meter_in_own_organization(): void
    {
        $meter =
            app(
                MeterService::class
            )->update(
                $this->landlordOne,
                $this->meterOne,
                [
                    'model' =>
                        'Updated Model',
                ]
            );

        $this->assertEquals(
            'Updated Model',
            $meter->model
        );
    }

    public function test_landlord_cannot_update_meter_in_another_organization(): void
    {
        try {

            app(
                MeterService::class
            )->update(
                $this->landlordOne,
                $this->meterTwo,
                [
                    'model' =>
                        'Hacked Model',
                ]
            );

            $this->fail(
                'Cross-organization meter update was allowed.'
            );

        } catch (
            HttpException $exception
        ) {

            $this->assertEquals(
                403,
                $exception->getStatusCode()
            );

            $this->assertStringContainsString(
                'Unauthorized meter access',
                $exception->getMessage()
            );
        }

        $this->assertNotEquals(
            'Hacked Model',
            $this->meterTwo
                ->fresh()
                ->model
        );
    }

    public function test_super_admin_can_update_meter_in_any_organization(): void
    {
        $meter =
            app(
                MeterService::class
            )->update(
                $this->superAdmin,
                $this->meterTwo,
                [
                    'model' =>
                        'Admin Updated',
                ]
            );

        $this->assertEquals(
            'Admin Updated',
            $meter->model
        );
    }

    public function test_landlord_cannot_change_meter_organization_during_update(): void
    {
        $meter =
            app(
                MeterService::class
            )->update(
                $this->landlordOne,
                $this->meterOne,
                [
                    'organization_id' =>
                        $this->organizationTwo->id,

                    'model' =>
                        'Safe Update',
                ]
            );

        $this->assertEquals(
            $this->organizationOne->id,
            $meter->organization_id
        );

        $this->assertEquals(
            'Safe Update',
            $meter->model
        );
    }

    public function test_landlord_can_update_tariff_for_own_property(): void
    {
        $tariff =
            WaterTariff::create([
                'property_id' =>
                    $this->propertyOne->id,

                'name' =>
                    'Tariff One',

                'price_per_m3' =>
                    6500,

                'currency' =>
                    'UGX',

                'effective_from' =>
                    now()->subDay(),

                'status' =>
                    'active',
            ]);

        $result =
            app(
                WaterTariffService::class
            )->update(
                $this->landlordOne,
                $tariff,
                [
                    'price_per_m3' =>
                        7000,
                ]
            );

        $this->assertEquals(
            7000,
            (float)
            $result->price_per_m3
        );
    }

    public function test_landlord_cannot_update_tariff_for_another_organization_property(): void
    {
        $tariff =
            WaterTariff::create([
                'property_id' =>
                    $this->propertyTwo->id,

                'name' =>
                    'Foreign Tariff',

                'price_per_m3' =>
                    6500,

                'currency' =>
                    'UGX',

                'effective_from' =>
                    now()->subDay(),

                'status' =>
                    'active',
            ]);

        try {

            app(
                WaterTariffService::class
            )->update(
                $this->landlordOne,
                $tariff,
                [
                    'price_per_m3' =>
                        9999,
                ]
            );

            $this->fail(
                'Cross-organization tariff update was allowed.'
            );

        } catch (
            HttpException $exception
        ) {

            $this->assertEquals(
                403,
                $exception->getStatusCode()
            );

            $this->assertStringContainsString(
                'Unauthorized property access',
                $exception->getMessage()
            );
        }

        $this->assertEquals(
            6500,
            (float)
            $tariff
                ->fresh()
                ->price_per_m3
        );
    }

    public function test_super_admin_can_update_tariff_for_any_organization(): void
    {
        $tariff =
            WaterTariff::create([
                'property_id' =>
                    $this->propertyTwo->id,

                'name' =>
                    'Admin Tariff',

                'price_per_m3' =>
                    6500,

                'currency' =>
                    'UGX',

                'effective_from' =>
                    now()->subDay(),

                'status' =>
                    'active',
            ]);

        $result =
            app(
                WaterTariffService::class
            )->update(
                $this->superAdmin,
                $tariff,
                [
                    'price_per_m3' =>
                        7200,
                ]
            );

        $this->assertEquals(
            7200,
            (float)
            $result->price_per_m3
        );
    }

    public function test_landlord_reconciliation_only_sees_own_organization_payments(): void
    {
        $this->createSuccessfulPayment(
            $this->organizationOne,
            $this->propertyOne,
            $this->tenantOne,
            'PAY-ORG-ONE'
        );

        $this->createSuccessfulPayment(
            $this->organizationTwo,
            $this->propertyTwo,
            $this->tenantTwo,
            'PAY-ORG-TWO'
        );

        $result =
            app(
                ReconciliationPersistenceService::class
            )->reconcilePayments(
                $this->landlordOne
            );

        $this->assertEquals(
            1,
            $result['total']
        );
    }

    public function test_second_landlord_reconciliation_only_sees_second_organization(): void
    {
        $this->createSuccessfulPayment(
            $this->organizationOne,
            $this->propertyOne,
            $this->tenantOne,
            'PAY-ORG-ONE-A'
        );

        $this->createSuccessfulPayment(
            $this->organizationTwo,
            $this->propertyTwo,
            $this->tenantTwo,
            'PAY-ORG-TWO-A'
        );

        $result =
            app(
                ReconciliationPersistenceService::class
            )->reconcilePayments(
                $this->landlordTwo
            );

        $this->assertEquals(
            1,
            $result['total']
        );
    }

    public function test_super_admin_reconciliation_sees_all_organizations(): void
    {
        $this->createSuccessfulPayment(
            $this->organizationOne,
            $this->propertyOne,
            $this->tenantOne,
            'PAY-ADMIN-ONE'
        );

        $this->createSuccessfulPayment(
            $this->organizationTwo,
            $this->propertyTwo,
            $this->tenantTwo,
            'PAY-ADMIN-TWO'
        );

        $result =
            app(
                ReconciliationPersistenceService::class
            )->reconcilePayments(
                $this->superAdmin
            );

        $this->assertEquals(
            2,
            $result['total']
        );
    }

    protected function createSuccessfulPayment(
        Organization $organization,
        Property $property,
        Tenant $tenant,
        string $reference
    ): Payment {
        return Payment::create([
            'organization_id' =>
                $organization->id,

            'property_id' =>
                $property->id,

            'tenant_id' =>
                $tenant->id,

            'payment_provider_id' =>
                $this->provider->id,

            'payment_provider_account_id' =>
                null,

            'reference' =>
                $reference,

            'amount' =>
                1000,

            'currency' =>
                'UGX',

            'payer_phone' =>
                $tenant->phone,

            'status' =>
                'successful',

            'initiated_at' =>
                now(),

            'completed_at' =>
                now(),
        ]);
    }
}