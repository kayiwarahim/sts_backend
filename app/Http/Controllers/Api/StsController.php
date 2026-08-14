<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Payment;
use App\Services\StsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StsController extends Controller
{
    public function __construct(
        protected StsService $stsService
    ) {
    }

    /**
     * Get meter information from the STS provider.
     */
    public function meterInfo(
        Meter $meter
    ): JsonResponse {

        try {

            $result = $this->stsService
                ->getMeterInfo(
                    $meter->meter_number
                );

            return response()->json([
                'success' => true,
                'message' =>
                    'Meter information retrieved successfully.',
                'data' =>
                    $result['Data'] ?? null,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate an STS water token from a successful payment.
     *
     * Client sends:
     *
     * {
     *     "payment_id": 123
     * }
     *
     * Backend flow:
     *
     * Payment
     *   ↓
     * Water Allocation
     *   ↓
     * Local Water Tariff
     *   ↓
     * Quantity m³
     *   ↓
     * STS Provider
     *   ↓
     * Token
     */
    public function vend(
        Request $request,
        Meter $meter
    ): JsonResponse {

        $validated = $request->validate([
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
            ],
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Load payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->with([
                    'allocations',
                    'tenant',
                    'property',
                    'waterVending',
                ])
                ->findOrFail(
                    $validated['payment_id']
                );

            /*
            |--------------------------------------------------------------------------
            | Validate successful payment
            |--------------------------------------------------------------------------
            */

            if (
                $payment->status !==
                'successful'
            ) {
                throw new RuntimeException(
                    'Only successful payments can generate STS tokens.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Payment and meter must belong to same organization
            |--------------------------------------------------------------------------
            */

            if (
                $payment->organization_id
                !==
                $meter->organization_id
            ) {
                throw new RuntimeException(
                    'The selected meter does not belong to the payment organization.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Generate token
            |--------------------------------------------------------------------------
            */

            $transaction =
                $this->stsService
                    ->vendFromPayment(
                        $payment,
                        $meter
                    );

            /*
            |--------------------------------------------------------------------------
            | Reload related records
            |--------------------------------------------------------------------------
            */

            $transaction->load([
                'meter',
                'payment',
                'tokens',
            ]);

            $vending =
                $payment
                    ->fresh()
                    ->waterVending()
                    ->with([
                        'waterTariff',
                        'tokens',
                    ])
                    ->first();

            /*
            |--------------------------------------------------------------------------
            | Return response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'STS water token generated successfully.',

                'data' => [

                    'payment' => [
                        'id' =>
                            $payment->id,

                        'reference' =>
                            $payment->reference,

                        'amount' =>
                            $payment->amount,

                        'currency' =>
                            $payment->currency,
                    ],

                    'water_allocation' => [
                        'amount' =>
                            optional(
                                $payment
                                    ->allocations
                                    ->firstWhere(
                                        'allocation_type',
                                        'water'
                                    )
                            )->amount,
                    ],

                    'meter' => [
                        'id' =>
                            $meter->id,

                        'meter_number' =>
                            $meter->meter_number,
                    ],

                    'sts_transaction' => [
                        'id' =>
                            $transaction->id,

                        'reference' =>
                            $transaction->reference,

                        'status' =>
                            $transaction->status,

                        'amount' =>
                            $transaction->amount,

                        'volume_m3' =>
                            $transaction->volume_m3,

                        'token' =>
                            $transaction->token,

                        'completed_at' =>
                            $transaction
                                ->completed_at,
                    ],

                    'water_vending' =>
                        $vending
                            ? [
                                'id' =>
                                    $vending->id,

                                'amount' =>
                                    $vending->amount,

                                'price_per_m3' =>
                                    $vending->price_per_m3,

                                'volume_m3' =>
                                    $vending->volume_m3,

                                'status' =>
                                    $vending->status,

                                'vended_at' =>
                                    $vending->vended_at,
                            ]
                            : null,
                ],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate a clear credit token.
     */
    public function clearCredit(
        Meter $meter
    ): JsonResponse {

        try {

            $result =
                $this->stsService
                    ->getClearCreditToken(
                        $meter->meter_number
                    );

            return response()->json([
                'success' => true,

                'message' =>
                    'Clear credit token generated successfully.',

                'data' => [
                    'meter_number' =>
                        $meter->meter_number,

                    'token' =>
                        $result['Data']
                        ?? null,
                ],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate a clear tamper token.
     */
    public function clearTamper(
        Meter $meter
    ): JsonResponse {

        try {

            $result =
                $this->stsService
                    ->getClearTamperToken(
                        $meter->meter_number
                    );

            return response()->json([
                'success' => true,

                'message' =>
                    'Clear tamper token generated successfully.',

                'data' => [
                    'meter_number' =>
                        $meter->meter_number,

                    'token' =>
                        $result['Data']
                        ?? null,
                ],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }
}