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

        if ($payment->status !== 'successful') {
            throw new RuntimeException(
                'Only successful payments can be posted to the ledger.'
            );
        }

        if ($payment->ledger_transaction_id) {
            return $payment->ledgerTransaction;
        }

        return DB::transaction(function () use (
            $payment,
            $createdBy
        ) {

            $payment->load([
                'organization',
                'property',
                'allocations',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate posting
            |--------------------------------------------------------------------------
            */

            $existingTransaction =
                $payment->ledgerTransaction();

            if ($existingTransaction) {
                return $existingTransaction;
            }

            /*
            |--------------------------------------------------------------------------
            | Validate allocations
            |--------------------------------------------------------------------------
            */

            if ($payment->allocations->isEmpty()) {
                throw new RuntimeException(
                    'Payment has no allocations.'
                );
            }

            $allocationTotal =
                $payment->allocations->sum(
                    'amount'
                );

            if (
                round(
                    (float) $allocationTotal,
                    2
                )
                !==
                round(
                    (float) $payment->amount,
                    2
                )
            ) {
                throw new RuntimeException(
                    'Payment allocations do not equal payment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find payment clearing account
            |--------------------------------------------------------------------------
            */

            $clearingAccount =
                $this->findAccount(
                    $payment->organization_id,
                    'PAYMENT_CLEARING'
                );

            /*
            |--------------------------------------------------------------------------
            | Build ledger entries
            |--------------------------------------------------------------------------
            */

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
            | Allocation accounts
            |--------------------------------------------------------------------------
            */

            foreach (
                $payment->allocations
                as $allocation
            ) {

                $accountCode =
                    $this->accountCodeForAllocation(
                        $allocation->allocation_type
                    );

                $account =
                    $this->findAccount(
                        $payment->organization_id,
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
            | Create balanced ledger transaction
            |--------------------------------------------------------------------------
            */

            $transaction =
                $this->ledgerService
                    ->createTransaction(
                        $payment->organization_id,
                        'payment',
                        $entries,
                        'Payment allocation for ' .
                        $payment->reference,
                        $createdBy
                    );

            /*
            |--------------------------------------------------------------------------
            | Attach transaction to payment
            |--------------------------------------------------------------------------
            */

            $payment->ledgerTransaction()
                ->associate($transaction);

            $payment->save();

            /*
            |--------------------------------------------------------------------------
            | Credit Water Wallet
            |--------------------------------------------------------------------------
            */

            $waterAllocation =
                $payment->allocations
                    ->firstWhere(
                        'allocation_type',
                        'water'
                    );

            if (
                $waterAllocation &&
                (float) $waterAllocation->amount > 0
            ) {

                $this->waterWalletService
                    ->credit(
                        $payment->property,
                        (float)
                        $waterAllocation->amount
                    );
            }

            return $transaction->load(
                'entries.account'
            );
        });
    }

    /**
     * Map payment allocation types
     * to ledger account codes.
     */
    protected function accountCodeForAllocation(
        string $allocationType
    ): string {

        return match ($allocationType) {

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

    /**
     * Find an organization ledger account.
     */
    protected function findAccount(
        ?int $organizationId,
        string $code
    ): LedgerAccount {

        $account = LedgerAccount::query()
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