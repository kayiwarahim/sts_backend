<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentAllocationService
{
    public function allocate(Payment $payment)
    {
        return DB::transaction(function () use ($payment) {

            $payment->load([
                'allocations',
                'property',
                'tenant',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate allocations
            |--------------------------------------------------------------------------
            */

            if ($payment->allocations->isNotEmpty()) {
                return $payment->allocations;
            }

            /*
            |--------------------------------------------------------------------------
            | Determine payment date
            |--------------------------------------------------------------------------
            */

            $paymentDate = $payment->initiated_at
                ? $payment->initiated_at->toDateString()
                : now()->toDateString();

            /*
            |--------------------------------------------------------------------------
            | Find configuration effective on payment date
            |--------------------------------------------------------------------------
            */

            $configuration = BillingConfiguration::query()
                ->where('property_id', $payment->property_id)
                ->where('status', 'active')
                ->whereDate('effective_from', '<=', $paymentDate)
                ->where(function ($query) use ($paymentDate) {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $paymentDate);
                })
                ->orderByDesc('effective_from')
                ->first();

            if (! $configuration) {
                throw new RuntimeException(
                    'No billing configuration was effective for this property on '.
                    $paymentDate
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate percentages
            |--------------------------------------------------------------------------
            */

            $this->validatePercentages($configuration);

            /*
            |--------------------------------------------------------------------------
            | Calculate allocations
            |--------------------------------------------------------------------------
            */

            $amount = round((float) $payment->amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException(
                    'Payment amount must be greater than zero.'
                );
            }

            $allocations = [
                'water' => $this->calculate(
                    $amount,
                    (float) $configuration->water_percentage
                ),

                'service_fee' => $this->calculate(
                    $amount,
                    (float) $configuration->service_fee_percentage
                ),

                'vat' => $this->calculate(
                    $amount,
                    (float) $configuration->vat_percentage
                ),

                'gateway_fee' => $this->calculate(
                    $amount,
                    (float) $configuration->gateway_fee_percentage
                ),

                'landlord' => $this->calculate(
                    $amount,
                    (float) $configuration->landlord_percentage
                ),

                'saas' => $this->calculate(
                    $amount,
                    (float) $configuration->saas_percentage
                ),
            ];

            /*
            |--------------------------------------------------------------------------
            | Handle rounding
            |--------------------------------------------------------------------------
            |
            | Any rounding difference is absorbed by the water allocation.
            |--------------------------------------------------------------------------
            */

            $difference = round(
                $amount - array_sum($allocations),
                2
            );

            if ($difference != 0.0) {
                $allocations['water'] = round(
                    $allocations['water'] + $difference,
                    2
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verify total BEFORE filtering zero allocations
            |--------------------------------------------------------------------------
            */

            if (
                round(array_sum($allocations), 2)
                !==
                round($amount, 2)
            ) {
                throw new RuntimeException(
                    'Payment allocations do not equal payment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create allocation records
            |--------------------------------------------------------------------------
            |
            | Zero-value allocations have no financial effect and therefore
            | should not create payment allocation or ledger records.
            |--------------------------------------------------------------------------
            */

            foreach ($allocations as $type => $allocationAmount) {

                $allocationAmount = round(
                    (float) $allocationAmount,
                    2
                );

                if ($allocationAmount <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'billing_configuration_id' => $configuration->id,
                    'allocation_type' => $type,
                    'percentage' => $this->percentageFor(
                        $configuration,
                        $type
                    ),
                    'amount' => $allocationAmount,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Reload created allocations
            |--------------------------------------------------------------------------
            */

            $payment = $payment
                ->fresh()
                ->load('allocations');

            /*
            |--------------------------------------------------------------------------
            | Final persisted allocation validation
            |--------------------------------------------------------------------------
            */

            $persistedTotal = round(
                (float) $payment->allocations->sum('amount'),
                2
            );

            if (
                $persistedTotal
                !==
                round($amount, 2)
            ) {
                throw new RuntimeException(
                    'Persisted payment allocations do not equal payment amount.'
                );
            }

            return $payment->allocations;
        });
    }

    protected function calculate(
        float $amount,
        float $percentage
    ): float {
        return round(
            ($amount * $percentage) / 100,
            2
        );
    }

    protected function validatePercentages(
        BillingConfiguration $configuration
    ): void {
        $total =
            (float) $configuration->water_percentage +
            (float) $configuration->service_fee_percentage +
            (float) $configuration->vat_percentage +
            (float) $configuration->gateway_fee_percentage +
            (float) $configuration->landlord_percentage +
            (float) $configuration->saas_percentage;

        if (round($total, 2) !== 100.00) {
            throw new RuntimeException(
                'Billing configuration percentages must equal 100%. '.
                "Current total: {$total}%."
            );
        }
    }

    protected function percentageFor(
        BillingConfiguration $configuration,
        string $type
    ): float {
        return match ($type) {
            'water' => (float) $configuration->water_percentage,

            'service_fee' => (float) $configuration->service_fee_percentage,

            'vat' => (float) $configuration->vat_percentage,

            'gateway_fee' => (float) $configuration->gateway_fee_percentage,

            'landlord' => (float) $configuration->landlord_percentage,

            'saas' => (float) $configuration->saas_percentage,

            default => throw new RuntimeException(
                "Unknown allocation type: {$type}"
            ),
        };
    }
}
