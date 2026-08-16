<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\WaterWallet;
use App\Models\WaterWalletTransaction;
use App\Services\WaterWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WaterWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Property $property;
    protected Tenant $tenant;
    protected PaymentProvider $provider;
    protected WaterWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization =
            Organization::create([
                'name' =>
                    'Test Water Organization',

                'registration_number' =>
                    'TEST-ORG-001',

                'phone' =>
                    '+256700000000',

                'email' =>
                    'org@example.com',

                'address' =>
                    'Kampala, Uganda',

                'status' =>
                    'active',
            ]);

        $this->property =
            Property::create([
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
                    'Test',

                'last_name' =>
                    'Tenant',

                'phone' =>
                    '+256700000001',

                'email' =>
                    'tenant@example.com',

                'status' =>
                    'active',
            ]);

        $this->provider =
            PaymentProvider::create([
                'name' =>
                    'Test Provider',

                'code' =>
                    'TEST_PROVIDER',

                'type' =>
                    'aggregator',

                'base_url' =>
                    null,

                'is_active' =>
                    true,

                'configuration' =>
                    null,
            ]);

        $this->service =
            app(
                WaterWalletService::class
            );
    }

    protected function createPayment(
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

    public function test_wallet_is_created_for_property(): void
    {
        $wallet =
            $this->service
                ->getOrCreateWallet(
                    $this->property
                );

        $this->assertInstanceOf(
            WaterWallet::class,
            $wallet
        );

        $this->assertEquals(
            $this->property->id,
            $wallet->property_id
        );

        $this->assertEquals(
            0,
            (float)
            $wallet->balance
        );
    }

    public function test_get_or_create_wallet_is_idempotent(): void
    {
        $first =
            $this->service
                ->getOrCreateWallet(
                    $this->property
                );

        $second =
            $this->service
                ->getOrCreateWallet(
                    $this->property
                );

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertEquals(
            1,
            WaterWallet::where(
                'property_id',
                $this->property->id
            )->count()
        );
    }

    public function test_credit_updates_wallet_and_creates_transaction(): void
    {
        $wallet =
            $this->service
                ->credit(
                    $this->property,
                    75000
                );

        $this->assertEquals(
            75000,
            (float)
            $wallet->balance
        );

        $transaction =
            WaterWalletTransaction::first();

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

    public function test_debit_updates_wallet_and_creates_transaction(): void
    {
        $this->service
            ->credit(
                $this->property,
                100000
            );

        $wallet =
            $this->service
                ->debit(
                    $this->property,
                    40000
                );

        $this->assertEquals(
            60000,
            (float)
            $wallet->balance
        );

        $transaction =
            WaterWalletTransaction::query()
                ->where(
                    'type',
                    'debit'
                )
                ->first();

        $this->assertNotNull(
            $transaction
        );

        $this->assertEquals(
            40000,
            (float)
            $transaction->amount
        );

        $this->assertEquals(
            100000,
            (float)
            $transaction
                ->balance_before
        );

        $this->assertEquals(
            60000,
            (float)
            $transaction
                ->balance_after
        );
    }

    public function test_payment_credit_is_linked_to_payment(): void
    {
        $payment =
            $this->createPayment();

        $this->service
            ->credit(
                $this->property,
                75000,
                $payment
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
    }

    public function test_payment_credit_is_idempotent(): void
    {
        $payment =
            $this->createPayment();

        $this->service
            ->credit(
                $this->property,
                75000,
                $payment
            );

        $firstBalance =
            (float)
            WaterWallet::where(
                'property_id',
                $this->property->id
            )
                ->firstOrFail()
                ->balance;

        $this->service
            ->credit(
                $this->property,
                75000,
                $payment
            );

        $secondBalance =
            (float)
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
    }

    public function test_different_payments_can_credit_same_wallet(): void
    {
        $paymentOne =
            $this->createPayment();

        $paymentTwo =
            $this->createPayment();

        $this->service
            ->credit(
                $this->property,
                75000,
                $paymentOne
            );

        $this->service
            ->credit(
                $this->property,
                37500,
                $paymentTwo
            );

        $wallet =
            WaterWallet::where(
                'property_id',
                $this->property->id
            )->firstOrFail();

        $this->assertEquals(
            112500,
            (float)
            $wallet->balance
        );

        $this->assertEquals(
            2,
            WaterWalletTransaction::where(
                'type',
                'credit'
            )->count()
        );
    }

    public function test_manual_credits_are_not_idempotent(): void
    {
        $this->service
            ->credit(
                $this->property,
                50000
            );

        $this->service
            ->credit(
                $this->property,
                25000
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

        $this->assertEquals(
            2,
            WaterWalletTransaction::where(
                'type',
                'credit'
            )->count()
        );
    }

    public function test_wallet_cannot_be_overdrawn(): void
    {
        $this->service
            ->credit(
                $this->property,
                10000
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Insufficient water wallet balance.'
        );

        $this->service
            ->debit(
                $this->property,
                15000
            );
    }

    public function test_failed_debit_does_not_create_transaction(): void
    {
        $this->service
            ->credit(
                $this->property,
                10000
            );

        $before =
            WaterWalletTransaction::count();

        try {
            $this->service
                ->debit(
                    $this->property,
                    15000
                );
        } catch (RuntimeException) {
            //
        }

        $after =
            WaterWalletTransaction::count();

        $this->assertEquals(
            $before,
            $after
        );
    }

    public function test_credit_amount_must_be_positive(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->service
            ->credit(
                $this->property,
                0
            );
    }

    public function test_debit_amount_must_be_positive(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->service
            ->debit(
                $this->property,
                0
            );
    }

    public function test_frozen_wallet_cannot_be_credited(): void
    {
        WaterWallet::create([
            'property_id' =>
                $this->property->id,

            'currency' =>
                'UGX',

            'balance' =>
                10000,

            'status' =>
                'frozen',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->service
            ->credit(
                $this->property,
                5000
            );
    }

    public function test_frozen_wallet_cannot_be_debited(): void
    {
        WaterWallet::create([
            'property_id' =>
                $this->property->id,

            'currency' =>
                'UGX',

            'balance' =>
                10000,

            'status' =>
                'frozen',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->service
            ->debit(
                $this->property,
                5000
            );
    }
}