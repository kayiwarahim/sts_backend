<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\MeterToken;
use App\Models\Payment;
use App\Models\StsTransaction;
use App\Models\WaterTariff;
use App\Models\WaterVending;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class StsService
{
    protected string $baseUrl;

    protected string $userId;

    protected string $password;

    protected int $meterType;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sts.base_url'), '/');
        $this->userId = config('services.sts.user_id');
        $this->password = config('services.sts.password');
        $this->meterType = (int) config('services.sts.meter_type', 2);
    }

    /**
     * Get meter information from STS provider.
     */
    public function getMeterInfo(
        string $meterCode
    ): array {

        $response = Http::timeout(30)
            ->get($this->baseUrl.'/api/Power/GetContractInfo',
                [
                    'UserId' => $this->userId,
                    'Password' => $this->password,
                    'MeterType' => $this->meterType,
                    'MeterCode' => $meterCode,
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'STS provider HTTP error: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['Code'] ?? null) != 200) {
            throw new RuntimeException(
                'STS provider error: '.
                ($data['Message'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Generate clear credit token.
     */
    public function getClearCreditToken(
        string $meterCode
    ): array {

        $response = Http::timeout(30)
            ->get($this->baseUrl.'/api/Power/GetClearCreditToken',
                [
                    'UserId' => $this->userId,
                    'Password' => $this->password,
                    'MeterType' => $this->meterType,
                    'MeterCode' => $meterCode,
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'STS provider HTTP error: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['Code'] ?? null) != 200) {
            throw new RuntimeException(
                'STS provider error: '.
                ($data['Message'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Generate clear tamper token.
     */
    public function getClearTamperToken(
        string $meterCode
    ): array {

        $response = Http::timeout(30)
            ->get($this->baseUrl.'/api/Power/GetClearTamperSignToken',
                [
                    'UserId' => $this->userId,
                    'Password' => $this->password,
                    'MeterType' => $this->meterType,
                    'MeterCode' => $meterCode,
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'STS provider HTTP error: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['Code'] ?? null) != 200) {
            throw new RuntimeException(
                'STS provider error: '.
                ($data['Message'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Generate a vending/token-generation transaction
     * from a successful tenant payment.
     *
     * Flow:
     *
     * Customer payment
     *      ↓
     * Water allocation
     *      ↓
     * Our water tariff
     *      ↓
     * Calculate m³
     *      ↓
     * STS provider
     *
     * AmountOrQuantity = m³
     * VendingType = 1
     */
    public function vendFromPayment(
        Payment $payment,
        Meter $meter
    ): StsTransaction {

        /*
        |--------------------------------------------------------------------------
        | Validate payment
        |--------------------------------------------------------------------------
        */

        if ($payment->status !== 'successful') {
            throw new RuntimeException(
                'Only successful payments can generate STS tokens.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment must have completed financial processing
        |--------------------------------------------------------------------------
        */

        if (! $payment->ledger_transaction_id) {
            throw new RuntimeException(
                'Payment has not completed financial processing.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate successful token generation
        |--------------------------------------------------------------------------
        */

        $existingTransaction =
            StsTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('transaction_type', 'token_generation')
                ->where('status', 'successful')
                ->first();

        if ($existingTransaction) {
            return $existingTransaction;
        }

        /*
        |--------------------------------------------------------------------------
        | Payment and meter organization must match
        |--------------------------------------------------------------------------
        */

        if (
            (int) $payment->organization_id
            !==
            (int) $meter->organization_id
        ) {
            throw new RuntimeException(
                'Payment and meter do not belong to the same organization.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Load payment allocations
        |--------------------------------------------------------------------------
        */

        $payment->loadMissing([
            'allocations',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find water allocation
        |--------------------------------------------------------------------------
        */

        $waterAllocation =
            $payment->allocations
                ->firstWhere(
                    'allocation_type',
                    'water'
                );

        if (! $waterAllocation) {
            throw new RuntimeException(
                'Payment does not have a water allocation.'
            );
        }

        $waterAmount =
            (float) $waterAllocation->amount;

        if ($waterAmount <= 0) {
            throw new RuntimeException(
                'Water allocation must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find active meter assignment
        |--------------------------------------------------------------------------
        */

        $assignment =
            $meter->assignments()
                ->whereNull(
                    'unassigned_at'
                )
                ->where(
                    'status',
                    'active'
                )
                ->with([
                    'unit.property',
                    'unit.activeTenancy.tenant',
                ])
                ->first();

        if (! $assignment) {
            throw new RuntimeException(
                'This meter is not currently assigned to a unit.'
            );
        }

        $unit =
            $assignment->unit;

        if (! $unit) {
            throw new RuntimeException(
                'Meter assignment does not have a unit.'
            );
        }

        $property =
            $unit->property;

        if (! $property) {
            throw new RuntimeException(
                'Meter unit does not belong to a property.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment property must match meter property
        |--------------------------------------------------------------------------
        */

        if (
            (int) $payment->property_id
            !==
            (int) $property->id
        ) {
            throw new RuntimeException(
                'Payment and meter do not belong to the same property.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Determine active tenant
        |--------------------------------------------------------------------------
        */

        $tenant =
            $unit->activeTenancy?->tenant;

        if (! $tenant) {
            throw new RuntimeException(
                'This unit does not have an active tenant.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment tenant must match active tenant
        |--------------------------------------------------------------------------
        */

        if (
            (int) $payment->tenant_id
            !==
            (int) $tenant->id
        ) {
            throw new RuntimeException(
                'Payment tenant does not match the tenant assigned to this meter.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Determine tariff date
        |--------------------------------------------------------------------------
        */

        $paymentDate =
            $payment->completed_at
                ? $payment
                    ->completed_at
                    ->toDateString()
                : now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Find OUR active water tariff
        |--------------------------------------------------------------------------
        */

        $tariff =
            WaterTariff::query()
                ->where(
                    'property_id',
                    $property->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->whereDate(
                    'effective_from',
                    '<=',
                    $paymentDate
                )
                ->where(
                    function ($query) use ($paymentDate) {

                        $query
                            ->whereNull(
                                'effective_to'
                            )
                            ->orWhereDate(
                                'effective_to',
                                '>=',
                                $paymentDate
                            );
                    }
                )
                ->orderByDesc(
                    'effective_from'
                )
                ->first();

        if (! $tariff) {
            throw new RuntimeException(
                'No active water tariff exists for this property.'
            );
        }

        $pricePerM3 =
            (float) $tariff->price_per_m3;

        if ($pricePerM3 <= 0) {
            throw new RuntimeException(
                'Water tariff must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Convert WATER MONEY → WATER QUANTITY
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Payment          = UGX 100,000
        | Water allocation = UGX 75,000
        | Tariff           = UGX 6,500 / m³
        |
        | 75,000 / 6,500
        | = 11.538 m³
        |
        */

        $quantity =
            round(
                $waterAmount /
                $pricePerM3,
                3
            );

        if ($quantity <= 0) {
            throw new RuntimeException(
                'Calculated water quantity must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate internal STS reference
        |--------------------------------------------------------------------------
        */

        $reference =
            'STS-'.
            strtoupper(
                Str::random(16)
            );

        /*
        |--------------------------------------------------------------------------
        | Create pending STS transaction
        |--------------------------------------------------------------------------
        */

        $transaction =
            StsTransaction::create([
                'meter_id' => $meter->id,

                'payment_id' => $payment->id,

                'reference' => $reference,

                /*
                |--------------------------------------------------------------------------
                | MUST MATCH sts_transactions migration
                |--------------------------------------------------------------------------
                */

                'transaction_type' => 'token_generation',

                'status' => 'pending',

                /*
                |--------------------------------------------------------------------------
                | OUR water money
                |--------------------------------------------------------------------------
                */

                'amount' => $waterAmount,

                /*
                |--------------------------------------------------------------------------
                | OUR calculated water quantity
                |--------------------------------------------------------------------------
                */

                'volume_m3' => $quantity,

                /*
                |--------------------------------------------------------------------------
                | Save request/audit information
                |--------------------------------------------------------------------------
                */

                'request_data' => [
                    'MeterCode' => $meter->meter_number,

                    'MeterType' => $this->meterType,

                    'AmountOrQuantity' => $quantity,

                    'VendingType' => 1,

                    'payment_amount' => (float) $payment->amount,

                    'water_allocation_amount' => $waterAmount,

                    'local_price_per_m3' => $pricePerM3,

                    'calculated_quantity_m3' => $quantity,

                    'currency' => $tariff->currency,
                ],
            ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Call STS provider
            |--------------------------------------------------------------------------
            */

            $response =
                Http::timeout(60)
                    ->get($this->baseUrl.'/api/Power/GetVendingToken',
                        [
                            'UserId' => $this->userId,
                            'Password' => $this->password,
                            'MeterType' => $this->meterType,
                            'MeterCode' => $meter->meter_number,

                            /*
                            |--------------------------------------------------------------------------
                            | Quantity generated by OUR billing engine
                            |--------------------------------------------------------------------------
                            */

                            'AmountOrQuantity' => $quantity,

                            /*
                            |--------------------------------------------------------------------------
                            | Provider mode: quantity
                            |--------------------------------------------------------------------------
                            */

                            'VendingType' => 1,
                        ]
                    );

            if (! $response->successful()) {
                throw new RuntimeException(
                    'STS provider HTTP error: '.
                    $response->status()
                );
            }

            $result =
                $response->json();

            if (
                ($result['Code'] ?? null)
                != 200
            ) {
                throw new RuntimeException(
                    'STS provider error: '.
                    (
                        $result['Message']
                        ?? 'Unknown error'
                    )
                );
            }

            $providerData =
                $result['Data'] ?? [];

            $token =
                $providerData['Token']
                ?? null;

            if (! $token) {
                throw new RuntimeException(
                    'STS provider did not return a vending token.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Provider quantity
            |--------------------------------------------------------------------------
            |
            | We store it for confirmation.
            |
            | Provider Tarrif/VendingAmount DO NOT control our billing.
            |
            */

            $providerQuantity =
                isset(
                    $providerData[
                        'VendingQuantity'
                    ]
                )
                    ? (float)
                        $providerData[
                            'VendingQuantity'
                        ]
                    : $quantity;

            /*
            |--------------------------------------------------------------------------
            | Mark STS transaction successful
            |--------------------------------------------------------------------------
            */

            $transaction->update([
                'status' => 'successful',

                'token' => $token,

                'external_reference' => $providerData[
                        'MeterCode'
                    ]
                    ?? null,

                'response_data' => $result,

                'completed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create WaterVending
            |--------------------------------------------------------------------------
            */

            $vending =
                WaterVending::create([
                    'payment_id' => $payment->id,

                    'tenant_id' => $tenant->id,

                    'property_id' => $property->id,

                    'meter_id' => $meter->id,

                    'water_tariff_id' => $tariff->id,

                    /*
                    |--------------------------------------------------------------------------
                    | OUR billing values
                    |--------------------------------------------------------------------------
                    */

                    'amount' => $waterAmount,

                    'price_per_m3' => $pricePerM3,

                    /*
                    |--------------------------------------------------------------------------
                    | Quantity confirmed by provider
                    |--------------------------------------------------------------------------
                    */

                    'volume_m3' => $providerQuantity,

                    'reference' => $reference,

                    'status' => 'successful',

                    'vended_at' => now(),
                ]);

            /*
            |--------------------------------------------------------------------------
            | Store meter token
            |--------------------------------------------------------------------------
            */

            MeterToken::create([
                'water_vending_id' => $vending->id,

                'meter_id' => $meter->id,

                'sts_transaction_id' => $transaction->id,

                'token' => $token,

                'token_type' => 'credit',

                'volume_m3' => $providerQuantity,

                'status' => 'generated',

                'generated_at' => now(),

                'issued_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Return transaction
            |--------------------------------------------------------------------------
            */

            return $transaction
                ->fresh([
                    'meter',
                    'payment',
                    'tokens',
                ]);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Record failed STS request
            |--------------------------------------------------------------------------
            */

            $transaction->update([
                'status' => 'failed',

                'error_message' => $e->getMessage(),

                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
