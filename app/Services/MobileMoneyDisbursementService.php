<?php

namespace App\Services;

use App\Jobs\SendMobileMoneyDisbursement;
use App\Models\{AuditLog, LedgerAccount, MobileMoneyDisbursement, NwscAccount, NwscPayment, Property, User, WaterWallet, WaterWalletTransaction, Payment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MobileMoneyDisbursementService
{
    public function __construct(private DisbursementFeeService $fees, private LedgerService $ledger) {}

    public function initiate(Property $property, User $user, array $data): MobileMoneyDisbursement
    {
        // Fail configuration checks before reserving funds; this does not make an HTTP request.
        app(RelworxService::class);
        $fee = $this->fees->fee((int) $data['amount']);
        return DB::transaction(function () use ($property, $user, $data, $fee) {
            $wallet = WaterWallet::where('property_id', $property->id)->lockForUpdate()->first();
            if (! $wallet) {
                $this->invalid('amount', 'This property has no water wallet.');
            }
            if ($property->status !== 'active' || $wallet->status !== 'active' || $wallet->currency !== 'UGX') {
                $this->invalid('amount', 'An active property and active UGX water wallet are required.');
            }
            $account = NwscAccount::where('property_id', $property->id)->where('status', 'active')->find($data['nwsc_account_id']);
            if (! $account) {
                $this->invalid('nwsc_account_id', 'Select an active NWSC account belonging to this property.');
            }
            $total = (int) $data['amount'] + $fee;
            if (bccomp($wallet->balance, (string) $total, 2) < 0) {
                $this->invalid('amount', 'Insufficient water wallet balance for the amount plus gateway fee.');
            }
            $accounts = $this->accounts($property);
            $reference = $this->generateReference();
            $record = MobileMoneyDisbursement::create([
                'property_id' => $property->id, 'water_wallet_id' => $wallet->id, 'nwsc_account_id' => $account->id,
                'initiated_by' => $user->id, 'reference' => $reference, 'msisdn' => $data['msisdn'],
                'currency' => 'UGX', 'amount' => $data['amount'], 'gateway_fee' => $fee, 'total_debit' => $total,
                'description' => $data['description'] ?? 'NWSC water remittance', 'status' => 'pending',
            ]);
            $journal = $this->ledger->createTransaction($property->organization_id, 'disbursement_reservation', [
                $this->entry($accounts['water'], $total, 0, 'Reserve water funds including gateway fee'),
                $this->entry($accounts['pending'], 0, $total, 'Disbursement funds awaiting provider confirmation'),
            ], $record->reference, $user->id);
            $record->update(['reservation_ledger_id' => $journal->id]);
            $this->walletEntry($wallet, $record, 'debit', $total, 'RESERVE', 'Reserve recipient amount and fixed disbursement fee');
            $this->audit($record, 'disbursement_requested');
            SendMobileMoneyDisbursement::dispatch($record->id)->afterCommit();
            return $record->fresh();
        });
    }

    public function send(int $id): void
    {
        // Persist the claim BEFORE the external request. Re-delivery never sends twice.
        $record = DB::transaction(function () use ($id) {
            $record = MobileMoneyDisbursement::lockForUpdate()->findOrFail($id);
            if ($record->status !== 'pending') { return null; }
            $record->update(['status' => 'submitting']);
            return $record;
        });
        if (! $record) { return; }
        try {
            $result = app(RelworxService::class)->sendPayment($record->reference, $record->msisdn, (int) $record->amount, $record->description);
            DB::transaction(function () use ($record, $result) {
                $locked = MobileMoneyDisbursement::lockForUpdate()->findOrFail($record->id);
                if ($locked->status === 'submitting') {
                    $locked->update(['status' => 'processing', 'provider_reference' => $result['internal_reference'], 'provider_response' => $result]);
                }
            });
        } catch (Throwable $e) {
            // Timeout or malformed response is ambiguous, so retain the reservation.
            MobileMoneyDisbursement::whereKey($id)->where('status', 'submitting')
                ->update(['status' => 'unknown', 'error_message' => 'Submission outcome unknown; refresh provider status before any new payout.']);
            report($e);
        }
    }

    public function refresh(MobileMoneyDisbursement $record): MobileMoneyDisbursement
    {
        $record->refresh();
        if (in_array($record->status, ['successful', 'failed', 'pending'], true)) { return $record; }
        $result = app(RelworxService::class)->checkRequestStatus($record->provider_reference ?? $record->reference);
        return $this->applyResult($record, $result);
    }

    public function reconcileFor(User $user): array
    {
        $totals = ['checked' => 0, 'successful' => 0, 'failed' => 0, 'pending' => 0, 'needs_review' => 0, 'errors' => 0];
        $query = MobileMoneyDisbursement::whereIn('status', ['submitting', 'processing', 'unknown', 'needs_review']);
        if (! $user->isSuperAdmin()) {
            $query->whereHas('property', fn ($q) => $q->where('organization_id', $user->organization_id));
        }
        $query->chunkById(100, function ($records) use (&$totals) {
            foreach ($records as $record) {
                $totals['checked']++;
                try {
                    $record = $this->refresh($record);
                    $key = in_array($record->status, ['successful', 'failed', 'needs_review'], true) ? $record->status : 'pending';
                    $totals[$key]++;
                } catch (Throwable $e) {
                    $totals['errors']++;
                    report($e);
                }
            }
        });
        return $totals;
    }

    public function applyResult(MobileMoneyDisbursement $record, array $result): MobileMoneyDisbursement
    {
        return DB::transaction(function () use ($record, $result) {
            $record = MobileMoneyDisbursement::lockForUpdate()->findOrFail($record->id);
            if (in_array($record->status, ['successful', 'failed'], true)) { return $record; }
            if (($result['customer_reference'] ?? null) !== $record->reference || empty($result['internal_reference']) ||
                ($record->provider_reference && $record->provider_reference !== $result['internal_reference'])) {
                throw new RuntimeException('Disbursement provider references do not match.');
            }
            foreach (['currency' => 'UGX', 'msisdn' => $record->msisdn] as $field => $value) {
                if (($result[$field] ?? null) !== $value) { throw new RuntimeException('Disbursement '.$field.' does not match.'); }
            }
            if (! isset($result['amount']) || ! is_numeric($result['amount']) || bccomp((string) $result['amount'], $record->amount, 2) !== 0) {
                throw new RuntimeException('Disbursement amount does not match.');
            }
            $status = strtolower((string) ($result['request_status'] ?? $result['status'] ?? ''));
            $record->fill(['provider_reference' => $result['internal_reference'], 'provider_response' => $result,
                'provider_transaction_id' => $result['provider_transaction_id'] ?? $record->provider_transaction_id]);
            if (! in_array($status, ['success', 'successful', 'failed'], true)) {
                $record->status = 'processing'; $record->save(); return $record;
            }
            if ($status !== 'failed' && isset($result['charge']) &&
                (! is_numeric($result['charge']) || bccomp((string) $result['charge'], $record->gateway_fee, 2) !== 0)) {
                $record->fill(['status' => 'needs_review', 'error_message' => 'Provider fee differs from the configured band. Reservation retained for reconciliation.']);
                if (is_numeric($result['charge'])) { $record->provider_charge = $result['charge']; }
                $record->save(); return $record;
            }
            $wallet = WaterWallet::lockForUpdate()->findOrFail($record->water_wallet_id);
            $property = $record->property;
            $accounts = $this->accounts($property);
            $total = (int) $record->total_debit;
            if ($status === 'failed') {
                // Only a confirmed zero-charge failure can release the entire reservation.
                if (isset($result['charge']) && (! is_numeric($result['charge']) || bccomp((string) $result['charge'], '0', 2) !== 0)) {
                    $record->fill(['status' => 'needs_review', 'error_message' => 'Failed payout has a provider charge; reservation retained.'])->save();
                    return $record;
                }
                $entries = [$this->entry($accounts['pending'], $total, 0, 'Release failed payout reservation'),
                    $this->entry($accounts['water'], 0, $total, 'Restore water payable')];
                $this->walletEntry($wallet, $record, 'credit', $total, 'RELEASE', 'Restore failed disbursement amount and fee');
                $record->status = 'failed';
                $record->error_message = $result['message'] ?? 'Provider confirmed failure.';
            } else {
                $entries = [$this->entry($accounts['pending'], (int) $record->amount, 0, 'Recipient amount sent'),
                    $this->entry($accounts['pending'], (int) $record->gateway_fee, 0, 'Fixed disbursement gateway charge'),
                    $this->entry($accounts['clearing'], 0, $total, 'Provider cash outflow including fee')];
                $nwsc = NwscPayment::create([
                    'nwsc_account_id' => $record->nwsc_account_id, 'water_wallet_id' => $wallet->id,
                    'reference' => $record->reference, 'amount' => $record->amount, 'payment_method' => 'mobile_money',
                    'status' => 'successful', 'initiated_by' => $record->initiated_by,
                    'provider_reference' => $result['internal_reference'], 'paid_at' => now(),
                    'request_data' => ['msisdn' => $record->msisdn, 'gateway_fee' => $record->gateway_fee], 'response_data' => $result,
                ]);
                $record->nwsc_payment_id = $nwsc->id;
                WaterWalletTransaction::where('water_wallet_id', $wallet->id)->where('reference', 'DISB-RESERVE-'.$record->id)
                    ->update(['nwsc_payment_id' => $nwsc->id]);
                $record->status = 'successful'; $record->error_message = null;
            }
            $journal = $this->ledger->createTransaction($property->organization_id, 'disbursement_'.$record->status,
                $entries, $record->reference, $record->initiated_by);
            $record->completion_ledger_id = $journal->id;
            $record->completed_at = now();
            $record->provider_charge = $result['charge'] ?? null;
            $record->save();
            $this->audit($record, 'disbursement_'.$record->status);
            return $record->fresh();
        });
    }

    private function accounts(Property $property): array
    {
        $found = [];
        foreach (['water' => 'WATER_PAYABLE', 'clearing' => 'PAYMENT_CLEARING'] as $key => $code) {
            $found[$key] = LedgerAccount::where('organization_id', $property->organization_id)
                ->where('code', $code)->where('currency', 'UGX')->where('is_active', true)->first();
            if (! $found[$key]) { $this->invalid('amount', 'Active UGX ledger account '.$code.' is required.'); }
            if ($found[$key]->type !== ($key === 'water' ? 'liability' : 'asset')) {
                $this->invalid('amount', 'Ledger account '.$code.' has an incompatible account type.');
            }
        }
        $found['pending'] = LedgerAccount::firstOrCreate(['organization_id' => $property->organization_id, 'code' => 'DISBURSEMENT_PENDING'],
            ['name' => 'Reserved mobile money disbursements', 'type' => 'liability', 'currency' => 'UGX', 'is_active' => true]);
        if (! $found['pending']->is_active || $found['pending']->currency !== 'UGX' || $found['pending']->type !== 'liability') {
            $this->invalid('amount', 'The disbursement pending ledger account must be active and denominated in UGX.');
        }
        return $found;
    }

    private function entry(LedgerAccount $account, int $debit, int $credit, string $description): array
    {
        return ['ledger_account_id' => $account->id, 'debit' => $debit, 'credit' => $credit, 'description' => $description];
    }

    private function walletEntry(WaterWallet $wallet, MobileMoneyDisbursement $record, string $type, int $amount, string $suffix, string $description): void
    {
        $before = $wallet->balance;
        $after = $type === 'debit' ? bcsub($before, (string) $amount, 2) : bcadd($before, (string) $amount, 2);
        $wallet->update(['balance' => $after]);
        WaterWalletTransaction::create(['water_wallet_id' => $wallet->id, 'type' => $type, 'amount' => $amount,
            'balance_before' => $before, 'balance_after' => $after, 'reference' => 'DISB-'.$suffix.'-'.$record->id, 'description' => $description]);
    }

    private function audit(MobileMoneyDisbursement $record, string $action): void
    {
        AuditLog::create(['user_id' => $record->initiated_by, 'organization_id' => $record->property->organization_id,
            'action' => $action, 'auditable_type' => MobileMoneyDisbursement::class, 'auditable_id' => $record->id,
            'new_values' => $record->only(['reference', 'amount', 'gateway_fee', 'total_debit', 'status']), 'description' => $action.' '.$record->reference]);
    }

    private function generateReference(): string
    {
        do {
            $reference = 'DISB-'.now()->format('YmdHis').'-'.strtoupper(Str::random(8));
        } while (
            MobileMoneyDisbursement::where('reference', $reference)->exists()
            || Payment::where('reference', $reference)->exists()
        );

        return $reference;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
