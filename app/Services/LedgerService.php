<?php

namespace App\Services;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LedgerService
{
    /**
     * Create a balanced double-entry transaction.
     *
     * @param  array  $entries
     *                          Example:
     *
     * [
     *     [
     *         'ledger_account_id' => 1,
     *         'debit' => 100000,
     *         'credit' => 0,
     *         'description' => 'Payment received',
     *     ],
     *     [
     *         'ledger_account_id' => 2,
     *         'debit' => 0,
     *         'credit' => 75000,
     *         'description' => 'Water allocation',
     *     ],
     * ]
     */
    public function createTransaction(
        ?int $organizationId,
        string $transactionType,
        array $entries,
        ?string $description = null,
        ?int $createdBy = null
    ): LedgerTransaction {

        if (empty($entries)) {
            throw new InvalidArgumentException(
                'A ledger transaction must contain at least one entry.'
            );
        }

        return DB::transaction(function () use (
            $organizationId,
            $transactionType,
            $entries,
            $description,
            $createdBy
        ) {

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($entries as $entry) {

                $debit = (float) (
                    $entry['debit'] ?? 0
                );

                $credit = (float) (
                    $entry['credit'] ?? 0
                );

                /*
                |--------------------------------------------------------------------------
                | An entry cannot have both debit and credit
                |--------------------------------------------------------------------------
                */

                if (
                    $debit > 0 &&
                    $credit > 0
                ) {
                    throw new InvalidArgumentException(
                        'A ledger entry cannot have both debit and credit.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | An entry must contain either debit or credit
                |--------------------------------------------------------------------------
                */

                if (
                    $debit == 0 &&
                    $credit == 0
                ) {
                    throw new InvalidArgumentException(
                        'A ledger entry must contain a debit or credit amount.'
                    );
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            /*
            |--------------------------------------------------------------------------
            | Double-entry accounting rule
            |--------------------------------------------------------------------------
            */

            if (
                round($totalDebit, 2)
                !==
                round($totalCredit, 2)
            ) {
                throw new InvalidArgumentException(
                    'Ledger transaction is not balanced. '.
                    "Debit: {$totalDebit}, ".
                    "Credit: {$totalCredit}."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create transaction
            |--------------------------------------------------------------------------
            */

            $transaction = LedgerTransaction::create([
                'organization_id' => $organizationId,

                'reference' => $this->generateReference(),

                'transaction_type' => $transactionType,

                'description' => $description,

                'transaction_date' => now(),

                'created_by' => $createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create entries
            |--------------------------------------------------------------------------
            */

            foreach ($entries as $entry) {

                $transaction->entries()->create([
                    'ledger_account_id' => $entry['ledger_account_id'],

                    'debit' => $entry['debit'] ?? 0,

                    'credit' => $entry['credit'] ?? 0,

                    'description' => $entry['description'] ?? null,
                ]);
            }

            return $transaction->load(
                'entries.account'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Generate unique reference
    |--------------------------------------------------------------------------
    */

    protected function generateReference(): string
    {
        do {
            $reference =
                'LT-'.
                now()->format('YmdHis').
                '-'.
                strtoupper(
                    Str::random(6)
                );

        } while (
            LedgerTransaction::where(
                'reference',
                $reference
            )->exists()
        );

        return $reference;
    }
}
