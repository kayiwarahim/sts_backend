<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentProcessingService
{
    public function __construct(
        protected PaymentAllocationService $allocationService,
        protected PaymentLedgerService $ledgerService,
        protected PaymentVendingService $vendingService
    ) {
    }

    /**
     * Process successful payment.
     *
     * Phase 1:
     * Financial processing
     *
     * Phase 2:
     * STS water vending
     */
    public function processSuccessfulPayment(
        Payment $payment,
        ?int $createdBy = null
    ): Payment {

        /*
        |--------------------------------------------------------------------------
        | PHASE 1 — FINANCIAL PROCESSING
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $payment,
            $createdBy
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock payment
            |--------------------------------------------------------------------------
            */

            $lockedPayment =
                Payment::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $payment->id
                    );

            /*
            |--------------------------------------------------------------------------
            | Payment must be successful
            |--------------------------------------------------------------------------
            */

            if (
                $lockedPayment->status
                !==
                'successful'
            ) {
                throw new RuntimeException(
                    'Only successful payments can be processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Financial processing is idempotent
            |--------------------------------------------------------------------------
            */

            if (
                !$lockedPayment
                    ->ledger_transaction_id
            ) {

                /*
                |--------------------------------------------------------------------------
                | 1. Create payment allocations
                |--------------------------------------------------------------------------
                */

                $this->allocationService
                    ->allocate(
                        $lockedPayment
                    );

                /*
                |--------------------------------------------------------------------------
                | 2. Ledger + water wallet
                |--------------------------------------------------------------------------
                */

                $this->ledgerService
                    ->postPayment(
                        $lockedPayment->fresh(),
                        $createdBy
                    );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Reload after DB commit
        |--------------------------------------------------------------------------
        */

        $payment =
            Payment::findOrFail(
                $payment->id
            );

        /*
        |--------------------------------------------------------------------------
        | PHASE 2 — STS VENDING
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | We do this OUTSIDE the financial DB transaction.
        |
        | STS is an external HTTP service and may timeout/fail.
        |
        | We don't want to rollback a successful payment simply because the
        | STS provider temporarily failed.
        |
        */

        if (
            !$payment
                ->stsTransactions()
                ->where(
                    'transaction_type',
                    'token_generation'
                )
                ->where(
                    'status',
                    'successful'
                )
                ->exists()
        ) {
            $this->vendingService
                ->vend($payment);
        }

        /*
        |--------------------------------------------------------------------------
        | Return complete processed payment
        |--------------------------------------------------------------------------
        */

        return $payment
            ->fresh()
            ->load([
                'allocations',

                'ledgerTransaction.entries.account',

                'stsTransactions.tokens',

                'waterVending.waterTariff',

                'waterVending.tokens',

                'tenant.activeTenancy.unit.activeMeterAssignment.meter',
            ]);
    }
}