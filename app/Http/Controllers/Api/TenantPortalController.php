<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeterToken;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPortalController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Resolve logged-in tenant
    |--------------------------------------------------------------------------
    */

    protected function tenant(
        Request $request
    ): Tenant {

        $user =
            $request->user();

        abort_if(
            !$user,
            401,
            'Unauthenticated.'
        );

        abort_if(
            !$user->hasRole('Tenant'),
            403,
            'Tenant access required.'
        );

        $tenant =
            Tenant::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        abort_if(
            !$tenant,
            404,
            'No tenant profile is linked to this account.'
        );

        return $tenant;
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(
        Request $request
    ): JsonResponse {

        $tenant =
            $this->tenant(
                $request
            );

        $successfulPayments =
            Payment::query()
                ->where(
                    'tenant_id',
                    $tenant->id
                )
                ->where(
                    'status',
                    'successful'
                );

        $totalPaid =
            (float)
            (clone $successfulPayments)
                ->sum('amount');

        $paymentCount =
            (clone $successfulPayments)
                ->count();

        $totalWater =
            (float)
            $tenant
                ->waterVendings()
                ->where(
                    'status',
                    'successful'
                )
                ->sum(
                    'volume_m3'
                );

        $activeTenancy =
            $tenant
                ->activeTenancy()
                ->with([
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ])
                ->first();

        $meter =
            $activeTenancy
                ?->unit
                ?->activeMeterAssignment
                ?->meter;

        return response()->json([
            'success' => true,

            'data' => [
                'tenant' => [
                    'id' =>
                        $tenant->id,

                    'name' =>
                        $tenant->full_name,

                    'phone' =>
                        $tenant->phone,

                    'email' =>
                        $tenant->email,
                ],

                'summary' => [
                    'total_paid' =>
                        $totalPaid,

                    'successful_payments' =>
                        $paymentCount,

                    'total_water_m3' =>
                        $totalWater,

                    'meter_status' =>
                        $meter?->status,
                ],

                'meter' =>
                    $meter
                        ? [
                            'id' =>
                                $meter->id,

                            'meter_number' =>
                                $meter->meter_number,

                            'status' =>
                                $meter->status,
                        ]
                        : null,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | My Meter
    |--------------------------------------------------------------------------
    */

    public function meter(
        Request $request
    ): JsonResponse {

        $tenant =
            $this->tenant(
                $request
            );

        $tenancy =
            $tenant
                ->activeTenancy()
                ->with([
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ])
                ->first();

        abort_if(
            !$tenancy,
            404,
            'You do not have an active tenancy.'
        );

        $unit =
            $tenancy->unit;

        $assignment =
            $unit
                ->activeMeterAssignment;

        abort_if(
            !$assignment,
            404,
            'Your unit does not have an active meter assignment.'
        );

        $meter =
            $assignment->meter;

        abort_if(
            !$meter,
            404,
            'Assigned meter could not be found.'
        );

        return response()->json([
            'success' => true,

            'data' => [
                'id' =>
                    $meter->id,

                'meter_number' =>
                    $meter->meter_number,

                'serial_number' =>
                    $meter->serial_number,

                'manufacturer' =>
                    $meter->manufacturer,

                'model' =>
                    $meter->model,

                'meter_type' =>
                    $meter->meter_type,

                'status' =>
                    $meter->status,

                'installed_at' =>
                    $meter->installed_at,

                'assignment' => [
                    'id' =>
                        $assignment->id,

                    'assigned_at' =>
                        $assignment->assigned_at,

                    'status' =>
                        $assignment->status,
                ],

                'unit' => [
                    'id' =>
                        $unit->id,

                    'unit_number' =>
                        $unit->unit_number,

                    'floor' =>
                        $unit->floor,

                    'status' =>
                        $unit->status,
                ],

                'property' => [
                    'id' =>
                        $unit->property->id,

                    'name' =>
                        $unit->property->name,

                    'property_code' =>
                        $unit->property
                            ->property_code,

                    'address' =>
                        $unit->property
                            ->address,
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment History
    |--------------------------------------------------------------------------
    */

    public function payments(
        Request $request
    ): JsonResponse {

        $tenant =
            $this->tenant(
                $request
            );

        $perPage =
            min(
                (int)
                $request->input(
                    'per_page',
                    20
                ),
                100
            );

        $payments =
            Payment::query()
                ->where(
                    'tenant_id',
                    $tenant->id
                )
                ->with([
                    'property:id,name,property_code',
                    'waterVending.tokens',
                ])
                ->latest(
                    'initiated_at'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Token History
    |--------------------------------------------------------------------------
    */

    public function tokens(
        Request $request
    ): JsonResponse {

        $tenant =
            $this->tenant(
                $request
            );

        $perPage =
            min(
                (int)
                $request->input(
                    'per_page',
                    20
                ),
                100
            );

        $tokens =
            MeterToken::query()
                ->whereHas(
                    'waterVending',
                    function ($query)
                    use ($tenant) {

                        $query->where(
                            'tenant_id',
                            $tenant->id
                        );
                    }
                )
                ->with([
                    'meter:id,meter_number',
                    'waterVending:id,payment_id,tenant_id,property_id,meter_id,amount,volume_m3,reference,status,vended_at',
                ])
                ->latest(
                    'generated_at'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }
}