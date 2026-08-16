<?php

namespace Tests\Feature;

use App\Models\BillingConfiguration;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\WaterWallet;
use App\Services\PaymentAllocationService;
use App\Services\PaymentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentLedgerServiceTest extends TestCase
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
            'name' => 'Test Water Organization',
            'registration_number' => 'TEST-ORG-001',
            'phone' => '+256700000000',
            'email' => 'org@example.com',
            'address' => 'Kampala, Uganda',
            'status' => 'active',
        ]);

        $this->property = Property::create([
            'organization_id' =>
                $this->organization->id,

            'name' =>
                'Test Residential Property',

            'property_code' =>
                'TEST-PROP-001',

            'address' =>
                'Kampala, Uganda',

            'city' =>
                'Kampala',

            'district' =>
                'Central',

            'latitude' => 0,
            'longitude' => 0,

            'status' => 'active',
        ]);

        $this->tenant = Tenant::create([
            'organization_id' =>
                $this->organization->id,

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

        $this->createBillingConfiguration();

        $this->createLedgerAccounts();
    }

    protected function createBillingConfiguration(): void
    {
        BillingConfiguration::create([
            'property_id' =>
                $this->property->id,

            'name' =>
                'Test Billing Configuration',

            'water_percentage' => 75,
            'service_fee_percentage' => 5,
            'vat_percentage' => 10,
            'gateway_fee_percentage' => 4,
            'landlord_percentage' => 3,
            'saas_percentage' => 3,

            'effective_from' =>
                now()->subDay(),

            'effective_to' => null,

            'status' => 'active',
        ]);
    }

    protected function createLedgerAccounts(): void
    {
        $accounts = [
            [
                'code' => 'PAYMENT_CLEARING',
                'name' => 'Payment Clearing',
                'type' => 'asset',
            ],
            [
                'code' => 'WATER_PAYABLE',
                'name' => 'Water Payable',
                'type' => 'liability',
            ],
            [
                'code' => 'SERVICE_REVENUE',
                'name' => 'Service Revenue',
                'type' => 'revenue',
            ],
            [
                'code' => 'VAT_PAYABLE',
                'name' => 'VAT Payable',
                'type' => 'liability',
            ],
            [
                'code' => 'GATEWAY_PAYABLE',
                'name' => 'Gateway Payable',
                'type' => 'liability',
            ],
            [
                'code' => 'LANDLORD_PAYABLE',
                'name' => 'Landlord Payable',
                'type' => 'liability',
            ],
            [
                'code' => 'SAAS_REVENUE',
                'name' => 'SaaS Revenue',
                'type' => 'revenue',
            ],
        ];

        foreach ($accounts as $account) {
            LedgerAccount::create([
                'organization_id' =>
                    $this->organization->id,

                'code' =>
                    $account['code'],

                'name' =>
                    $account['name'],

                'type' =>
                    $account['type'],

                'currency' => 'UGX',

                'is_active' => true,
            ]);
        }
    }

    protected function createSuccessfulPayment(
        float $amount = 100000
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
                'TEST-PAY-' .
                strtoupper(uniqid()),

            'amount' => $amount,

            'currency' => 'UGX',

            'payer_phone' =>
                '+256700000001',

            'status' =>
                'successful',

            'initiated_at' =>
                now(),

            'completed_at' =>
                now(),
        ]);
    }

    protected function allocate(
        Payment $payment
    ): void {
        app(
            PaymentAllocationService::class
        )->allocate($payment);
    }

    public function test_successful_payment_creates_balanced_ledger_transaction(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $transaction->load('entries');

        $totalDebit = (float)
            $transaction
                ->entries
                ->sum('debit');

        $totalCredit = (float)
            $transaction
                ->entries
                ->sum('credit');

        $this->assertEquals(
            100000,
            $totalDebit
        );

        $this->assertEquals(
            100000,
            $totalCredit
        );

        $this->assertEquals(
            $totalDebit,
            $totalCredit
        );
    }

    public function test_ledger_transaction_has_one_debit_and_six_credit_entries(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $entries =
            $transaction
                ->entries()
                ->get();

        $this->assertCount(
            7,
            $entries
        );

        $this->assertEquals(
            1,
            $entries
                ->filter(
                    fn ($entry) =>
                        (float) $entry->debit > 0
                )
                ->count()
        );

        $this->assertEquals(
            6,
            $entries
                ->filter(
                    fn ($entry) =>
                        (float) $entry->credit > 0
                )
                ->count()
        );
    }

    public function test_payment_clearing_account_is_debited_with_full_payment_amount(): void
    {
        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $entry = $transaction
            ->entries()
            ->whereHas(
                'account',
                fn ($query) =>
                    $query->where(
                        'code',
                        'PAYMENT_CLEARING'
                    )
            )
            ->first();

        $this->assertNotNull($entry);

        $this->assertEquals(
            100000,
            (float) $entry->debit
        );

        $this->assertEquals(
            0,
            (float) $entry->credit
        );
    }

    public function test_water_payable_receives_water_allocation(): void
    {
        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $entry = $transaction
            ->entries()
            ->whereHas(
                'account',
                fn ($query) =>
                    $query->where(
                        'code',
                        'WATER_PAYABLE'
                    )
            )
            ->first();

        $this->assertNotNull($entry);

        $this->assertEquals(
            75000,
            (float) $entry->credit
        );
    }

    public function test_allocation_accounts_receive_correct_amounts(): void
    {
        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $expected = [
            'WATER_PAYABLE' => 75000,
            'SERVICE_REVENUE' => 5000,
            'VAT_PAYABLE' => 10000,
            'GATEWAY_PAYABLE' => 4000,
            'LANDLORD_PAYABLE' => 3000,
            'SAAS_REVENUE' => 3000,
        ];

        foreach ($expected as $code => $amount) {
            $entry = $transaction
                ->entries()
                ->whereHas(
                    'account',
                    fn ($query) =>
                        $query->where(
                            'code',
                            $code
                        )
                )
                ->first();

            $this->assertNotNull(
                $entry,
                "Missing ledger entry for {$code}"
            );

            $this->assertEquals(
                $amount,
                (float) $entry->credit
            );
        }
    }

    public function test_payment_is_linked_to_ledger_transaction(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        $transaction = app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $payment->refresh();

        $this->assertNotNull(
            $payment->ledger_transaction_id
        );

        $this->assertEquals(
            $transaction->id,
            $payment->ledger_transaction_id
        );
    }

    public function test_water_wallet_receives_only_water_allocation(): void
    {
        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $this->allocate($payment);

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );

        $wallet = WaterWallet::where(
            'property_id',
            $this->property->id
        )->first();

        $this->assertNotNull($wallet);

        $this->assertEquals(
            75000,
            (float) $wallet->balance
        );
    }

    public function test_posting_same_payment_twice_does_not_duplicate_ledger(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        $service = app(
            PaymentLedgerService::class
        );

        $first = $service->postPayment(
            $payment->fresh()
        );

        $second = $service->postPayment(
            $payment->fresh()
        );

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertEquals(
            1,
            LedgerTransaction::where(
                'organization_id',
                $this->organization->id
            )
                ->where(
                    'transaction_type',
                    'payment'
                )
                ->count()
        );
    }

    public function test_posting_same_payment_twice_does_not_credit_wallet_twice(): void
    {
        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $this->allocate($payment);

        $service = app(
            PaymentLedgerService::class
        );

        $service->postPayment(
            $payment->fresh()
        );

        $firstBalance = (float)
            WaterWallet::where(
                'property_id',
                $this->property->id
            )
                ->firstOrFail()
                ->balance;

        $service->postPayment(
            $payment->fresh()
        );

        $secondBalance = (float)
            WaterWallet::where(
                'property_id',
                $this->property->id
            )
                ->firstOrFail()
                ->balance;

        $this->assertEquals(
            75000,
            $firstBalance
        );

        $this->assertEquals(
            75000,
            $secondBalance
        );
    }

    public function test_posting_same_payment_twice_does_not_duplicate_entries(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        $service = app(
            PaymentLedgerService::class
        );

        $transaction =
            $service->postPayment(
                $payment->fresh()
            );

        $service->postPayment(
            $payment->fresh()
        );

        $this->assertEquals(
            7,
            $transaction
                ->entries()
                ->count()
        );
    }

    public function test_unsuccessful_payment_cannot_be_posted(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $payment->update([
            'status' => 'failed',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Only successful payments can be posted to the ledger.'
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );
    }

    public function test_payment_without_allocations_cannot_be_posted(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Payment has no allocations.'
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment
        );
    }

    public function test_missing_required_ledger_account_causes_posting_failure(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        LedgerAccount::where(
            'organization_id',
            $this->organization->id
        )
            ->where(
                'code',
                'WATER_PAYABLE'
            )
            ->delete();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ledger account [WATER_PAYABLE] does not exist'
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );
    }

    public function test_inactive_ledger_account_cannot_be_used(): void
    {
        $payment =
            $this->createSuccessfulPayment();

        $this->allocate($payment);

        LedgerAccount::where(
            'organization_id',
            $this->organization->id
        )
            ->where(
                'code',
                'VAT_PAYABLE'
            )
            ->update([
                'is_active' => false,
            ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ledger account [VAT_PAYABLE] does not exist'
        );

        app(
            PaymentLedgerService::class
        )->postPayment(
            $payment->fresh()
        );
    }
}