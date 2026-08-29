<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class MeterPurchaseLookupController extends Controller
{
    public function show(
        string $meterNumber
    ): JsonResponse {
        $meterNumber = trim($meterNumber);

        /*
        |--------------------------------------------------------------------------
        | Find Meter
        |--------------------------------------------------------------------------
        */

        $meter = Meter::query()->where('meter_number', $meterNumber)
            ->where('status', 'active')->first();

        if (! $meter) {
            return response()->json([
                'success' => false,
                'message' => 'Meter number was not found or is not active.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Active Assignment
        |--------------------------------------------------------------------------
        */

        $assignment = $meter
            ->assignments()->whereNull('unassigned_at')
            ->where('status', 'active')
            ->with(['unit.property'])
            ->latest('assigned_at')
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'This meter is not currently assigned.', ],
                422);
        }

        $unit = $assignment->unit;

        if (! $unit) {
            return response()->json([
                'success' => false,
                'message' => 'The meter assignment does not have a valid unit.', ],
                422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Active Tenancy
        |--------------------------------------------------------------------------
        |
        | Assumption:
        |
        | Unit -> tenancies -> tenant
        |--------------------------------------------------------------------------
        */

        $tenancy = $unit->tenancies()
            ->where('status', 'active')
            ->with('tenant')
            ->latest('start_date')
            ->first();

        if (
            ! $tenancy ||
            ! $tenancy->tenant
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant is currently associated with this meter.', ],
                422);
        }

        $tenant = $tenancy->tenant;

        /*
        |--------------------------------------------------------------------------
        | Public-Safe Response
        |--------------------------------------------------------------------------
        |
        | Do not expose tenant email, phone, national ID, user ID, etc.
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Meter found.',
            'data' => [
                'meter_number' => $meter->meter_number,
                'tenant_name' => trim($tenant->first_name.' '.$tenant->last_name),
                'property_name' => $unit->property?->name,
                'unit_number' => $unit->unit_number,
            ],
        ]);
    }

    public function retrieveToken(
        string $providerTransactionId
    ): JsonResponse {
        $payment =
            Payment::query()
                ->where('provider_transaction_id', $providerTransactionId)
                ->with(['tenant', 'waterVending.meter', 'waterVending.tokens'])
                ->first();

        if (! $payment) {
            return response()->json(['message' => 'No transaction was found with that transaction ID.'],
                404);
        }

        if (
            $payment->status !== 'successful'
        ) {
            return response()->json(['message' => 'This transaction was not completed successfully.'],
                422);
        }
        $waterVending = $payment->waterVending;

        $token = $waterVending?->tokens?->first();

        if (! $token) {
            return response()->json(['message' => 'No water token was found for this transaction.'],
                404);
        }

        return response()->json([
            'data' => [
                'provider_transaction_id' => $payment->provider_transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'tenant_name' => $payment->tenant ? trim($payment->tenant->first_name.' '.$payment->tenant->last_name) : null,
                'meter_number' => $payment->waterVending?->meter?->meter_number,
                'token' => $token->token,
                'created_at' => $token->created_at,
            ],
        ]);
    }
}
