<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use App\Models\Meter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MobileMoneyPaymentService
{
    public function __construct(
        protected RelworxService $relworxService,
        protected PaymentProcessingService $paymentProcessingService
    ) {
    }

    /**
     * Initiate an MTN/Airtel mobile-money payment.
     */
    public function initiateForMeter(
        Meter $meter,
        float $amount,
        string $msisdn
    ): Payment {

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve active meter assignment
        |--------------------------------------------------------------------------
        */

        $assignment =
            $meter->assignments()
                ->where(
                    'status',
                    'active'
                )
                ->whereNull(
                    'unassigned_at'
                )
                ->with([
                    'unit.property',
                    'unit.activeTenancy.tenant',
                ])
                ->first();

        if (!$assignment) {
            throw new RuntimeException(
                'This meter is not currently assigned to a unit.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve unit
        |--------------------------------------------------------------------------
        */

        $unit =
            $assignment->unit;

        if (!$unit) {
            throw new RuntimeException(
                'Meter does not have a valid unit.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve property
        |--------------------------------------------------------------------------
        */

        $property =
            $unit->property;

        if (!$property) {
            throw new RuntimeException(
                'Meter unit does not have a valid property.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve active tenancy
        |--------------------------------------------------------------------------
        */

        $tenancy =
            $unit->activeTenancy;

        if (!$tenancy) {
            throw new RuntimeException(
                'This meter does not currently have an active tenant.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve tenant
        |--------------------------------------------------------------------------
        */

        $tenant =
            $tenancy->tenant;

        if (!$tenant) {
            throw new RuntimeException(
                'Active tenancy does not have a tenant.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find Relworx
        |--------------------------------------------------------------------------
        */

        $provider =
            PaymentProvider::query()
                ->where(
                    'code',
                    'RELWORX'
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Select provider account
        |--------------------------------------------------------------------------
        */

        $providerAccount =
            PaymentProviderAccount::query()
                ->where(
                    'payment_provider_id',
                    $provider->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(function ($query)
                    use ($property) {

                    $query
                        ->where(
                            'organization_id',
                            $property->organization_id
                        )
                        ->orWhereNull(
                            'organization_id'
                        );
                })
                ->orderByRaw(
                    'organization_id IS NULL ASC'
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Generate public payment reference
        |--------------------------------------------------------------------------
        */

        $reference =
            'WTR-' .
            now()->format('YmdHis') .
            '-' .
            strtoupper(
                Str::random(8)
            );

        /*
        |--------------------------------------------------------------------------
        | Normalize payer number
        |--------------------------------------------------------------------------
        */

        $msisdn =
            $this->normalizeUgandanMsisdn(
                $msisdn
            );

        /*
        |--------------------------------------------------------------------------
        | Create local pending payment
        |--------------------------------------------------------------------------
        */

        $payment =
            DB::transaction(
                function () use (
                    $tenant,
                    $property,
                    $provider,
                    $providerAccount,
                    $reference,
                    $amount,
                    $msisdn
                ) {

                    return Payment::create([
                        'organization_id' =>
                            $property->organization_id,

                        'property_id' =>
                            $property->id,

                        /*
                        |--------------------------------------------------------------------------
                        | Beneficiary tenant
                        |--------------------------------------------------------------------------
                        |
                        | This is NOT necessarily the person paying.
                        |
                        */

                        'tenant_id' =>
                            $tenant->id,

                        'payment_provider_id' =>
                            $provider->id,

                        'payment_provider_account_id' =>
                            $providerAccount?->id,

                        'reference' =>
                            $reference,

                        'amount' =>
                            round(
                                $amount,
                                2
                            ),

                        'currency' =>
                            'UGX',

                        /*
                        |--------------------------------------------------------------------------
                        | Actual person/mobile number paying
                        |--------------------------------------------------------------------------
                        */

                        'payer_phone' =>
                            $msisdn,

                        'status' =>
                            'pending',

                        'initiated_at' =>
                            now(),
                    ]);
                }
            );

        try {

            /*
            |--------------------------------------------------------------------------
            | Ask Relworx to debit payer's mobile-money account
            |--------------------------------------------------------------------------
            */

            $result =
                $this->relworxService
                    ->requestPayment(
                        $payment->reference,
                        $payment->payer_phone,
                        (float)
                        $payment->amount,
                        $payment->currency,
                        'Water purchase for meter ' .
                            $meter->meter_number
                    );

            $payment->update([
                'provider_reference' =>
                    $result[
                        'internal_reference'
                    ],

                'status' =>
                    'processing',

                'provider_response' =>
                    $result,
            ]);

            return $payment->fresh();

        } catch (\Throwable $e) {

            $payment->update([
                'status' =>
                    'failed',

                'failure_reason' =>
                    $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh payment state from Relworx.
     */
    public function checkStatus(
        Payment $payment
    ): Payment {

        if (!$payment->provider_reference) {
            throw new RuntimeException(
                'Payment does not have a Relworx internal reference.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Already fully processed
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status ===
            'successful'
        ) {
            return $payment
                ->fresh()
                ->load([
                    'allocations',
                    'waterVending.tokens',
                    'stsTransactions',
                ]);
        }

        $result =
            $this->relworxService
                ->checkRequestStatus(
                    $payment
                        ->provider_reference
                );

        $status =
            strtolower(
                (string)
                (
                    $result[
                        'request_status'
                    ]
                    ??
                    $result['status']
                    ??
                    ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Pending
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    'pending',
                    'processing',
                ],
                true
            )
        ) {

            $payment->update([
                'status' =>
                    'processing',

                'mobile_money_provider' =>
                    $result['provider']
                        ?? null,

                'provider_response' =>
                    $result,
            ]);

            return $payment->fresh();
        }

        /*
        |--------------------------------------------------------------------------
        | Successful payment
        |--------------------------------------------------------------------------
        */

        if ($status === 'success') {

            /*
            |--------------------------------------------------------------------------
            | Validate returned amount/currency
            |--------------------------------------------------------------------------
            */

            if (
                round(
                    (float)
                    ($result['amount'] ?? 0),
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
                    'Relworx payment amount does not match local payment.'
                );
            }

            if (
                strtoupper(
                    (string)
                    ($result['currency'] ?? '')
                )
                !==
                strtoupper(
                    $payment->currency
                )
            ) {
                throw new RuntimeException(
                    'Relworx payment currency does not match local payment.'
                );
            }

            $payment->update([
                'status' =>
                    'successful',

                'mobile_money_provider' =>
                    $result['provider']
                        ?? null,

                'provider_transaction_id' =>
                    $result[
                        'provider_transaction_id'
                    ] ?? null,

                'provider_charge' =>
                    $result['charge']
                        ?? null,

                'provider_response' =>
                    $result,

                'completed_at' =>
                    isset(
                        $result['completed_at']
                    ) &&
                    $result['completed_at']
                    !== 'N/A'
                        ? $result[
                            'completed_at'
                        ]
                        : now(),

                'failure_reason' =>
                    null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Existing pipeline:
            |
            | allocation
            | ledger
            | water wallet
            | m³ calculation
            | STS token
            |--------------------------------------------------------------------------
            */

            return $this
                ->paymentProcessingService
                ->processSuccessfulPayment(
                    $payment->fresh()
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Failure / cancelled
        |--------------------------------------------------------------------------
        */

        $payment->update([
            'status' =>
                'failed',

            'mobile_money_provider' =>
                $result['provider']
                    ?? null,

            'provider_response' =>
                $result,

            'failure_reason' =>
                $result['message']
                    ?? 'Mobile money payment failed.',
        ]);

        return $payment->fresh();
    }

    /**
     * Convert common Uganda formats to +256XXXXXXXXX.
     */
    protected function normalizeUgandanMsisdn(
        string $msisdn
    ): string {

        $number =
            preg_replace(
                '/\s+/',
                '',
                trim($msisdn)
            );

        if (
            str_starts_with(
                $number,
                '+256'
            )
        ) {
            return $number;
        }

        if (
            str_starts_with(
                $number,
                '256'
            )
        ) {
            return '+' . $number;
        }

        if (
            str_starts_with(
                $number,
                '0'
            )
        ) {
            return '+256' .
                substr(
                    $number,
                    1
                );
        }

        throw new RuntimeException(
            'Invalid Ugandan mobile money number.'
        );
    }
}