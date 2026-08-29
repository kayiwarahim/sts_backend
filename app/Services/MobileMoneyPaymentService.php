<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\PaymentProviderAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MobileMoneyPaymentService
{
    public function __construct(
        protected RelworxService $relworxService,
        protected PaymentProcessingService $paymentProcessingService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Anyone can initiate payment for an active meter.
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
        | Resolve meter → unit → property → tenant
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

        if (! $assignment) {
            throw new RuntimeException(
                'This meter is not currently assigned to a unit.'
            );
        }

        $unit =
            $assignment->unit;

        if (! $unit) {
            throw new RuntimeException(
                'Meter does not have a valid unit.'
            );
        }

        $property =
            $unit->property;

        if (! $property) {
            throw new RuntimeException(
                'Meter unit does not have a property.'
            );
        }

        $tenancy =
            $unit->activeTenancy;

        if (! $tenancy) {
            throw new RuntimeException(
                'This meter does not have an active tenancy.'
            );
        }

        $tenant =
            $tenancy->tenant;

        if (! $tenant) {
            throw new RuntimeException(
                'Active tenancy does not have a tenant.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Relworx provider
        |--------------------------------------------------------------------------
        */

        $provider =
            PaymentProvider::query()
                ->where('code', 'RELWORX')
                ->where('is_active', true)
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Provider account
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
                ->where(
                    function ($query) use ($property) {
                        $query
                            ->where(
                                'organization_id',
                                $property
                                    ->organization_id
                            )
                            ->orWhereNull(
                                'organization_id'
                            );
                    }
                )
                ->orderByRaw(
                    'organization_id IS NULL ASC'
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Customer/reference sent to Relworx
        |--------------------------------------------------------------------------
        */

        $reference =
            'STS-'.
            now()->format(
                'YmdHis'
            ).
            '-'.
            strtoupper(
                Str::random(8)
            );

        $msisdn =
            $this
                ->normalizeUgandanMsisdn(
                    $msisdn
                );

        /*
        |--------------------------------------------------------------------------
        | Create local payment BEFORE contacting Relworx
        |--------------------------------------------------------------------------
        */

        $payment =
            Payment::create([
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'payment_provider_id' => $provider->id,
                'payment_provider_account_id' => $providerAccount?->id,
                'reference' => $reference,
                'amount' => round($amount, 2),
                'currency' => 'UGX',
                'payer_phone' => $msisdn,
                'status' => 'pending',
                'initiated_at' => now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Contact Relworx
        |--------------------------------------------------------------------------
        */

        try {
            $result =
                $this
                    ->relworxService
                    ->requestPayment(
                        $payment->reference,
                        $payment->payer_phone,
                        (float)
                        $payment->amount,
                        $payment->currency,
                        'Water purchase for meter '.
                        $meter->meter_number
                    );

            $payment->update([
                'provider_reference' => $result[
                        'internal_reference'
                    ],

                'status' => 'processing',

                'provider_response' => $result,
            ]);

            return $payment->fresh();

        } catch (Throwable $e) {
            /*
            |--------------------------------------------------------------------------
            | Initial request failed
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'status' => 'failed',

                'failure_reason' => $e->getMessage(),

                'completed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Notify without changing payment outcome
            |--------------------------------------------------------------------------
            */

            $this->safePaymentFailedNotification(
                $payment->fresh(),
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Poll Relworx manually.
     *
     * This remains useful as a fallback even after webhooks.
     */
    public function checkStatus(
        Payment $payment
    ): Payment {
        /*
        |--------------------------------------------------------------------------
        | Already successful
        |--------------------------------------------------------------------------
        |
        | If provider marked it successful previously, but financial or STS
        | processing was interrupted, finish the local workflow.
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status ===
            'successful'
        ) {
            if (
                ! $payment
                    ->ledger_transaction_id
                ||
                ! $payment
                    ->waterVending()
                    ->where(
                        'status',
                        'successful'
                    )
                    ->exists()
            ) {
                return $this
                    ->paymentProcessingService
                    ->processSuccessfulPayment(
                        $payment->fresh()
                    );
            }

            return $payment
                ->fresh()
                ->load([
                    'allocations',
                    'waterVending.tokens',
                    'stsTransactions',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Provider reference required
        |--------------------------------------------------------------------------
        */

        if (
            ! $payment
                ->provider_reference
        ) {
            throw new RuntimeException(
                'Payment does not have a Relworx internal reference.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Query Relworx
        |--------------------------------------------------------------------------
        */

        $result =
            $this
                ->relworxService
                ->checkRequestStatus(
                    $payment
                        ->provider_reference
                );

        return $this
            ->applyProviderResult(
                $payment,
                $result,
                true
            );
    }

    /**
     * Apply a Relworx status or webhook result to a payment.
     *
     * Used by BOTH:
     *
     * - manual status polling
     * - webhook processing
     */
    public function applyProviderResult(
        Payment $payment,
        array $result,
        bool $processImmediately = true
    ): Payment {
        /*
        |--------------------------------------------------------------------------
        | Validate references
        |--------------------------------------------------------------------------
        */

        $customerReference =
            $result[
                'customer_reference'
            ] ?? null;

        $internalReference =
            $result[
                'internal_reference'
            ] ?? null;

        if (
            $customerReference &&
            $customerReference !==
            $payment->reference
        ) {
            throw new RuntimeException(
                'Relworx customer reference does not match payment.'
            );
        }

        if (
            $internalReference &&
            $payment
                ->provider_reference &&
            $internalReference !==
            $payment
                ->provider_reference
        ) {
            throw new RuntimeException(
                'Relworx internal reference does not match payment.'
            );
        }

        $status =
            strtolower(
                (string) (
                    $result[
                        'request_status'
                    ]
                    ??
                    $result[
                        'status'
                    ]
                    ??
                    ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Update while locking payment
        |--------------------------------------------------------------------------
        */

        $payment =
            DB::transaction(
                function () use (
                    $payment,
                    $result,
                    $status,
                    $internalReference
                ) {
                    $locked =
                        Payment::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $payment->id
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Never downgrade successful payment
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $locked->status ===
                        'successful'
                    ) {
                        return $locked
                            ->fresh();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Pending / processing
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
                        $locked->update([
                            'status' => 'processing',

                            'provider_reference' => $internalReference
                                ??
                                $locked
                                    ->provider_reference,

                            'mobile_money_provider' => $result[
                                    'provider'
                                ] ?? null,

                            'provider_response' => $result,
                        ]);

                        return $locked
                            ->fresh();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Successful
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $status ===
                        'success'
                        ||
                        $status ===
                        'successful'
                    ) {
                        /*
                        |--------------------------------------------------------------------------
                        | Validate amount if supplied
                        |--------------------------------------------------------------------------
                        */

                        if (
                            array_key_exists(
                                'amount',
                                $result
                            )
                            &&
                            round(
                                (float)
                                $result[
                                    'amount'
                                ],
                                2
                            )
                            !==
                            round(
                                (float)
                                $locked->amount,
                                2
                            )
                        ) {
                            throw new RuntimeException(
                                'Relworx payment amount does not match local payment.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Validate currency if supplied
                        |--------------------------------------------------------------------------
                        */

                        if (
                            ! empty(
                                $result[
                                    'currency'
                                ]
                            )
                            &&
                            strtoupper(
                                (string)
                                $result[
                                    'currency'
                                ]
                            )
                            !==
                            strtoupper(
                                $locked
                                    ->currency
                            )
                        ) {
                            throw new RuntimeException(
                                'Relworx payment currency does not match local payment.'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Success update
                        |--------------------------------------------------------------------------
                        */

                        $locked->update([
                            'status' => 'successful',

                            'provider_reference' => $internalReference
                                ??
                                $locked
                                    ->provider_reference,

                            'mobile_money_provider' => $result[
                                    'provider'
                                ]
                                ??
                                $locked
                                    ->mobile_money_provider,

                            'provider_transaction_id' => $result[
                                    'provider_transaction_id'
                                ]
                                ??
                                $locked
                                    ->provider_transaction_id,

                            'provider_charge' => $result[
                                    'charge'
                                ]
                                ??
                                $locked
                                    ->provider_charge,

                            'provider_response' => $result,

                            'completed_at' => (
                                isset(
                                    $result[
                                        'completed_at'
                                    ]
                                )
                                &&
                                $result[
                                    'completed_at'
                                ]
                                !==
                                'N/A'
                            )
                                    ?
                                    $result[
                                        'completed_at'
                                    ]
                                    :
                                    (
                                        $locked
                                            ->completed_at
                                        ??
                                        now()
                                    ),

                            'failure_reason' => null,
                        ]);

                        return $locked
                            ->fresh();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Failure
                    |--------------------------------------------------------------------------
                    */

                    $failureReason =
                        $result[
                            'message'
                        ]
                        ??
                        'Mobile money payment failed.';

                    $locked->update([
                        'status' => 'failed',

                        'provider_reference' => $internalReference
                            ??
                            $locked
                                ->provider_reference,

                        'mobile_money_provider' => $result[
                                'provider'
                            ]
                            ??
                            $locked
                                ->mobile_money_provider,

                        'provider_charge' => $result[
                                'charge'
                            ]
                            ??
                            $locked
                                ->provider_charge,

                        'provider_transaction_id' => $result[
                                'provider_transaction_id'
                            ]
                            ??
                            $locked
                                ->provider_transaction_id,

                        'provider_response' => $result,

                        'completed_at' => now(),

                        'failure_reason' => $failureReason,
                    ]);

                    return $locked
                        ->fresh();
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Successful payment
        |--------------------------------------------------------------------------
        |
        | Financial ledger / allocations / wallet / STS vending happen AFTER
        | the provider-status DB transaction commits.
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status ===
            'successful'
            &&
            $processImmediately
        ) {
            return $this
                ->paymentProcessingService
                ->processSuccessfulPayment(
                    $payment->fresh()
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Failed payment notification
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status ===
            'failed'
        ) {
            $this->safePaymentFailedNotification(
                $payment,
                $payment
                    ->failure_reason
            );
        }

        return $payment
            ->fresh();
    }

    /**
     * Notify about failed payment without ever breaking
     * payment/provider processing.
     */
    protected function safePaymentFailedNotification(
        Payment $payment,
        ?string $reason = null
    ): void {
        try {
            $this
                ->notificationService
                ->paymentFailed(
                    $payment,
                    $reason
                );
        } catch (Throwable $e) {
            Log::warning(
                'Payment failure notification failed.',
                [
                    'payment_id' => $payment->id,

                    'reference' => $payment->reference,

                    'reason' => $reason,

                    'notification_error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Normalize Uganda mobile money number.
     */
    protected function normalizeUgandanMsisdn(
        string $msisdn
    ): string {
        $number =
            preg_replace(
                '/[\s\-()]+/',
                '',
                trim(
                    $msisdn
                )
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
            return '+'.
                $number;
        }

        if (
            str_starts_with(
                $number,
                '0'
            )
        ) {
            return '+256'.
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
