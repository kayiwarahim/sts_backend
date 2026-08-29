<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Payment;
use App\Services\MobileMoneyPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMoneyPaymentController extends Controller
{
    public function __construct(
        protected MobileMoneyPaymentService $service
    ) {}

    /**
     * Public mobile-money payment initiation.
     *
     * Anyone can pay for an active STS meter.
     */
    public function initiate(
        Request $request
    ): JsonResponse {

        $validated = $request->validate([
            'meter_number' => ['required', 'string', 'exists:meters,meter_number'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'msisdn' => ['required', 'string', 'max:20'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find meter
        |--------------------------------------------------------------------------
        */

        $meter = Meter::query()->where('meter_number', $validated['meter_number'])->where('status', 'active')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Initiate payment
        |--------------------------------------------------------------------------
        */

        $payment =
            $this->service
                ->initiateForMeter(
                    $meter,
                    (float)
                    $validated['amount'],
                    $validated['msisdn']
                );

        return response()->json([
            'success' => true,
            'message' => 'Mobile money payment request initiated.',
            'data' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payer_phone' => $payment->payer_phone,
                'status' => $payment->status,
                'meter_number' => $meter->meter_number,
            ],
        ], 201);
    }

    /**
     * Public status lookup using the opaque payment reference.
     */
    public function status(
        string $reference
    ): JsonResponse {

        $payment = Payment::query()->where('reference', $reference)->firstOrFail();

        $payment = $this->service->checkStatus($payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment status retrieved.',
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'completed_at' => $payment->completed_at,
                'water_vending' => $payment->waterVending()->with('tokens')->first(),
            ],
        ]);
    }
}
