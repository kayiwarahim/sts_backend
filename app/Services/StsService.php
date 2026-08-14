<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Payment;
use App\Models\StsTransaction;
use App\Models\WaterTariff;
use App\Models\WaterVending;
use App\Models\MeterToken;
use Illuminate\Support\Facades\DB;
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
    public function getMeterInfo(string $meterCode): array
    {
        $response = Http::timeout(30)
            ->get($this->baseUrl . '/api/Power/GetContractInfo', [
                'UserId' => $this->userId,
                'Password' => $this->password,
                'MeterType' => $this->meterType,
                'MeterCode' => $meterCode,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'STS provider HTTP error: ' . $response->status()
            );
        }

        $data = $response->json();

        if (($data['Code'] ?? null) != 200) {
            throw new RuntimeException(
                'STS provider error: ' . ($data['Message'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Generate STS vending token.
     *
     * IMPORTANT:
     * Our local WaterTariff determines the price.
     * The STS provider tariff is NOT used for billing.
     *
     * AmountOrQuantity = requested water quantity.
     * VendingType = 1.
     */
    public function vendToken(
        Meter $meter,
        float $quantity,
        ?int $paymentId = null
    ): StsTransaction {

        if ($quantity <= 0) {
            throw new RuntimeException(
                'Water quantity must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get meter assignment
        |--------------------------------------------------------------------------
        */

        $assignment = $meter->assignments()
            ->whereNull('unassigned_at')
            ->with('unit.property')
            ->first();

        if (!$assignment) {
            throw new RuntimeException(
                'This meter is not currently assigned to a unit.'
            );
        }

        $unit = $assignment->unit;

        if (!$unit || !$unit->property) {
            throw new RuntimeException(
                'The meter assignment does not have a valid property.'
            );
        }

        $property = $unit->property;

        /*
        |--------------------------------------------------------------------------
        | Get OUR tariff
        |--------------------------------------------------------------------------
        */

        $tariff = WaterTariff::query()
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        now()->toDateString()
                    );
            })
            ->orderByDesc('effective_from')
            ->first();

        if (!$tariff) {
            throw new RuntimeException(
                'No active water tariff exists for this property.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate price using OUR tariff
        |--------------------------------------------------------------------------
        */

        $pricePerM3 = (float) $tariff->price_per_m3;

        $amount = round(
            $quantity * $pricePerM3,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Create STS transaction record
        |--------------------------------------------------------------------------
        */

        $reference = 'STS-' . strtoupper(
            Str::random(16)
        );

        $transaction = StsTransaction::create([
            'meter_id' => $meter->id,
            'payment_id' => $paymentId,
            'reference' => $reference,
            'transaction_type' => 'vending',
            'status' => 'pending',
            'amount' => $amount,
            'volume_m3' => $quantity,
            'request_data' => [
                'MeterCode' => $meter->meter_number,
                'MeterType' => $this->meterType,
                'AmountOrQuantity' => $quantity,
                'VendingType' => 1,
                'local_tariff' => $pricePerM3,
                'currency' => $tariff->currency,
            ],
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Call STS provider
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(60)
                ->get(
                    $this->baseUrl . '/api/Power/GetVendingToken',
                    [
                        'UserId' => $this->userId,
                        'Password' => $this->password,
                        'MeterType' => $this->meterType,
                        'MeterCode' => $meter->meter_number,

                        // IMPORTANT:
                        // Quantity, NOT our money amount.
                        'AmountOrQuantity' => $quantity,

                        // 1 = quantity
                        'VendingType' => 1,
                    ]
                );

            if (!$response->successful()) {
                throw new RuntimeException(
                    'STS provider HTTP error: ' . $response->status()
                );
            }

            $result = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Validate provider response
            |--------------------------------------------------------------------------
            */

            if (($result['Code'] ?? null) != 200) {
                throw new RuntimeException(
                    'STS provider error: ' .
                    ($result['Message'] ?? 'Unknown error')
                );
            }

            $providerData = $result['Data'] ?? [];

            $token = $providerData['Token'] ?? null;

            if (!$token) {
                throw new RuntimeException(
                    'STS provider did not return a vending token.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | Ignore provider Tarrif and VendingAmount for our billing.
            |--------------------------------------------------------------------------
            */

            $providerQuantity = isset($providerData['VendingQuantity'])
                ? (float) $providerData['VendingQuantity']
                : $quantity;

            /*
            |--------------------------------------------------------------------------
            | Save successful STS transaction
            |--------------------------------------------------------------------------
            */

            $transaction->update([
                'status' => 'successful',
                'token' => $token,
                'external_reference' => $providerData['MeterCode'] ?? null,
                'response_data' => $result,
                'completed_at' => now(),
            ]);

            $payment = $paymentId
                ? Payment::find($paymentId)
                : null;

            /*
            |--------------------------------------------------------------------------
            | Create WaterVending record
            |--------------------------------------------------------------------------
            */

            $vending = WaterVending::create([
                'payment_id' => $paymentId,
                'tenant_id' => $payment?->tenant_id,
                'property_id' => $property->id,
                'meter_id' => $meter->id,
                'water_tariff_id' => $tariff->id,

                // OUR calculated amount
                'amount' => $amount,

                // OUR tariff
                'price_per_m3' => $pricePerM3,

                // Actual quantity returned by provider if available
                'volume_m3' => $providerQuantity,

                'reference' => $reference,
                'status' => 'successful',
                'vended_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Save token
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

            return $transaction->fresh();

        } catch (\Throwable $e) {

            $transaction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}