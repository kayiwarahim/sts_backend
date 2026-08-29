<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentAllocationService;
use Illuminate\Http\JsonResponse;

class PaymentAllocationController extends Controller
{
    public function __construct(
        protected PaymentAllocationService $allocationService
    ) {}

    /**
     * Allocate a successful payment.
     */
    public function allocate(
        Payment $payment
    ): JsonResponse {

        if ($payment->status !== 'successful') {

            return response()->json([
                'success' => false,
                'message' => 'Only successful payments can be allocated.',
            ], 422);
        }

        $allocations = $this->allocationService->allocate($payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment allocated successfully.',
            'data' => $allocations,
        ]);
    }
}
