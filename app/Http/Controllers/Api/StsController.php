<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Services\StsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StsController extends Controller
{
    public function __construct(
        protected StsService $stsService
    ) {
    }

    public function meterInfo(Meter $meter): JsonResponse
    {
        $result = $this->stsService->getMeterInfo(
            $meter->meter_number
        );

        return response()->json([
            'message' => 'Meter information retrieved successfully.',
            'data' => $result['Data'] ?? null,
        ]);
    }

    public function vend(Request $request, Meter $meter): JsonResponse
    {
        $validated = $request->validate([
            'quantity_m3' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'payment_id' => [
                'nullable',
                'integer',
                'exists:payments,id',
            ],
        ]);

        $transaction = $this->stsService->vendToken(
            $meter,
            (float) $validated['quantity_m3'],
            $validated['payment_id'] ?? null
        );

        return response()->json([
            'message' => 'STS token generated successfully.',
            'data' => [
                'transaction' => $transaction,
                'token' => $transaction->token,
                'meter_code' => $meter->meter_number,
                'amount' => $transaction->amount,
                'volume_m3' => $transaction->volume_m3,
            ],
        ]);
    }
}