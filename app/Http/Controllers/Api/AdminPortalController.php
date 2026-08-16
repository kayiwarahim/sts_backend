<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPortalController extends Controller
{
    protected function authorizeAdmin(
        Request $request
    ): void {

        $user =$request->user();

        abort_if(!$user,401,'Unauthenticated.');

        abort_if(!$user->isSuperAdmin(), 403,'Super Admin access required.');
    }

    /*Platform Dashboard*/

    public function dashboard(
        Request $request
    ): JsonResponse {

        $this->authorizeAdmin($request);

        $successfulPayments =Payment::query()->where('status','successful');

        $totalCollections =(float)(clone $successfulPayments)->sum('amount');

        $paymentCount =(clone $successfulPayments)->count();

        $saasRevenue =
            (float)
            PaymentAllocation::query()
                ->where('allocation_type','saas')
                ->whereHas('payment',fn ($query) =>$query->where('status','successful'))
                ->sum('amount');

        $gatewayFees =
            (float)
            PaymentAllocation::query()
                ->where('allocation_type','gateway_fee')
                ->whereHas('payment',fn ($query) =>$query->where('status','successful'))
                ->sum('amount');

        $recentPayments =
            Payment::query()
                ->with([
                    'organization:id,name',
                    'tenant:id,first_name,last_name',
                    'property:id,name,property_code',
                ])
                ->latest('initiated_at')->limit(10)->get();

        return response()->json([
            'success' => true,
            'data' => ['organizations' =>Organization::count(),
            'landlords' =>User::role('Landlord')->count(),
            'tenants' =>Tenant::count(),
            'meters' =>Meter::count(),
            'successful_payments' =>$paymentCount,
            'total_collections' =>$totalCollections,
            'saas_revenue' =>$saasRevenue,
            'gateway_fees' =>$gatewayFees,
            'recent_payments' =>$recentPayments,
            ],
        ]);
    }

    /*Platform Payments*/

    public function payments(
        Request $request
    ): JsonResponse {

        $this->authorizeAdmin($request);

        $perPage =min((int)$request->input( 'per_page',25 ), 100);

        $query =Payment::query()
                ->with([
                    'organization:id,name',
                    'property:id,name,property_code',
                    'tenant:id,first_name,last_name,phone',
                    'paymentProvider:id,name,code',
                    'waterVending.tokens',
                ]);

        if (
            $request->filled('status')
        ) {
            $query->where('status',$request->status);
        }

        if (
            $request->filled('organization_id')
        ) {
            $query->where('organization_id',$request->organization_id);
        }

        $payments =$query->latest('initiated_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }
}