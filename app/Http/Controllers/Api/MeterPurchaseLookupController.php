<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;

class MeterPurchaseLookupController extends Controller
{
    public function show(
        string $meterNumber
    ): JsonResponse {
        $meterNumber =
            trim(
                $meterNumber
            );

        /*
        |--------------------------------------------------------------------------
        | Find Meter
        |--------------------------------------------------------------------------
        */

        $meter =
            Meter::query()
                ->where(
                    'meter_number',
                    $meterNumber
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();

        if (!$meter) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Meter number was not found or is not active.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Active Assignment
        |--------------------------------------------------------------------------
        */

        $assignment =
            $meter
                ->assignments()
                ->whereNull(
                    'unassigned_at'
                )
                ->where(
                    'status',
                    'active'
                )
                ->with([
                    'unit.property',
                ])
                ->latest(
                    'assigned_at'
                )
                ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This meter is not currently assigned.',
            ], 422);
        }

        $unit =
            $assignment->unit;

        if (!$unit) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The meter assignment does not have a valid unit.',
            ], 422);
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

        $tenancy =
            $unit
                ->tenancies()
                ->where(
                    'status',
                    'active'
                )
                ->with(
                    'tenant'
                )
                ->latest(
                    'start_date'
                )
                ->first();

        if (
            !$tenancy ||
            !$tenancy->tenant
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'No active tenant is currently associated with this meter.',
            ], 422);
        }

        $tenant =
            $tenancy->tenant;

        /*
        |--------------------------------------------------------------------------
        | Public-Safe Response
        |--------------------------------------------------------------------------
        |
        | Do not expose tenant email, phone, national ID, user ID, etc.
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Meter found.',

            'data' => [
                'meter_number' =>
                    $meter
                        ->meter_number,

                'tenant_name' =>
                    trim(
                        $tenant
                            ->first_name .
                        ' ' .
                        $tenant
                            ->last_name
                    ),

                'property_name' =>
                    $unit
                        ->property
                        ?->name,

                'unit_number' =>
                    $unit
                        ->unit_number,
            ],
        ]);
    }
}