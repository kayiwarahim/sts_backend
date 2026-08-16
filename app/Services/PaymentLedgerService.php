<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentLedgerService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected WaterWalletService $waterWalletService
    ) {
    }

    /**
     * Post a successful payment to the ledger.
     */
    public function postPayment(
        Payment $payment,
        ?int $createdBy = null
    ) {
        /*
        |--------------------------------------------------------------------------
        | Validate payment status
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status !==
            'successful'
        ) {
            throw new RuntimeException(
                'Only successful payments can be posted to the ledger.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate posting
        |--------------------------------------------------------------------------
        */

        if (
            $payment
                ->ledger_transaction_id
        ) {
            return $payment
                ->ledgerTransaction()
                ->first();
        }

        return DB::transaction(
            function () use (
                $payment,
                $createdBy
            ) {
                /*
                |--------------------------------------------------------------------------
                | Reload and lock payment
                |--------------------------------------------------------------------------
                */

                $payment =
                    Payment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->id
                        );

                /*
                |--------------------------------------------------------------------------
                | Double-check duplicate posting
                |--------------------------------------------------------------------------
                */

                if (
                    $payment
                        ->ledger_transaction_id
                ) {
                    return $payment
                        ->ledgerTransaction()
                        ->first();
                }

                /*
                |--------------------------------------------------------------------------
                | Load relationships
                |--------------------------------------------------------------------------
                */

                $payment->load([
                    'organization',
                    'property',
                    'allocations',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Validate allocations
                |--------------------------------------------------------------------------
                */

                if (
                    $payment
                        ->allocations
                        ->isEmpty()
                ) {
                    throw new RuntimeException(
                        'Payment has no allocations.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validate allocation total
                |--------------------------------------------------------------------------
                */

                $allocationTotal =
                    $payment
                        ->allocations
                        ->sum(
                            'amount'
                        );

                if (
                    round(
                        (float)
                        $allocationTotal,
                        2
                    )
                    !==
                    round(
                        (float)
                        $payment->amount,
                        2
                    )
                ) {
                    throw new RuntimeException(
                        'Payment allocations do not equal payment amount.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Clearing account
                |--------------------------------------------------------------------------
                */

                $clearingAccount =
                    $this->findAccount(
                        $payment
                            ->organization_id,
                        'PAYMENT_CLEARING'
                    );

                $entries = [];

                /*
                |--------------------------------------------------------------------------
                | Debit payment clearing
                |--------------------------------------------------------------------------
                */

                $entries[] = [
                    'ledger_account_id' =>
                        $clearingAccount->id,

                    'debit' =>
                        $payment->amount,

                    'credit' =>
                        0,

                    'description' =>
                        'Payment received: ' .
                        $payment->reference,
                ];

                /*
                |--------------------------------------------------------------------------
                | Credit allocations
                |--------------------------------------------------------------------------
                */

                foreach (
                    $payment->allocations
                    as $allocation
                ) {
                    $accountCode =
                        $this
                            ->accountCodeForAllocation(
                                $allocation
                                    ->allocation_type
                            );

                    $account =
                        $this->findAccount(
                            $payment
                                ->organization_id,
                            $accountCode
                        );

                    $entries[] = [
                        'ledger_account_id' =>
                            $account->id,

                        'debit' =>
                            0,

                        'credit' =>
                            $allocation->amount,

                        'description' =>
                            ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $allocation
                                        ->allocation_type
                                )
                            ) .
                            ' allocation for payment ' .
                            $payment->reference,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Validate balance
                |--------------------------------------------------------------------------
                */

                $totalDebit =
                    collect(
                        $entries
                    )->sum(
                        'debit'
                    );

                $totalCredit =
                    collect(
                        $entries
                    )->sum(
                        'credit'
                    );

                if (
                    round(
                        (float)
                        $totalDebit,
                        2
                    )
                    !==
                    round(
                        (float)
                        $totalCredit,
                        2
                    )
                ) {
                    throw new RuntimeException(
                        'Ledger transaction is not balanced.' .
                        'Debit: ' .
                        $totalDebit .
                        ', Credit: ' .
                        $totalCredit
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create ledger transaction
                |--------------------------------------------------------------------------
                */

                $transaction =
                    $this
                        ->ledgerService
                        ->createTransaction(
                            $payment
                                ->organization_id,

                            'payment',

                            $entries,

                            'Payment allocation for ' .
                            $payment->reference,

                            $createdBy
                        );

                /*
                |--------------------------------------------------------------------------
                | Attach ledger transaction to payment
                |--------------------------------------------------------------------------
                */

                $payment
                    ->ledgerTransaction()
                    ->associate(
                        $transaction
                    );

                $payment->save();

                /*
                |--------------------------------------------------------------------------
                | Credit water wallet
                |--------------------------------------------------------------------------
                */

                $waterAllocation =
                    $payment
                        ->allocations
                        ->firstWhere(
                            'allocation_type',
                            'water'
                        );

                if (
                    $waterAllocation &&
                    (float)
                    $waterAllocation->amount >
                    0
                ) {
                    $this
                        ->waterWalletService
                        ->credit(
                            $payment
                                ->property,

                            (float)
                            $waterAllocation
                                ->amount,

                            $payment,

                            'Water allocation from payment ' .
                            $payment
                                ->reference
                        );
                }

                return $transaction
                    ->load(
                        'entries.account'
                    );
            }
        );
    }

    protected function accountCodeForAllocation(
        string $allocationType
    ): string {
        return match (
            $allocationType
        ) {
            'water' =>
                'WATER_PAYABLE',

            'service_fee' =>
                'SERVICE_REVENUE',

            'vat' =>
                'VAT_PAYABLE',

            'gateway_fee' =>
                'GATEWAY_PAYABLE',

            'landlord' =>
                'LANDLORD_PAYABLE',

            'saas' =>
                'SAAS_REVENUE',

            default =>
                throw new RuntimeException(
                    'Unknown allocation type: ' .
                    $allocationType
                ),
        };
    }

    protected function findAccount(
        ?int $organizationId,
        string $code
    ): LedgerAccount {
        $account =
            LedgerAccount::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'code',
                    $code
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (!$account) {
            throw new RuntimeException(
                "Ledger account [{$code}] " .
                'does not exist for this organization.'
            );
        }

        return $account;
    }
}