<?php

namespace Tests\Feature;

use App\Models\BillingConfiguration;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\WaterWallet;
use App\Models\WaterWalletTransaction;
use App\Services\PaymentAllocationService;
use App\Services\PaymentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWalletIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Property $property;

    protected Tenant $tenant;

    protected PaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization =
            Organization::create([
                'name' => 'Integration Organization',

                'registration_number' => 'INT-ORG-001',

                'phone' => '+256700000000',

                'email' => 'integration@example.com',

                'address' => 'Kampala, Uganda',

                'status' => 'active',
            ]);

        $this->property =
            Property::create([
                'organization_id' => $this->organization->id,

                'name' => 'Integration Property',

                'property_code' => 'INT-PROP-001',

                'address' => 'Kampala',

                'city' => 'Kampala',

                'district' => 'Central',

                'latitude' => 0,

                'longitude' => 0,

                'status' => 'active',
            ]);

        $this->tenant =
            Tenant::create([
                'organization_id' => $this->organization->id,

                'first_name' => 'Integration',

                'last_name' => 'Tenant',

                'phone' => '+256700000001',

                'email' => 'tenant@example.com',

                'status' => 'active',
            ]);

        $this->provider =
            PaymentProvider::create([
                'name' => 'Integration Provider',

                'code' => 'INTEGRATION',

                'type' => 'aggregator',

                'base_url' => null,

                'is_active' => true,

                'configuration' => null,
            ]);

        BillingConfiguration::create([
            'property_id' => $this->property->id,

            'name' => 'Integration Billing',

            'water_percentage' => 75,

            'service_fee_percentage' => 5,

            'vat_percentage' => 10,

            'gateway_fee_percentage' => 4,

            'landlord_percentage' => 3,

            'saas_percentage' => 3,

            'effective_from' => now()->subDay(),

            'effective_to' => null,

            'status' => 'active',
        ]);

        $this->createLedgerAccounts();
    }

    protected function createLedgerAccounts(): void
    {
        $accounts = [
            ['PAYMENT_CLEARING', 'Payment Clearing', 'asset'],
            ['WATER_PAYABLE', 'Water Payable', 'liability'],
            ['SERVICE_REVENUE', 'Service Revenue', 'revenue'],
            ['VAT_PAYABLE', 'VAT Payable', 'liability'],
            ['GATEWAY_PAYABLE', 'Gateway Payable', 'liability'],
            ['LANDLORD_PAYABLE', 'Landlord Payable', 'liability'],
            ['SAAS_REVENUE', 'SaaS Revenue', 'revenue'],
        ];

        foreach (
            $accounts as [
                $code,
                $name,
                $type,
            ]
        ) {
            LedgerAccount::create([
                'organization_id' => $this->organization->id,

                'code' => $code,

                'name' => $name,

                'type' => $type,

                'currency' => 'UGX',

                'is_active' => true,
            ]);
        }
    }

    protected function createPayment(): Payment
    {
        return Payment::create([
            'organization_id' => $this->organization->id,

            'property_id' => $this->property->id,

            'tenant_id' => $this->tenant->id,

            'payment_provider_id' => $this->provider->id,

            'payment_provider_account_id' => null,

            'reference' => 'INT-'.
                strtoupper(
                    uniqid()
                ),

            'amount' => 100000,

            'currency' => 'UGX',

            'payer_phone' => '+256700000001',

            'status' => 'successful',

            'initiated_at' => now(),

            'completed_at' => now(),
        ]);
    }

    public function test_payment_ledger_posting_creates_wallet_transaction(): void
    {
        $payment =
            $this->createPayment();

        app(
            PaymentAllocationService::class
        )->allocate(
            $payment
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $transaction =
            WaterWalletTransaction::query()
                ->where(
                    'payment_id',
                    $payment->id
                )
                ->first();

        $this->assertNotNull(
            $transaction
        );

        $this->assertEquals(
            'credit',
            $transaction->type
        );

        $this->assertEquals(
            75000,
            (float)
            $transaction->amount
        );

        $this->assertEquals(
            0,
            (float)
            $transaction
                ->balance_before
        );

        $this->assertEquals(
            75000,
            (float)
            $transaction
                ->balance_after
        );
    }

    public function test_same_payment_cannot_create_second_wallet_transaction(): void
    {
        $payment =
            $this->createPayment();

        app(
            PaymentAllocationService::class
        )->allocate(
            $payment
        );

        $service =
            app(
                PaymentLedgerService::class
            );

        $service->postPayment(
            $payment->fresh()
        );

        $service->postPayment(
            $payment->fresh()
        );

        $this->assertEquals(
            1,
            WaterWalletTransaction::query()
                ->where(
                    'payment_id',
                    $payment->id
                )
                ->where(
                    'type',
                    'credit'
                )
                ->count()
        );

        $wallet =
            WaterWallet::where(
                'property_id',
                $this->property->id
            )->firstOrFail();

        $this->assertEquals(
            75000,
            (float)
            $wallet->balance
        );
    }

    public function test_wallet_transaction_reference_is_payment_specific(): void
    {
        $payment =
            $this->createPayment();

        app(
            PaymentAllocationService::class
        )->allocate(
            $payment
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $transaction =
            WaterWalletTransaction::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            'PAYMENT-CREDIT-'.
            $payment->id,
            $transaction->reference
        );
    }
}
