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
                ->where(
                    'property_id',
                    $payment->property_id
                )
                ->where(
                    'status',
                    'active'
                )
                ->whereDate(
                    'effective_from',
                    '<=',
                    $paymentDate
                )
                ->where(function ($query) use ($paymentDate) {

                    $query->whereNull('effective_to')
                        ->orWhereDate(
                            'effective_to',
                            '>=',
                            $paymentDate
                        );
                })
                ->orderByDesc('effective_from')
                ->first();

            if (!$configuration) {
                throw new RuntimeException(
                    'No billing configuration was effective for this property on ' .
                    $paymentDate
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate percentages
            |--------------------------------------------------------------------------
            */

            $this->validatePercentages(
                $configuration
            );

            /*
            |--------------------------------------------------------------------------
            | Calculate allocations
            |--------------------------------------------------------------------------
            */

            $amount = (float) $payment->amount;

            $allocations = [
                'water' => $this->calculate(
                    $amount,
                    $configuration->water_percentage
                ),

                'service_fee' => $this->calculate(
                    $amount,
                    $configuration->service_fee_percentage
                ),

                'vat' => $this->calculate(
                    $amount,
                    $configuration->vat_percentage
                ),

                'gateway_fee' => $this->calculate(
                    $amount,
                    $configuration->gateway_fee_percentage
                ),

                'landlord' => $this->calculate(
                    $amount,
                    $configuration->landlord_percentage
                ),

                'saas' => $this->calculate(
                    $amount,
                    $configuration->saas_percentage
                ),
            ];

            /*
            |--------------------------------------------------------------------------
            | Handle rounding
            |--------------------------------------------------------------------------
            */

            $difference = round(
                $amount - array_sum($allocations),
                2
            );

            if ($difference != 0) {
                $allocations['water'] = round(
                    $allocations['water'] + $difference,
                    2
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verify total
            |--------------------------------------------------------------------------
            */

            if (
                round(
                    array_sum($allocations),
                    2
                ) !== round($amount, 2)
            ) {
                throw new RuntimeException(
                    'Payment allocations do not equal payment amount.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create allocation records
            |--------------------------------------------------------------------------
            */

            foreach ($allocations as $type => $allocationAmount) {

                $payment->allocations()->create([
                    'billing_configuration_id' =>
                        $configuration->id,

                    'allocation_type' =>
                        $type,

                    'percentage' =>
                        $this->percentageFor(
                            $configuration,
                            $type
                        ),

                    'amount' =>
                        $allocationAmount,
                ]);
            }

            return $payment
                ->fresh()
                ->load('allocations');
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
                "Billing configuration percentages must equal 100%. " .
                "Current total: {$total}%."
            );
        }
    }

    protected function percentageFor(
        BillingConfiguration $configuration,
        string $type
    ): float {

        return match ($type) {

            'water' =>
                (float) $configuration->water_percentage,

            'service_fee' =>
                (float) $configuration->service_fee_percentage,

            'vat' =>
                (float) $configuration->vat_percentage,

            'gateway_fee' =>
                (float) $configuration->gateway_fee_percentage,

            'landlord' =>
                (float) $configuration->landlord_percentage,

            'saas' =>
                (float) $configuration->saas_percentage,

            default =>
                throw new RuntimeException(
                    "Unknown allocation type: {$type}"
                ),
        };
    }
}