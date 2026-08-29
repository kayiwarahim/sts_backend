<?php

namespace Tests\Feature;

use App\Models\BillingConfiguration;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Property $property;

    protected Tenant $tenant;

    protected PaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'registration_number' => 'TEST-ORG-001',
            'phone' => '+256700000000',
            'email' => 'org@example.com',
            'address' => 'Kampala, Uganda',
            'status' => 'active',
        ]);

        $this->property = Property::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Property',
            'property_code' => 'TEST-PROP-001',
            'address' => 'Kampala, Uganda',
            'city' => 'Kampala',
            'district' => 'Kampala',
            'latitude' => 0,
            'longitude' => 0,
            'status' => 'active',
        ]);

        $this->tenant = Tenant::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'phone' => '+256700000001',
            'email' => 'tenant@example.com',
            'status' => 'active',
        ]);

        $this->provider = PaymentProvider::create([
            'name' => 'Test Provider',
            'code' => 'TEST_PROVIDER',
            'type' => 'aggregator',
            'base_url' => null,
            'is_active' => true,
            'configuration' => null,
        ]);
    }

    protected function createBillingConfiguration(
        array $overrides = []
    ): BillingConfiguration {
        return BillingConfiguration::create(
            array_merge([
                'property_id' => $this->property->id,
                'name' => 'Test Billing Configuration',

                'water_percentage' => 75,
                'service_fee_percentage' => 5,
                'vat_percentage' => 10,
                'gateway_fee_percentage' => 4,
                'landlord_percentage' => 3,
                'saas_percentage' => 3,

                'effective_from' => now()->subDay(),
                'effective_to' => null,
                'status' => 'active',
            ], $overrides)
        );
    }

    protected function createPayment(
        float $amount = 100000
    ): Payment {
        return Payment::create([
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,

            'payment_provider_id' => $this->provider->id,

            'payment_provider_account_id' => null,

            'reference' => 'TEST-PAY-'.strtoupper(uniqid()),

            'amount' => $amount,
            'currency' => 'UGX',

            'payer_phone' => '+256700000001',

            'status' => 'successful',

            'initiated_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_successful_payment_is_split_into_six_allocations(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment();

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $this->assertEquals(
            6,
            $payment
                ->fresh()
                ->allocations()
                ->count()
        );
    }

    public function test_allocation_types_are_correct(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment();

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $types = $payment
            ->fresh()
            ->allocations()
            ->pluck('allocation_type')
            ->sort()
            ->values()
            ->all();

        $expected = [
            'gateway_fee',
            'landlord',
            'saas',
            'service_fee',
            'vat',
            'water',
        ];

        sort($expected);

        $this->assertEquals(
            $expected,
            $types
        );
    }

    public function test_allocation_amounts_are_correct(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment(
            100000
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $allocations = $payment
            ->fresh()
            ->allocations
            ->keyBy('allocation_type');

        $this->assertEquals(
            75000,
            (float) $allocations['water']->amount
        );

        $this->assertEquals(
            5000,
            (float) $allocations['service_fee']->amount
        );

        $this->assertEquals(
            10000,
            (float) $allocations['vat']->amount
        );

        $this->assertEquals(
            4000,
            (float) $allocations['gateway_fee']->amount
        );

        $this->assertEquals(
            3000,
            (float) $allocations['landlord']->amount
        );

        $this->assertEquals(
            3000,
            (float) $allocations['saas']->amount
        );
    }

    public function test_allocation_percentages_are_stored_correctly(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment();

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $allocations = $payment
            ->fresh()
            ->allocations
            ->keyBy('allocation_type');

        $this->assertEquals(
            75,
            (float) $allocations['water']->percentage
        );

        $this->assertEquals(
            5,
            (float) $allocations['service_fee']->percentage
        );

        $this->assertEquals(
            10,
            (float) $allocations['vat']->percentage
        );

        $this->assertEquals(
            4,
            (float) $allocations['gateway_fee']->percentage
        );

        $this->assertEquals(
            3,
            (float) $allocations['landlord']->percentage
        );

        $this->assertEquals(
            3,
            (float) $allocations['saas']->percentage
        );
    }

    public function test_allocations_equal_payment_amount(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment(
            100000
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $allocationTotal = (float) $payment
            ->fresh()
            ->allocations()
            ->sum('amount');

        $this->assertEquals(
            (float) $payment->amount,
            $allocationTotal
        );
    }

    public function test_allocation_is_idempotent(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment();

        $service = app(
            PaymentAllocationService::class
        );

        $service->allocate($payment);

        $firstCount = $payment
            ->fresh()
            ->allocations()
            ->count();

        $service->allocate(
            $payment->fresh()
        );

        $secondCount = $payment
            ->fresh()
            ->allocations()
            ->count();

        $this->assertEquals(
            6,
            $firstCount
        );

        $this->assertEquals(
            6,
            $secondCount
        );
    }

    public function test_rounding_difference_is_absorbed_by_water_allocation(): void
    {
        $this->createBillingConfiguration();

        $payment = $this->createPayment(
            1001
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);

        $total = round(
            (float) $payment
                ->fresh()
                ->allocations()
                ->sum('amount'),
            2
        );

        $this->assertEquals(
            1001.00,
            $total
        );
    }

    public function test_invalid_percentage_configuration_is_rejected(): void
    {
        $this->createBillingConfiguration([
            'water_percentage' => 70,
        ]);

        $payment = $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Billing configuration percentages must equal 100%'
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);
    }

    public function test_missing_billing_configuration_is_rejected(): void
    {
        $payment = $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No billing configuration was effective'
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);
    }

    public function test_future_billing_configuration_is_not_used(): void
    {
        $this->createBillingConfiguration([
            'effective_from' => now()->addDay(),
        ]);

        $payment = $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No billing configuration was effective'
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);
    }

    public function test_expired_billing_configuration_is_not_used(): void
    {
        $this->createBillingConfiguration([
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDay(),
        ]);

        $payment = $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No billing configuration was effective'
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);
    }

    public function test_inactive_billing_configuration_is_not_used(): void
    {
        $this->createBillingConfiguration([
            'status' => 'inactive',
        ]);

        $payment = $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No billing configuration was effective'
        );

        app(PaymentAllocationService::class)
            ->allocate($payment);
    }
}
