<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StsTransaction;
use App\Models\User;

class ReconciliationService
{
    public function payments(
        User $user,
        array $filters = []
    ) {

        $query =
            Payment::query()
                ->with([
                    'organization:id,name',
                    'property:id,name',
                    'tenant:id,first_name,last_name',
                    'allocations',
                    'ledgerTransaction.entries',
                    'waterVending.tokens',
                ]);

        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'organization_id',
                $user->organization_id
            );

        }

        if (
            !empty(
                $filters['date_from']
            )
        ) {

            $query->whereDate(
                'initiated_at',
                '>=',
                $filters['date_from']
            );

        }

        if (
            !empty(
                $filters['date_to']
            )
        ) {

            $query->whereDate(
                'initiated_at',
                '<=',
                $filters['date_to']
            );

        }

        $payments =
            $query
                ->latest(
                    'initiated_at'
                )
                ->paginate(
                    min(
                        (int)
                        ($filters['per_page'] ?? 25),
                        100
                    )
                );

        $payments
            ->getCollection()
            ->transform(
                function (
                    Payment $payment
                ) {

                    $allocationTotal =
                        (float)
                        $payment
                            ->allocations
                            ->sum(
                                'amount'
                            );

                    $ledgerDebit =
                        (float)
                        optional(
                            $payment
                                ->ledgerTransaction
                        )
                            ?->entries
                            ?->sum(
                                'debit'
                            ) ?? 0;

                    $ledgerCredit =
                        (float)
                        optional(
                            $payment
                                ->ledgerTransaction
                        )
                            ?->entries
                            ?->sum(
                                'credit'
                            ) ?? 0;

                    $paymentAmount =
                        (float)
                        $payment->amount;

                    $issues = [];

                    if (
                        $payment->status ===
                            'successful' &&
                        round(
                            $allocationTotal,
                            2
                        ) !==
                        round(
                            $paymentAmount,
                            2
                        )
                    ) {

                        $issues[] =
                            'allocation_mismatch';

                    }

                    if (
                        $payment->status ===
                            'successful' &&
                        !$payment
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

                    $payment->setAttribute(
                        'reconciliation',
                        [
                            'payment_amount' =>
                                $paymentAmount,

                            'allocation_total' =>
                                $allocationTotal,

                            'ledger_debit' =>
                                $ledgerDebit,

                            'ledger_credit' =>
                                $ledgerCredit,

                            'balanced' =>
                                count(
                                    $issues
                                ) === 0,

                            'issues' =>
                                $issues,
                        ]
                    );

                    return $payment;
                }
            );

        return $payments;
    }

    public function sts(
        User $user,
        array $filters = []
    ) {

        $query =
            StsTransaction::query()
                ->with([
                    'meter:id,meter_number,organization_id',
                    'payment:id,reference,amount,status,organization_id',
                    'tokens',
                ]);

        if (
            !$user->isSuperAdmin()
        ) {

            $query->whereHas(
                'meter',
                fn ($q) =>
                    $q->where(
                        'organization_id',
                        $user->organization_id
                    )
            );

        }

        if (
            !empty(
                $filters['status']
            )
        ) {

            $query->where(
                'status',
                $filters['status']
            );

        }

        $transactions =
            $query
                ->latest()
                ->paginate(
                    min(
                        (int)
                        ($filters['per_page'] ?? 25),
                        100
                    )
                );

        $transactions
            ->getCollection()
            ->transform(
                function (
                    StsTransaction $transaction
                ) {

                    $issues = [];

                    if (
                        $transaction->status ===
                            'successful' &&
                        !$transaction->token
                    ) {

                        $issues[] =
                            'missing_sts_token';

                    }

                    if (
                        $transaction
                            ->transaction_type ===
                            'token_generation' &&
                        !$transaction
                            ->payment_id
                    ) {

                        $issues[] =
                            'missing_payment';

                    }

                    $transaction
                        ->setAttribute(
                            'reconciliation',
                            [
                                'balanced' =>
                                    count(
                                        $issues
                                    ) === 0,

                                'issues' =>
                                    $issues,
                            ]
                        );

                    return $transaction;
                }
            );

        return $transactions;
    }
}