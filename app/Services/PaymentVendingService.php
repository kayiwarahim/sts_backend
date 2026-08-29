<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StsTransaction;
use RuntimeException;

class PaymentVendingService
{
    public function __construct(
        protected StsService $stsService
    ) {}

    /**
     * Vend water for a successfully processed payment.
     */
    public function vend(
        Payment $payment
    ): StsTransaction {

        /*
        |--------------------------------------------------------------------------
        | Validate payment status
        |--------------------------------------------------------------------------
        */

        if ($payment->status !== 'successful') {
            throw new RuntimeException(
                'Only successful payments can be used for water vending.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment must already be financially processed
        |--------------------------------------------------------------------------
        */

        if (! $payment->ledger_transaction_id) {
            throw new RuntimeException(
                'Payment has not completed financial processing.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate successful vending
        |--------------------------------------------------------------------------
        */

        $existingTransaction =
            $payment->stsTransactions()
                ->where(
                    'transaction_type',
                    'token_generation'
                )
                ->where(
                    'status',
                    'successful'
                )
                ->first();

        if ($existingTransaction) {
            return $existingTransaction;
        }

        /*
        |--------------------------------------------------------------------------
        | Load tenant → tenancy → unit → meter
        |--------------------------------------------------------------------------
        */

        $payment->loadMissing([
            'tenant.activeTenancy.unit.activeMeterAssignment.meter',
        ]);

        $tenant = $payment->tenant;

        if (! $tenant) {
            throw new RuntimeException(
                'Payment does not have a tenant.'
            );
        }

        $tenancy = $tenant->activeTenancy;

        if (! $tenancy) {
            throw new RuntimeException(
                'Tenant does not have an active tenancy.'
            );
        }

        $unit = $tenancy->unit;

        if (! $unit) {
            throw new RuntimeException(
                'Tenant tenancy does not have a unit.'
            );
        }

        $assignment =
            $unit->activeMeterAssignment;

        if (! $assignment) {
            throw new RuntimeException(
                'Tenant unit does not have an active meter assignment.'
            );
        }

        $meter = $assignment->meter;

        if (! $meter) {
            throw new RuntimeException(
                'Meter assignment does not have a meter.'
            );
        }

        if ($meter->status !== 'active') {
            throw new RuntimeException(
                'The assigned meter is not active.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate STS vending token
        |--------------------------------------------------------------------------
        |
        | StsService::vendFromPayment() performs:
        |
        | Payment
        |   ↓
        | water allocation
        |   ↓
        | local water tariff
        |   ↓
        | quantity m³
        |   ↓
        | STS provider
        |
        */

        return $this->stsService
            ->vendFromPayment(
                $payment,
                $meter
            );
    }
}
