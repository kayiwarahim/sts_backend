<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\WaterWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandlordPortalController extends Controller
{
    protected function organizationId(
        Request $request
    ): int {

        $user =
            $request->user();

        abort_if(
            !$user,
            401,
            'Unauthenticated.'
        );

        abort_if(
            !$user->hasAnyRole([
                'Landlord',
                'Super Admin',
            ]),
            403,
            'Landlord access required.'
        );

        abort_if(
            !$user->organization_id,
            422,
            'This user is not linked to an organization.'
        );

        return
            (int)
            $user->organization_id;
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard Summary
    |--------------------------------------------------------------------------
    */

    public function dashboard(
        Request $request
    ): JsonResponse {

        $organizationId =
            $this->organizationId(
                $request
            );

        $properties =
            Property::query()
                ->where(
                    'organization_id',
                    $organizationId
                );

        $propertyCount =
            (clone $properties)
                ->count();

        $unitCount =
            Unit::query()
                ->whereHas(
                    'property',
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                )
                ->count();

        $tenantCount =
            Tenant::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->count();

        $meterCount =
            Meter::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->count();

        $activeMeterCount =
            Meter::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'status',
                    'active'
                )
                ->count();

        $successfulPayments =
            Payment::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'status',
                    'successful'
                );

        $totalCollections =
            (float)
            (clone $successfulPayments)
                ->sum('amount');

        $successfulPaymentCount =
            (clone $successfulPayments)
                ->count();

        $waterWalletBalance =
            (float)
            WaterWallet::query()
                ->whereHas(
                    'property',
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                )
                ->sum('balance');

        $recentPayments =
            Payment::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->with([
                    'tenant:id,first_name,last_name',
                    'property:id,name,property_code',
                ])
                ->latest(
                    'initiated_at'
                )
                ->limit(10)
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'properties' =>
                    $propertyCount,

                'units' =>
                    $unitCount,

                'tenants' =>
                    $tenantCount,

                'meters' =>
                    $meterCount,

                'active_meters' =>
                    $activeMeterCount,

                'successful_payments' =>
                    $successfulPaymentCount,

                'total_collections' =>
                    $totalCollections,

                'water_wallet_balance' =>
                    $waterWalletBalance,

                'recent_payments' =>
                    $recentPayments,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization Payments
    |--------------------------------------------------------------------------
    */

    public function payments(
        Request $request
    ): JsonResponse {

        $organizationId =
            $this->organizationId(
                $request
            );

        $perPage =
            min(
                (int)
                $request->input(
                    'per_page',
                    25
                ),
                100
            );

        $query =
            Payment::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->with([
                    'tenant:id,first_name,last_name,phone',
                    'property:id,name,property_code',
                    'paymentProvider:id,name,code',
                    'waterVending.tokens',
                ]);

        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        $payments =
            $query
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
    | Water Wallets
    |--------------------------------------------------------------------------
    */

    public function waterWallet(
        Request $request
    ): JsonResponse {

        $organizationId =
            $this->organizationId(
                $request
            );

        $wallets =
            WaterWallet::query()
                ->whereHas(
                    'property',
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                )
                ->with([
                    'property:id,name,property_code,address',
                ])
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'currency' =>
                    'UGX',

                'total_balance' =>
                    (float)
                    $wallets->sum(
                        'balance'
                    ),

                'wallets' =>
                    $wallets,
            ],
        ]);
    }
}