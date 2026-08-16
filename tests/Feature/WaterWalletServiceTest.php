<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Property;
use App\Models\WaterWallet;
use App\Services\WaterWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WaterWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Property $property;
    protected WaterWalletService $service;

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
            'organization_id' => $this->organization->id,
            'name' => 'Test Residential Property',
            'property_code' => 'TEST-PROP-001',
            'address' => 'Kampala, Uganda',
            'city' => 'Kampala',
            'district' => 'Central',
            'latitude' => 0,
            'longitude' => 0,
            'status' => 'active',
        ]);

        $this->service = app(WaterWalletService::class);
    }

    public function test_wallet_is_created_for_property(): void
    {
        $wallet = $this->service->getOrCreateWallet(
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
            'UGX',
            $wallet->currency
        );

        $this->assertEquals(
            0,
            (float) $wallet->balance
        );

        $this->assertEquals(
            'active',
            $wallet->status
        );
    }

    public function test_get_or_create_wallet_is_idempotent(): void
    {
        $first = $this->service->getOrCreateWallet(
            $this->property
        );

        $second = $this->service->getOrCreateWallet(
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

    public function test_wallet_can_be_credited(): void
    {
        $wallet = $this->service->credit(
            $this->property,
            75000
        );

        $this->assertEquals(
            75000,
            (float) $wallet->balance
        );

        $this->assertDatabaseHas(
            'water_wallets',
            [
                'property_id' => $this->property->id,
                'balance' => 75000,
                'status' => 'active',
            ]
        );
    }

    public function test_multiple_credits_accumulate(): void
    {
        $this->service->credit(
            $this->property,
            50000
        );

        $wallet = $this->service->credit(
            $this->property,
            25000
        );

        $this->assertEquals(
            75000,
            (float) $wallet->balance
        );
    }

    public function test_wallet_can_be_debited(): void
    {
        $this->service->credit(
            $this->property,
            100000
        );

        $wallet = $this->service->debit(
            $this->property,
            40000
        );

        $this->assertEquals(
            60000,
            (float) $wallet->balance
        );
    }

    public function test_multiple_debits_reduce_balance_correctly(): void
    {
        $this->service->credit(
            $this->property,
            100000
        );

        $this->service->debit(
            $this->property,
            25000
        );

        $wallet = $this->service->debit(
            $this->property,
            15000
        );

        $this->assertEquals(
            60000,
            (float) $wallet->balance
        );
    }

    public function test_credit_amount_must_be_greater_than_zero(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Wallet credit amount must be greater than zero.'
        );

        $this->service->credit(
            $this->property,
            0
        );
    }

    public function test_negative_credit_is_rejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Wallet credit amount must be greater than zero.'
        );

        $this->service->credit(
            $this->property,
            -1000
        );
    }

    public function test_debit_amount_must_be_greater_than_zero(): void
    {
        $this->service->credit(
            $this->property,
            10000
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Wallet debit amount must be greater than zero.'
        );

        $this->service->debit(
            $this->property,
            0
        );
    }

    public function test_wallet_cannot_be_overdrawn(): void
    {
        $this->service->credit(
            $this->property,
            10000
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Insufficient water wallet balance.'
        );

        $this->service->debit(
            $this->property,
            15000
        );
    }

    public function test_debit_fails_when_wallet_does_not_exist(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Water wallet does not exist.'
        );

        $this->service->debit(
            $this->property,
            1000
        );
    }

    public function test_frozen_wallet_cannot_be_credited(): void
    {
        WaterWallet::create([
            'property_id' => $this->property->id,
            'currency' => 'UGX',
            'balance' => 10000,
            'status' => 'frozen',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Water wallet is not active.'
        );

        $this->service->credit(
            $this->property,
            5000
        );
    }

    public function test_frozen_wallet_cannot_be_debited(): void
    {
        WaterWallet::create([
            'property_id' => $this->property->id,
            'currency' => 'UGX',
            'balance' => 10000,
            'status' => 'frozen',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Water wallet is not active.'
        );

        $this->service->debit(
            $this->property,
            5000
        );
    }

    public function test_closed_wallet_cannot_be_credited(): void
    {
        WaterWallet::create([
            'property_id' => $this->property->id,
            'currency' => 'UGX',
            'balance' => 10000,
            'status' => 'closed',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Water wallet is not active.'
        );

        $this->service->credit(
            $this->property,
            5000
        );
    }

    public function test_wallet_balance_never_becomes_negative(): void
    {
        $this->service->credit(
            $this->property,
            10000
        );

        try {
            $this->service->debit(
                $this->property,
                15000
            );
        } catch (RuntimeException $exception) {
            // Expected.
        }

        $wallet = WaterWallet::where(
            'property_id',
            $this->property->id
        )->firstOrFail();

        $this->assertGreaterThanOrEqual(
            0,
            (float) $wallet->balance
        );

        $this->assertEquals(
            10000,
            (float) $wallet->balance
        );
    }
}