<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PropertyFinancialStatisticsService
{
    public function allProperties(User $user, int $perPage = 20, int $transactionsPerPage = 20, int $transactionsPage = 1): LengthAwarePaginator
    {
        $query = Property::query()->with(['nwscAccounts', 'waterWallet']);
        if (! $user->isSuperAdmin()) {
            $query->where('organization_id', $user->organization_id);
        }

        return $query->orderBy('id')->paginate($perPage)->withQueryString()
            ->through(fn (Property $property) => $this->report($property, $transactionsPerPage, $transactionsPage, 'transactions_page'));
    }

    public function report(Property $property, int $perPage = 20, ?int $page = null, string $pageName = 'page'): array
    {
        $property->loadMissing(['nwscAccounts', 'waterWallet']);
        // Aggregate before joining so multiple allocations or STS attempts never multiply payments.
        $water = DB::table('payment_allocations')->where('allocation_type', 'water')
            ->selectRaw('payment_id, SUM(amount) AS water_amount')->groupBy('payment_id');
        $vending = DB::table('water_vendings')->where('status', 'successful')
            ->where('property_id', $property->id)
            ->selectRaw('payment_id, SUM(volume_m3) AS delivered_volume')->groupBy('payment_id');
        $totals = DB::table('payments as p')
            ->leftJoinSub($water, 'w', 'w.payment_id', '=', 'p.id')
            ->leftJoinSub($vending, 'v', 'v.payment_id', '=', 'p.id')
            ->where('p.property_id', $property->id);
        $columns = "COUNT(*) AS transaction_count,
            SUM(CASE WHEN p.status = 'successful' THEN 1 ELSE 0 END) AS successful_count,
            SUM(CASE WHEN p.status = 'successful' THEN p.amount ELSE 0 END) AS amount_paid,
            SUM(CASE WHEN p.status = 'successful' THEN COALESCE(w.water_amount, 0) ELSE 0 END) AS water_allocated,
            SUM(COALESCE(v.delivered_volume, 0)) AS units_delivered_m3,
            SUM(CASE WHEN p.status = 'successful' AND w.payment_id IS NULL THEN 1 ELSE 0 END) AS missing_water_allocation_count";
        $summary = (clone $totals)->selectRaw('p.currency, '.$columns)->groupBy('p.currency')->get()
            ->map(fn ($row) => $this->formatTotals($row))->values();
        $tenants = (clone $totals)->leftJoin('tenants as t', 't.id', '=', 'p.tenant_id')
            ->selectRaw('p.tenant_id, t.first_name, t.last_name, p.currency, '.$columns)
            ->groupBy('p.tenant_id', 't.first_name', 't.last_name', 'p.currency')
            ->orderBy('p.tenant_id')->get()->map(fn ($row) => array_merge([
                'tenant_id' => $row->tenant_id,
                'tenant_name' => trim($row->first_name.' '.$row->last_name),
            ], $this->formatTotals($row)))->values();

        $transactions = Payment::query()->where('property_id', $property->id)
            ->with(['tenant', 'allocations', 'waterVending.meter', 'stsTransactions.meter'])
            ->orderByDesc('initiated_at')->orderByDesc('id')->paginate($perPage, ['*'], $pageName, $page)->withQueryString();
        $transactions->through(function (Payment $payment) {
            $vending = $payment->waterVending;
            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'tenant_id' => $payment->tenant_id,
                'tenant_name' => $payment->tenant?->full_name,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'requested_amount' => $payment->amount,
                'successful_amount_paid' => $payment->isSuccessful() ? $payment->amount : '0.00',
                'water_allocation_amount' => $payment->isSuccessful()
                    ? $this->decimal($payment->allocations->where('allocation_type', 'water')->sum('amount')) : '0.00',
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'type' => $allocation->allocation_type, 'amount' => $allocation->amount,
                ])->values(),
                'meter_number' => $vending?->meter?->meter_number,
                'units_delivered_m3' => $vending?->status === 'successful' ? $vending->volume_m3 : '0.000',
                'water_vending_status' => $vending?->status,
                'sts_transactions' => $payment->stsTransactions->map(fn ($sts) => [
                    'id' => $sts->id, 'reference' => $sts->reference,
                    'type' => $sts->transaction_type, 'status' => $sts->status,
                    'meter_number' => $sts->meter?->meter_number,
                    'amount' => $sts->amount,
                    'requested_units_m3' => $sts->volume_m3,
                    'completed_at' => $sts->completed_at,
                ])->values(),
                'initiated_at' => $payment->initiated_at,
                'completed_at' => $payment->completed_at,
                'failure_reason' => $payment->failure_reason,
            ];
        });

        $wallet = $property->waterWallet;
        $activeAccounts = $property->nwscAccounts->where('status', 'active')->values();
        $destination = $activeAccounts->count() === 1 ? $activeAccounts->first() : null;
        $remitted = DB::table('nwsc_payments as n')->join('nwsc_accounts as a', 'a.id', '=', 'n.nwsc_account_id')
            ->where('a.property_id', $property->id)->where('n.status', 'successful')
            ->selectRaw('n.nwsc_account_id, SUM(n.amount) AS amount')->groupBy('n.nwsc_account_id')
            ->pluck('amount', 'nwsc_account_id');

        return [
            'property' => $property->only(['id', 'organization_id', 'name', 'property_code']),
            'period' => 'all_time',
            'summary_by_currency' => $summary,
            'tenants' => $tenants,
            'transactions' => $transactions,
            'nwsc_accounts' => $property->nwscAccounts->map(fn ($account) => array_merge(
                $account->only(['id', 'account_number', 'account_name', 'meter_number', 'phone', 'status']),
                ['successful_remitted_amount' => $this->decimal($remitted[$account->id] ?? 0)]
            ))->values(),
            'remittance' => [
                'basis' => 'current_water_wallet_balance',
                'currency' => $wallet?->currency,
                'wallet_status' => $wallet?->status,
                'wallet_balance' => $wallet?->balance,
                'destination_account_id' => $destination?->id,
                'amount_available_to_remit' => $wallet && $wallet->status === 'active' && $destination
                    ? $this->decimal(max(0, (float) $wallet->balance)) : null,
                'account_selection_required' => $activeAccounts->count() > 1,
                'missing_active_account' => $activeAccounts->isEmpty(),
                'missing_wallet' => $wallet === null,
            ],
        ];
    }

    private function formatTotals(object $row): array
    {
        return [
            'currency' => $row->currency,
            'transaction_count' => (int) $row->transaction_count,
            'successful_count' => (int) $row->successful_count,
            'amount_paid' => $this->decimal($row->amount_paid),
            'water_allocated' => $this->decimal($row->water_allocated),
            'units_delivered_m3' => $this->decimal($row->units_delivered_m3, 3),
            'missing_water_allocation_count' => (int) $row->missing_water_allocation_count,
        ];
    }

    private function decimal(mixed $value, int $places = 2): string
    {
        return number_format((float) $value, $places, '.', '');
    }
}
