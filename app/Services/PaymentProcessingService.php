<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentProcessingService
{
    public function __construct(
        protected PaymentAllocationService $allocationService,
        protected PaymentLedgerService $ledgerService
    ) {
    }

    /**
     * Process a successful payment.
     *
     * This is the main financial pipeline.
     */
    public function processSuccessfulPayment(
        Payment $payment,
        ?int $createdBy = null
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $createdBy
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock payment
            |--------------------------------------------------------------------------
            */

            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            /*
            |--------------------------------------------------------------------------
            | Validate status
            |--------------------------------------------------------------------------
            */

            if ($payment->status !== 'successful') {
                throw new RuntimeException(
                    'Only successful payments can be processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate processing
            |--------------------------------------------------------------------------
            |
            | If a ledger transaction already exists, the payment has already
            | passed through the financial processing pipeline.
            |
            */

            if ($payment->ledger_transaction_id) {
                return $payment
                    ->fresh()
                    ->load([
                        'allocations',
                        'ledgerTransaction.entries.account',
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 1. Create allocations
            |--------------------------------------------------------------------------
            */

            $this->allocationService->allocate($payment);

            /*
            |--------------------------------------------------------------------------
            | 2. Post to ledger
            |--------------------------------------------------------------------------
            */

            $this->ledgerService->postPayment(
                $payment->fresh(),
                $createdBy
            );

            /*
            |--------------------------------------------------------------------------
            | Return complete payment
            |--------------------------------------------------------------------------
            */

            return $payment
                ->fresh()
                ->load([
                    'allocations',
                    'ledgerTransaction.entries.account',
                ]);
        });
    }
}