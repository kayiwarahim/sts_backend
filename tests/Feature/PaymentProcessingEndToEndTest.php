<?php

namespace Tests\Feature;

use App\Models\BillingConfiguration;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterToken;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\StsTransaction;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterTariff;
use App\Models\WaterVending;
use App\Models\WaterWallet;
use App\Models\WaterWalletTransaction;
use App\Services\PaymentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymentProcessingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Property $property;
    protected Unit $unit;
    protected Tenant $tenant;
    protected Tenancy $tenancy;
    protected Meter $meter;
    protected MeterAssignment $meterAssignment;

    protected WaterTariff $tariff;
    protected BillingConfiguration $billingConfiguration;

    protected PaymentProvider $provider;

    protected User $landlordUser;
    protected User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | STS configuration
        |--------------------------------------------------------------------------
        |
        | The test NEVER contacts the real STS server.
        |--------------------------------------------------------------------------
        */

        config([
            'services.sts.base_url' =>
                'http://sts-test.local',

            'services.sts.user_id' =>
                'TEST_USER',

            'services.sts.password' =>
                'TEST_PASSWORD',

            'services.sts.meter_type' =>
                2,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Organization
        |--------------------------------------------------------------------------
        */

        $this->organization =
            Organization::create([
                'name' =>
                    'E2E Water Organization',

                'registration_number' =>
                    'E2E-ORG-001',

                'phone' =>
                    '+256700000000',

                'email' =>
                    'e2e-org@example.com',

                'address' =>
                    'Kampala, Uganda',

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Landlord application user
        |--------------------------------------------------------------------------
        */

        $this->landlordUser =
            User::create([
                'organization_id' =>
                    $this->organization->id,

                'name' =>
                    'E2E Landlord',

                'email' =>
                    'e2e-landlord@example.com',

                'password' =>
                    Hash::make('password'),

                'email_verified_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Property
        |--------------------------------------------------------------------------
        */

        $this->property =
            Property::create([
                'organization_id' =>
                    $this->organization->id,

                'name' =>
                    'E2E Residential Property',

                'property_code' =>
                    'E2E-PROP-001',

                'address' =>
                    'Kampala, Uganda',

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

        /*
        |--------------------------------------------------------------------------
        | Unit
        |--------------------------------------------------------------------------
        */

        $this->unit =
            Unit::create([
                'property_id' =>
                    $this->property->id,

                'unit_number' =>
                    '101',

                'floor' =>
                    '1',

                'description' =>
                    'E2E test unit',

                'status' =>
                    'occupied',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Tenant
        |--------------------------------------------------------------------------
        */

        $this->tenant =
            Tenant::create([
                'organization_id' =>
                    $this->organization->id,

                'first_name' =>
                    'John',

                'last_name' =>
                    'Tenant',

                'phone' =>
                    '+256700000001',

                'email' =>
                    'e2e-tenant@example.com',

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Tenant application user
        |--------------------------------------------------------------------------
        |
        | NotificationService currently resolves tenant User by matching email.
        |--------------------------------------------------------------------------
        */

        $this->tenantUser =
            User::create([
                'organization_id' =>
                    null,

                'name' =>
                    'John Tenant',

                'email' =>
                    'e2e-tenant@example.com',

                'password' =>
                    Hash::make('password'),

                'email_verified_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Active tenancy
        |--------------------------------------------------------------------------
        */

        $this->tenancy =
            Tenancy::create([
                'unit_id' =>
                    $this->unit->id,

                'tenant_id' =>
                    $this->tenant->id,

                'start_date' =>
                    now()->subMonth(),

                'end_date' =>
                    null,

                'status' =>
                    'active',

                'notes' =>
                    'E2E active tenancy',
            ]);

        /*
        |--------------------------------------------------------------------------
        | STS Meter
        |--------------------------------------------------------------------------
        */

        $this->meter =
            Meter::create([
                'organization_id' =>
                    $this->organization->id,

                'meter_number' =>
                    '0152110004800',

                'serial_number' =>
                    '0152110004800',

                'manufacturer' =>
                    'STS Test Provider',

                'model' =>
                    'Prepaid Water Meter',

                'meter_type' =>
                    '2',

                'key_revision' =>
                    null,

                'supply_group_code' =>
                    null,

                'status' =>
                    'active',

                'installed_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Meter assignment
        |--------------------------------------------------------------------------
        */

        $this->meterAssignment =
            MeterAssignment::create([
                'meter_id' =>
                    $this->meter->id,

                'unit_id' =>
                    $this->unit->id,

                'assigned_at' =>
                    now(),

                'unassigned_at' =>
                    null,

                'status' =>
                    'active',

                'notes' =>
                    'E2E active meter assignment',
            ]);

        /*
        |--------------------------------------------------------------------------
        | OUR water tariff
        |--------------------------------------------------------------------------
        |
        | UGX 6,500 per m³.
        |--------------------------------------------------------------------------
        */

        $this->tariff =
            WaterTariff::create([
                'property_id' =>
                    $this->property->id,

                'name' =>
                    'E2E Water Tariff',

                'price_per_m3' =>
                    6500,

                'currency' =>
                    'UGX',

                'effective_from' =>
                    now()->subMonth(),

                'effective_to' =>
                    null,

                'status' =>
                    'active',

                'notes' =>
                    'Local system tariff',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Billing split
        |--------------------------------------------------------------------------
        |
        | Payment = UGX 100,000
        | Water   = 75% = UGX 75,000
        |--------------------------------------------------------------------------
        */

        $this->billingConfiguration =
            BillingConfiguration::create([
                'property_id' =>
                    $this->property->id,

                'water_tariff_id' =>
                    $this->tariff->id,

                'name' =>
                    'E2E Billing Configuration',

                'water_percentage' =>
                    75,

                'service_fee_percentage' =>
                    5,

                'vat_percentage' =>
                    10,

                'gateway_fee_percentage' =>
                    4,

                'landlord_percentage' =>
                    3,

                'saas_percentage' =>
                    3,

                'effective_from' =>
                    now()->subMonth(),

                'effective_to' =>
                    null,

                'status' =>
                    'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Payment provider
        |--------------------------------------------------------------------------
        */

        $this->provider =
            PaymentProvider::create([
                'name' =>
                    'Test Payment Provider',

                'code' =>
                    'TEST_E2E',

                'type' =>
                    'aggregator',

                'base_url' =>
                    null,

                'is_active' =>
                    true,

                'configuration' =>
                    null,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ledger accounts
        |--------------------------------------------------------------------------
        */

        $this->createLedgerAccounts();
    }

    protected function createLedgerAccounts(): void
    {
        $accounts = [
            [
                'PAYMENT_CLEARING',
                'Payment Clearing',
                'asset',
            ],

            [
                'WATER_PAYABLE',
                'Water Payable',
                'liability',
            ],

            [
                'SERVICE_REVENUE',
                'Service Revenue',
                'revenue',
            ],

            [
                'VAT_PAYABLE',
                'VAT Payable',
                'liability',
            ],

            [
                'GATEWAY_PAYABLE',
                'Gateway Payable',
                'liability',
            ],

            [
                'LANDLORD_PAYABLE',
                'Landlord Payable',
                'liability',
            ],

            [
                'SAAS_REVENUE',
                'SaaS Revenue',
                'revenue',
            ],
        ];

        foreach (
            $accounts as [
                $code,
                $name,
                $type,
            ]
        ) {
            LedgerAccount::create([
                'organization_id' =>
                    $this->organization->id,

                'code' =>
                    $code,

                'name' =>
                    $name,

                'type' =>
                    $type,

                'currency' =>
                    'UGX',

                'is_active' =>
                    true,
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
                'E2E-PAY-' .
                strtoupper(
                    uniqid()
                ),

            'amount' =>
                $amount,

            'currency' =>
                'UGX',

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

    /**
     * Fake a successful STS provider response.
     */
    protected function fakeSuccessfulSts(): void
    {
        Http::fake([
            'http://sts-test.local/api/Power/GetVendingToken*' =>
                Http::response([
                    'Code' =>
                        200,

                    'Message' =>
                        'get token successfully',

                    'Data' => [
                        'Token' =>
                            '4521 9558 2647 3699 0692',

                        'MeterCode' =>
                            '0152110004800',

                        /*
                        |--------------------------------------------------------------------------
                        | Intentionally DIFFERENT provider tariff.
                        |
                        | This must NOT control our billing calculation.
                        |--------------------------------------------------------------------------
                        */

                        'Tarrif' =>
                            '999999.000 per unit',

                        'ServiceCharge' =>
                            0,

                        'VendingAmount' =>
                            999999,

                        /*
                        |--------------------------------------------------------------------------
                        | Provider confirms quantity calculated by OUR system.
                        |--------------------------------------------------------------------------
                        */

                        'VendingQuantity' =>
                            11.538,
                    ],
                ], 200),
        ]);
    }

    /**
     * Fake an STS provider application failure.
     */
    protected function fakeFailedSts(): void
    {
        Http::fake([
            'http://sts-test.local/api/Power/GetVendingToken*' =>
                Http::response([
                    'Code' =>
                        500,

                    'Message' =>
                        'Unable to generate token',

                    'Data' =>
                        null,
                ], 200),
        ]);
    }

    public function test_successful_payment_runs_complete_internal_workflow(): void
    {
        $this->fakeSuccessfulSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $result =
            app(
                PaymentProcessingService::class
            )->processSuccessfulPayment(
                $payment
            );

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            'successful',
            $result->status
        );

        $this->assertNotNull(
            $result->ledger_transaction_id
        );

        /*
        |--------------------------------------------------------------------------
        | Six allocations
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            6,
            $payment
                ->fresh()
                ->allocations()
                ->count()
        );

        $this->assertEquals(
            100000,
            (float)
            $payment
                ->fresh()
                ->allocations()
                ->sum('amount')
        );

        /*
        |--------------------------------------------------------------------------
        | Ledger
        |--------------------------------------------------------------------------
        */

        $ledger =
            LedgerTransaction::findOrFail(
                $result
                    ->ledger_transaction_id
            );

        $entries =
            $ledger
                ->entries()
                ->get();

        $this->assertCount(
            7,
            $entries
        );

        $this->assertEquals(
            100000,
            (float)
            $entries->sum(
                'debit'
            )
        );

        $this->assertEquals(
            100000,
            (float)
            $entries->sum(
                'credit'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Wallet
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Wallet transaction
        |--------------------------------------------------------------------------
        */

        $walletTransaction =
            WaterWalletTransaction::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            'credit',
            $walletTransaction->type
        );

        $this->assertEquals(
            75000,
            (float)
            $walletTransaction->amount
        );

        $this->assertEquals(
            0,
            (float)
            $walletTransaction
                ->balance_before
        );

        $this->assertEquals(
            75000,
            (float)
            $walletTransaction
                ->balance_after
        );

        /*
        |--------------------------------------------------------------------------
        | STS transaction
        |--------------------------------------------------------------------------
        */

        $sts =
            StsTransaction::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            'token_generation',
            $sts->transaction_type
        );

        $this->assertEquals(
            'successful',
            $sts->status
        );

        $this->assertEquals(
            75000,
            (float)
            $sts->amount
        );

        $this->assertEquals(
            11.538,
            (float)
            $sts->volume_m3
        );

        $this->assertEquals(
            '4521 9558 2647 3699 0692',
            $sts->token
        );

        /*
        |--------------------------------------------------------------------------
        | Water vending
        |--------------------------------------------------------------------------
        */

        $vending =
            WaterVending::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            75000,
            (float)
            $vending->amount
        );

        $this->assertEquals(
            6500,
            (float)
            $vending->price_per_m3
        );

        $this->assertEquals(
            11.538,
            (float)
            $vending->volume_m3
        );

        $this->assertEquals(
            'successful',
            $vending->status
        );

        /*
        |--------------------------------------------------------------------------
        | Meter token
        |--------------------------------------------------------------------------
        */

        $token =
            MeterToken::where(
                'water_vending_id',
                $vending->id
            )->firstOrFail();

        $this->assertEquals(
            '4521 9558 2647 3699 0692',
            $token->token
        );

        $this->assertEquals(
            'credit',
            $token->token_type
        );

        $this->assertEquals(
            'generated',
            $token->status
        );

        $this->assertEquals(
            11.538,
            (float)
            $token->volume_m3
        );
    }

    public function test_local_tariff_converts_water_money_to_quantity_sent_to_sts(): void
    {
        $this->fakeSuccessfulSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        app(
            PaymentProcessingService::class
        )->processSuccessfulPayment(
            $payment
        );

        /*
        |--------------------------------------------------------------------------
        | UGX 100,000 × 75%
        | = UGX 75,000 water allocation
        |
        | UGX 75,000 / UGX 6,500 per m³
        | = 11.538 m³
        |--------------------------------------------------------------------------
        */

        Http::assertSent(
            function (
                Request $request
            ) {
                if (
                    !str_contains(
                        $request->url(),
                        '/api/Power/GetVendingToken'
                    )
                ) {
                    return false;
                }

                $data =
                    $request->data();

                return
                    $data[
                        'MeterCode'
                    ] ===
                    '0152110004800'

                    &&
                    (int)
                    $data[
                        'MeterType'
                    ] ===
                    2

                    &&
                    (float)
                    $data[
                        'AmountOrQuantity'
                    ] ===
                    11.538

                    &&
                    (int)
                    $data[
                        'VendingType'
                    ] ===
                    1;
            }
        );
    }

    public function test_provider_tariff_does_not_override_local_tariff(): void
    {
        $this->fakeSuccessfulSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        app(
            PaymentProcessingService::class
        )->processSuccessfulPayment(
            $payment
        );

        /*
        |--------------------------------------------------------------------------
        | Fake provider returned:
        |
        | Tarrif = 999999
        |
        | But OUR configured tariff is UGX 6,500/m³.
        |--------------------------------------------------------------------------
        */

        $vending =
            WaterVending::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            6500,
            (float)
            $vending->price_per_m3
        );

        $this->assertNotEquals(
            999999,
            (float)
            $vending->price_per_m3
        );

        $this->assertEquals(
            11.538,
            (float)
            $vending->volume_m3
        );
    }

    public function test_processing_same_payment_twice_is_fully_idempotent(): void
    {
        $this->fakeSuccessfulSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $service =
            app(
                PaymentProcessingService::class
            );

        /*
        |--------------------------------------------------------------------------
        | First processing
        |--------------------------------------------------------------------------
        */

        $service
            ->processSuccessfulPayment(
                $payment
            );

        $firstLedgerId =
            $payment
                ->fresh()
                ->ledger_transaction_id;

        $firstWalletBalance =
            (float)
            WaterWallet::where(
                'property_id',
                $this->property->id
            )
                ->firstOrFail()
                ->balance;

        /*
        |--------------------------------------------------------------------------
        | Process SAME payment again
        |--------------------------------------------------------------------------
        */

        $service
            ->processSuccessfulPayment(
                $payment->fresh()
            );

        /*
        |--------------------------------------------------------------------------
        | Allocations
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            6,
            $payment
                ->fresh()
                ->allocations()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | One ledger transaction
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $firstLedgerId,
            $payment
                ->fresh()
                ->ledger_transaction_id
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

        /*
        |--------------------------------------------------------------------------
        | Seven entries only
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            7,
            LedgerEntry::where(
                'ledger_transaction_id',
                $firstLedgerId
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Wallet not credited twice
        |--------------------------------------------------------------------------
        */

        $secondWalletBalance =
            (float)
            WaterWallet::where(
                'property_id',
                $this->property->id
            )
                ->firstOrFail()
                ->balance;

        $this->assertEquals(
            75000,
            $firstWalletBalance
        );

        $this->assertEquals(
            75000,
            $secondWalletBalance
        );

        $this->assertEquals(
            1,
            WaterWalletTransaction::where(
                'payment_id',
                $payment->id
            )
                ->where(
                    'type',
                    'credit'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | STS not duplicated
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            StsTransaction::where(
                'payment_id',
                $payment->id
            )
                ->where(
                    'status',
                    'successful'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Water vending not duplicated
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            WaterVending::where(
                'payment_id',
                $payment->id
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Token not duplicated
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            MeterToken::where(
                'meter_id',
                $this->meter->id
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | STS provider called exactly once
        |--------------------------------------------------------------------------
        */

        Http::assertSentCount(
            1
        );
    }

    public function test_notifications_are_not_duplicated_when_payment_is_processed_twice(): void
    {
        $this->fakeSuccessfulSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        $service =
            app(
                PaymentProcessingService::class
            );

        $service
            ->processSuccessfulPayment(
                $payment
            );

        $service
            ->processSuccessfulPayment(
                $payment->fresh()
            );

        /*
        |--------------------------------------------------------------------------
        | Tenant payment notification
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            Notification::where(
                'user_id',
                $this->tenantUser->id
            )
                ->where(
                    'type',
                    'payment_successful'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Tenant token notification
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            Notification::where(
                'user_id',
                $this->tenantUser->id
            )
                ->where(
                    'type',
                    'sts_token_generated'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Landlord payment notification
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            Notification::where(
                'user_id',
                $this->landlordUser->id
            )
                ->where(
                    'type',
                    'payment_received'
                )
                ->count()
        );
    }

    public function test_sts_failure_does_not_rollback_financial_processing(): void
    {
        $this->fakeFailedSts();

        $payment =
            $this->createSuccessfulPayment(
                100000
            );

        try {
            app(
                PaymentProcessingService::class
            )->processSuccessfulPayment(
                $payment
            );

            $this->fail(
                'Expected STS vending failure was not thrown.'
            );

        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'STS provider error',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment remains successful
        |--------------------------------------------------------------------------
        */

        $payment->refresh();

        $this->assertEquals(
            'successful',
            $payment->status
        );

        /*
        |--------------------------------------------------------------------------
        | Financial transaction remains committed
        |--------------------------------------------------------------------------
        */

        $this->assertNotNull(
            $payment
                ->ledger_transaction_id
        );

        $this->assertEquals(
            6,
            $payment
                ->allocations()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Wallet remains credited
        |--------------------------------------------------------------------------
        */

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

        $this->assertEquals(
            1,
            WaterWalletTransaction::where(
                'payment_id',
                $payment->id
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Failed STS attempt recorded
        |--------------------------------------------------------------------------
        */

        $sts =
            StsTransaction::where(
                'payment_id',
                $payment->id
            )->firstOrFail();

        $this->assertEquals(
            'failed',
            $sts->status
        );

        $this->assertNotNull(
            $sts->error_message
        );

        /*
        |--------------------------------------------------------------------------
        | No successful vending/token created
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            0,
            WaterVending::where(
                'payment_id',
                $payment->id
            )->count()
        );

        $this->assertEquals(
            0,
            MeterToken::where(
                'sts_transaction_id',
                $sts->id
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Tenant gets delay notification
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            Notification::where(
                'user_id',
                $this->tenantUser->id
            )
                ->where(
                    'type',
                    'sts_vending_failed'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Landlord gets operational failure notification
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            Notification::where(
                'user_id',
                $this->landlordUser->id
            )
                ->where(
                    'type',
                    'sts_vending_failed'
                )
                ->count()
        );
    }

    public function test_unsuccessful_payment_cannot_enter_processing_workflow(): void
    {
        Http::preventStrayRequests();

        $payment =
            $this->createSuccessfulPayment();

        $payment->update([
            'status' =>
                'failed',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Only successful payments can be processed.'
        );

        app(
            PaymentProcessingService::class
        )->processSuccessfulPayment(
            $payment->fresh()
        );
    }
}