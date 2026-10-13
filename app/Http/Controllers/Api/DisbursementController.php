<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{MobileMoneyDisbursement, Property};
use App\Models\WaterWallet;
use App\Services\{DisbursementFeeService, MobileMoneyDisbursementService};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{JsonResponse, Request};

class  DisbursementController extends Controller
{
    use AuthorizesRequests;

    public function quote(Request $request, Property $property, DisbursementFeeService $fees): JsonResponse
    {
        $this->authorize('view', $property);
        $data = $request->validate(['amount' => ['required', 'integer', 'between:500,5000000']]);
        $amount = (int) $data['amount']; $fee = $fees->fee($amount);
        $wallet = WaterWallet::where('property_id', $property->id)->first();
        $availableBalance = $wallet ? (float) $wallet->balance : null;
        $totalDebit = $amount + $fee;
        return response()->json(['success' => true, 'data' => [
            'currency' => 'UGX', 'recipient_amount' => $amount, 'gateway_fee' => $fee,
            'total_debit' => $totalDebit, 'water_wallet_balance' => $availableBalance,
            'available_balance' => $availableBalance,
            'sufficient_balance' => $wallet !== null && bccomp((string) $wallet->balance, (string) $totalDebit, 2) > 0,
        ]]);
    }

    public function store(Request $request, Property $property, MobileMoneyDisbursementService $service): JsonResponse
    {
        $this->authorize('view', $property);
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'regex:/^\+2567\d{8}$/'],
            'amount' => ['required', 'integer', 'between:500,5000000'],
            'nwsc_account_id' => ['required', 'integer'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $record = $service->initiate($property, $request->user(), $data);
        return response()->json(['success' => true, 'message' => 'Disbursement recorded. Inspect its status for provider confirmation.', 'data' => $record->load(['property:id,name,property_code', 'nwscAccount:id,property_id,account_number,account_name', 'waterWallet:id,property_id,currency,balance,status'])], 202);
    }

    public function index(Request $request, Property $property): JsonResponse
    {
        $this->authorize('view', $property);
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);
        return response()->json(['success' => true, 'data' => MobileMoneyDisbursement::where('property_id', $property->id)
            ->with(['property:id,name,property_code', 'nwscAccount:id,property_id,account_number,account_name', 'waterWallet:id,property_id,currency,balance,status'])
            ->latest()->paginate($data['per_page'] ?? 20)]);
    }

    public function show(Request $request, Property $property, MobileMoneyDisbursement $disbursement): JsonResponse
    {
        $this->authorize('view', $property);
        abort_unless($disbursement->property_id === $property->id, 404);
        return response()->json(['success' => true, 'data' => $disbursement->load([
            'property:id,name,property_code', 'nwscAccount:id,property_id,account_number,account_name', 'waterWallet:id,property_id,currency,balance,status',
        ])]);
    }

    public function refresh(Request $request, Property $property, MobileMoneyDisbursement $disbursement, MobileMoneyDisbursementService $service): JsonResponse
    {
        $this->authorize('view', $property);
        abort_unless($disbursement->property_id === $property->id, 404);
        try { $record = $service->refresh($disbursement); }
        catch (\RuntimeException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 502); }
        return response()->json(['success' => true, 'data' => $record->load([
            'property:id,name,property_code', 'nwscAccount:id,property_id,account_number,account_name', 'waterWallet:id,property_id,currency,balance,status',
        ])]);
    }
}
