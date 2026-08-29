<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentProcessingService
{
    public function __construct(
        protected PaymentAllocationService $allocationService,
        protected PaymentLedgerService $ledgerService,
        protected PaymentVendingService $vendingService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Fully process a successful payment.
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

        DB::transaction(
            function () use (
                $payment,
                $createdBy
            ) {
                $lockedPayment =
                    Payment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->id
                        );

                if (
                    $lockedPayment->status !==
                    'successful'
                ) {
                    throw new RuntimeException(
                        'Only successful payments can be processed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Idempotency
                |--------------------------------------------------------------------------
                |
                | Once ledger_transaction_id exists, financial processing
                | for this payment has already completed.
                |--------------------------------------------------------------------------
                */

                if (
                    ! $lockedPayment
                        ->ledger_transaction_id
                ) {
                    $this
                        ->allocationService
                        ->allocate(
                            $lockedPayment
                        );

                    $this
                        ->ledgerService
                        ->postPayment(
                            $lockedPayment->fresh(),
                            $createdBy
                        );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Reload committed payment
        |--------------------------------------------------------------------------
        */

        $payment =
            Payment::findOrFail(
                $payment->id
            );

        /*
        |--------------------------------------------------------------------------
        | PAYMENT SUCCESS NOTIFICATION
        |--------------------------------------------------------------------------
        */

        try {
            $this
                ->notificationService
                ->paymentSuccessful(
                    $payment
                );
        } catch (Throwable $e) {
            Log::warning(
                'Payment success notification failed.',
                [
                    'payment_id' => $payment->id,

                    'reference' => $payment->reference,

                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PHASE 2 — STS VENDING
        |--------------------------------------------------------------------------
        */

        $successfulSts =
            $payment
                ->stsTransactions()
                ->where(
                    'transaction_type',
                    'token_generation'
                )
                ->where(
                    'status',
                    'successful'
                )
                ->first();

        if (! $successfulSts) {
            try {
                $successfulSts =
                    $this
                        ->vendingService
                        ->vend(
                            $payment
                        );
            } catch (Throwable $e) {
                /*
                |--------------------------------------------------------------------------
                | Payment already succeeded.
                |
                | STS failure must never rollback payment allocation,
                | ledger posting or water wallet credit.
                |--------------------------------------------------------------------------
                */

                try {
                    $this
                        ->notificationService
                        ->stsVendingFailed(
                            $payment,
                            $e->getMessage()
                        );
                } catch (Throwable $notificationError) {
                    Log::warning(
                        'STS failure notification failed.',
                        [
                            'payment_id' => $payment->id,

                            'error' => $notificationError
                                ->getMessage(),
                        ]
                    );
                }

                Log::error(
                    'STS vending failed after successful payment.',
                    [
                        'payment_id' => $payment->id,

                        'reference' => $payment->reference,

                        'error' => $e->getMessage(),
                    ]
                );

                throw $e;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TOKEN NOTIFICATION
        |--------------------------------------------------------------------------
        */

        if ($successfulSts) {
            try {
                $this
                    ->notificationService
                    ->stsTokenGenerated(
                        $payment,
                        $successfulSts
                    );
            } catch (Throwable $e) {
                Log::warning(
                    'STS token notification failed.',
                    [
                        'payment_id' => $payment->id,

                        'sts_transaction_id' => $successfulSts->id,

                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Return complete payment
        |--------------------------------------------------------------------------
        */

        return Payment::query()
            ->with([
                'allocations',

                'ledgerTransaction.entries.account',

                'stsTransactions.tokens',

                'waterVending.waterTariff',

                'waterVending.tokens',

                'tenant.activeTenancy.unit.activeMeterAssignment.meter',
            ])
            ->findOrFail(
                $payment->id
            );
    }
}
