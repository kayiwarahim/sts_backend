<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\MobileMoneyPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MobileMoneyPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Property $property;
    protected Tenant $tenant;
    protected PaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.relworx.base_url' =>
                'https://relworx-test.local/api',

            'services.relworx.account_no' =>
                'REL_TEST',

            'services.relworx.bearer_token' =>
                'TOKEN_TEST',
        ]);

        $this->organization =
            Organization::create([
                'name' =>
                    'Relworx Test Organization',

                'registration_number' =>
                    'REL-ORG-001',

                'phone' =>
                    '+256700000001',

                'email' =>
                    'relworx-org@example.com',

                'address' =>
                    'Kampala',

                'status' =>
                    'active',
            ]);

        $this->property =
            Property::create([
                'organization_id' =>
                    $this->organization->id,

                'name' =>
                    'Relworx Property',

                'property_code' =>
                    'REL-PROP-001',

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

        $this->tenant =
            Tenant::create([
                'organization_id' =>
                    $this->organization->id,

                'first_name' =>
                    'Relworx',

                'last_name' =>
                    'Tenant',

                'phone' =>
                    '256752225375',

                'email' =>
                    'relworx-tenant@example.com',

                'status' =>
                    'active',
            ]);

        $this->provider =
            PaymentProvider::create([
                'name' =>
                    'Relworx',

                'code' =>
                    'RELWORX',

                'type' =>
                    'aggregator',

                'base_url' =>
                    'https://relworx-test.local/api',

                'is_active' =>
                    true,

                'configuration' =>
                    null,
            ]);
    }

    protected function createPayment(
        string $status = 'processing'
    ): Payment {
        return Payment::create([
            'organization_id' =>
                $this->organization->id,

            'property_id' =>
                $this->property->id,

            'tenant_id' =>
                $this->tenant->id,

            'payment_provider_id' =>
                $this->provider->id,

            'payment_provider_account_id' =>
                null,

            'reference' =>
                'WTR-TEST-' .
                strtoupper(
                    uniqid()
                ),

            'provider_reference' =>
                'INTERNAL-' .
                strtoupper(
                    uniqid()
                ),

            'amount' =>
                1000,

            'currency' =>
                'UGX',

            'payer_phone' =>
                '+256752225375',

            'status' =>
                $status,

            'initiated_at' =>
                now(),
        ]);
    }

    public function test_pending_provider_result_keeps_payment_processing(): void
    {
        $payment =
            $this->createPayment();

        $result = app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'pending',

                'request_status' =>
                    'pending',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,

                'provider' =>
                    'AIRTEL_UGANDA',

                'amount' =>
                    1000,

                'currency' =>
                    'UGX',
            ],
            false
        );

        $this->assertEquals(
            'processing',
            $result->status
        );

        $this->assertEquals(
            'AIRTEL_UGANDA',
            $result->mobile_money_provider
        );
    }

    public function test_success_provider_result_marks_payment_successful(): void
    {
        $payment =
            $this->createPayment();

        $result = app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'success',

                'request_status' =>
                    'success',

                'message' =>
                    'Request payment completed successfully.',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,

                'msisdn' =>
                    '+256752225375',

                'amount' =>
                    1000,

                'currency' =>
                    'UGX',

                'provider' =>
                    'AIRTEL_UGANDA',

                'charge' =>
                    30,

                'provider_transaction_id' =>
                    'AIRTEL-TXN-100',

                'completed_at' =>
                    '2026-08-17T08:00:00+03:00',
            ],
            false
        );

        $result->refresh();

        $this->assertEquals(
            'successful',
            $result->status
        );

        $this->assertEquals(
            'AIRTEL_UGANDA',
            $result->mobile_money_provider
        );

        $this->assertEquals(
            'AIRTEL-TXN-100',
            $result->provider_transaction_id
        );

        $this->assertEquals(
            30,
            (float) $result->provider_charge
        );

        $this->assertNotNull(
            $result->completed_at
        );

        $this->assertNull(
            $result->failure_reason
        );
    }

    public function test_failed_provider_result_marks_payment_failed(): void
    {
        $payment =
            $this->createPayment();

        $result = app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'failed',

                'request_status' =>
                    'failed',

                'message' =>
                    'Customer declined payment.',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,

                'provider' =>
                    'MTN_UGANDA',

                'charge' =>
                    0,
            ],
            false
        );

        $result->refresh();

        $this->assertEquals(
            'failed',
            $result->status
        );

        $this->assertEquals(
            'Customer declined payment.',
            $result->failure_reason
        );

        $this->assertNotNull(
            $result->completed_at
        );
    }

    public function test_successful_payment_cannot_be_downgraded_by_later_failed_result(): void
    {
        $payment =
            $this->createPayment(
                'successful'
            );

        $result = app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'failed',

                'request_status' =>
                    'failed',

                'message' =>
                    'Late provider failure.',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,
            ],
            false
        );

        $this->assertEquals(
            'successful',
            $result->status
        );
    }

    public function test_customer_reference_mismatch_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx customer reference does not match payment.'
        );

        app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'success',

                'customer_reference' =>
                    'WRONG-REFERENCE',

                'internal_reference' =>
                    $payment->provider_reference,
            ],
            false
        );
    }

    public function test_internal_reference_mismatch_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx internal reference does not match payment.'
        );

        app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'success',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    'WRONG-INTERNAL-REFERENCE',
            ],
            false
        );
    }

    public function test_provider_amount_mismatch_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx payment amount does not match local payment.'
        );

        app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'success',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,

                'amount' =>
                    1500,

                'currency' =>
                    'UGX',
            ],
            false
        );
    }

    public function test_provider_currency_mismatch_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx payment currency does not match local payment.'
        );

        app(
            MobileMoneyPaymentService::class
        )->applyProviderResult(
            $payment,
            [
                'status' =>
                    'success',

                'customer_reference' =>
                    $payment->reference,

                'internal_reference' =>
                    $payment->provider_reference,

                'amount' =>
                    1000,

                'currency' =>
                    'USD',
            ],
            false
        );
    }

    public function test_repeating_success_result_is_idempotent(): void
    {
        $payment =
            $this->createPayment();

        $payload = [
            'status' =>
                'success',

            'request_status' =>
                'success',

            'customer_reference' =>
                $payment->reference,

            'internal_reference' =>
                $payment->provider_reference,

            'amount' =>
                1000,

            'currency' =>
                'UGX',

            'provider' =>
                'AIRTEL_UGANDA',

            'charge' =>
                30,

            'provider_transaction_id' =>
                'TXN-IDEMPOTENT',

            'completed_at' =>
                '2026-08-17T08:00:00+03:00',
        ];

        $service =
            app(
                MobileMoneyPaymentService::class
            );

        $first =
            $service->applyProviderResult(
                $payment,
                $payload,
                false
            );

        $second =
            $service->applyProviderResult(
                $payment->fresh(),
                $payload,
                false
            );

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertEquals(
            'successful',
            $second->status
        );

        $this->assertEquals(
            'TXN-IDEMPOTENT',
            $second->provider_transaction_id
        );

        $this->assertEquals(
            1,
            Payment::where(
                'reference',
                $payment->reference
            )->count()
        );
    }
}