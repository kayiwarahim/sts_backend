<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReconciliationRecord;
use App\Models\StsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ReconciliationPersistenceService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Run all internal reconciliation.
     */
    public function run(
        User $user
    ): array {
        return DB::transaction(
            function () use ($user) {
                $paymentResults =
                    $this->reconcilePayments(
                        $user
                    );

                $stsResults =
                    $this->reconcileSts(
                        $user
                    );

                return [
                    'payments' => $paymentResults,

                    'sts' => $stsResults,

                    'total' => $paymentResults['total'] +
                        $stsResults['total'],
                ];
            }
        );
    }

    /**
     * Reconcile payment amount against allocations and ledger.
     */
    public function reconcilePayments(
        User $user
    ): array {
        $query =
            Payment::query()
                ->where(
                    'status',
                    'successful'
                )
                ->with([
                    'allocations',
                    'ledgerTransaction.entries',
                ]);

        if (
            ! $user->isSuperAdmin()
        ) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        $payments =
            $query->get();

        $matched = 0;
        $partial = 0;
        $unmatched = 0;

        foreach (
            $payments as $payment
        ) {
            $expected =
                (float) $payment->amount;

            $allocationTotal =
                (float)
                $payment
                    ->allocations
                    ->sum(
                        'amount'
                    );

            $ledgerDebit =
                (float)
                (
                    $payment
                        ->ledgerTransaction
                        ?->entries
                        ?->sum('debit')
                    ?? 0
                );

            $ledgerCredit =
                (float)
                (
                    $payment
                        ->ledgerTransaction
                        ?->entries
                        ?->sum('credit')
                    ?? 0
                );

            $issues = [];

            if (
                round(
                    $expected,
                    2
                ) !==
                round(
                    $allocationTotal,
                    2
                )
            ) {
                $issues[] =
                    'allocation_mismatch';
            }

            if (
                ! $payment
                    ->ledger_transaction_id
            ) {
                $issues[] =
                    'missing_ledger_transaction';
            }

            if (
                $payment
                    ->ledger_transaction_id &&
                round(
                    $ledgerDebit,
                    2
                ) !==
                round(
                    $ledgerCredit,
                    2
                )
            ) {
                $issues[] =
                    'unbalanced_ledger';
            }

            if (
                empty($issues)
            ) {
                $status =
                    'matched';

                $matched++;
            } elseif (
                $allocationTotal > 0
            ) {
                $status =
                    'partial';

                $partial++;
            } else {
                $status =
                    'unmatched';

                $unmatched++;
            }

            $record =
                ReconciliationRecord::updateOrCreate(
                    [
                        'provider' => 'internal_payment',

                        'internal_reference' => 'PAYMENT:'.
                            $payment->id,
                    ],
                    [
                        'organization_id' => $payment
                            ->organization_id,

                        'reconciliation_type' => 'payment',

                        'provider_reference' => $payment
                            ->reference,

                        'transaction_date' => $payment
                            ->completed_at
                            ?? $payment
                                ->initiated_at
                            ?? $payment
                                ->created_at,

                        'expected_amount' => $expected,

                        'actual_amount' => $allocationTotal,

                        'difference' => round(
                            $allocationTotal -
                            $expected,
                            2
                        ),

                        'status' => $status,

                        'external_data' => [
                        'payment_id' => $payment->id,

                        'payment_status' => $payment->status,

                        'ledger_transaction_id' => $payment
                            ->ledger_transaction_id,

                        'ledger_debit' => $ledgerDebit,

                        'ledger_credit' => $ledgerCredit,

                        'allocation_total' => $allocationTotal,

                        'issues' => $issues,
                        ],
                    ]
                );

            $this->notifyIfDiscrepancy(
                $record,
                'Payment'
            );
        }

        return [
            'total' => $payments->count(),

            'matched' => $matched,

            'partial' => $partial,

            'unmatched' => $unmatched,
        ];
    }

    /**
     * Reconcile STS water vending.
     */
    public function reconcileSts(
        User $user
    ): array {
        $query =
            StsTransaction::query()
                ->where(
                    'transaction_type',
                    'token_generation'
                )
                ->with([
                    'payment.allocations',
                    'meter',
                ]);

        if (
            ! $user->isSuperAdmin()
        ) {
            $query->whereHas(
                'meter',
                function ($query) use ($user) {
                    $query->where(
                        'organization_id',
                        $user->organization_id
                    );
                }
            );
        }

        $transactions =
            $query->get();

        $matched = 0;
        $partial = 0;
        $unmatched = 0;

        foreach (
            $transactions as $transaction
        ) {
            $payment =
                $transaction->payment;

            $issues = [];

            if (! $payment) {
                $expected = 0;

                $issues[] =
                    'missing_payment';
            } else {
                $waterAllocation =
                    $payment
                        ->allocations
                        ->firstWhere(
                            'allocation_type',
                            'water'
                        );

                $expected =
                    (float) (
                        $waterAllocation
                            ?->amount
                        ?? 0
                    );

                if (! $waterAllocation) {
                    $issues[] =
                        'missing_water_allocation';
                }
            }

            $actual =
                (float) (
                    $transaction->amount
                    ?? 0
                );

            if (
                round(
                    $expected,
                    2
                ) !==
                round(
                    $actual,
                    2
                )
            ) {
                $issues[] =
                    'sts_amount_mismatch';
            }

            if (
                $transaction->status ===
                    'successful' &&
                blank(
                    $transaction->token
                )
            ) {
                $issues[] =
                    'missing_token';
            }

            if (
                $transaction->status ===
                'failed'
            ) {
                $issues[] =
                    'sts_failed';
            }

            if (
                empty($issues)
            ) {
                $status =
                    'matched';

                $matched++;
            } elseif (
                $actual > 0
            ) {
                $status =
                    'partial';

                $partial++;
            } else {
                $status =
                    'unmatched';

                $unmatched++;
            }

            $record =
                ReconciliationRecord::updateOrCreate(
                    [
                        'provider' => 'sts',

                        'internal_reference' => 'STS:'.
                            $transaction->id,
                    ],
                    [
                        'organization_id' => $transaction
                            ->meter
                            ?->organization_id,

                        'reconciliation_type' => 'sts',

                        'provider_reference' => $transaction
                            ->reference,

                        'transaction_date' => $transaction
                            ->completed_at
                            ?? $transaction
                                ->created_at,

                        'expected_amount' => $expected,

                        'actual_amount' => $actual,

                        'difference' => round(
                            $actual -
                            $expected,
                            2
                        ),

                        'status' => $status,

                        'external_data' => [
                        'sts_transaction_id' => $transaction->id,

                        'payment_id' => $transaction
                            ->payment_id,

                        'meter_id' => $transaction
                            ->meter_id,

                        'meter_number' => $transaction
                            ->meter
                            ?->meter_number,

                        'sts_status' => $transaction
                            ->status,

                        'volume_m3' => $transaction
                            ->volume_m3,

                        'token' => $transaction
                            ->token,

                        'issues' => $issues,
                        ],
                    ]
                );

            $this->notifyIfDiscrepancy(
                $record,
                'STS'
            );
        }

        return [
            'total' => $transactions->count(),

            'matched' => $matched,

            'partial' => $partial,

            'unmatched' => $unmatched,
        ];
    }

    /**
     * Resolve reconciliation record manually.
     */
    public function resolve(
        User $user,
        ReconciliationRecord $record,
        ?string $notes = null
    ): ReconciliationRecord {
        if (
            ! $user->isSuperAdmin() &&
            $record->organization_id !==
            $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized reconciliation access.'
            );
        }

        if (
            $record->status ===
            'matched'
        ) {
            throw new RuntimeException(
                'Matched reconciliation records do not require resolution.'
            );
        }

        $record->update([
            'status' => 'resolved',

            'resolved_by' => $user->id,

            'resolved_at' => now(),

            'notes' => $notes,
        ]);

        return $record->fresh([
            'organization',
            'resolvedBy',
        ]);
    }

    /**
     * Send discrepancy notification without breaking reconciliation.
     */
    protected function notifyIfDiscrepancy(
        ReconciliationRecord $record,
        string $source
    ): void {
        if (
            ! in_array(
                $record->status,
                [
                    'partial',
                    'unmatched',
                ],
                true
            )
        ) {
            return;
        }

        try {
            $this
                ->notificationService
                ->reconciliationIssue(
                    $record
                );
        } catch (Throwable $e) {
            Log::warning(
                $source.
                ' reconciliation notification failed.',
                [
                    'reconciliation_record_id' => $record->id,

                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
