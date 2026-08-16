<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReconciliationRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class RelworxReconciliationService
{
    public function __construct(
        protected RelworxService $relworxService
    ) {
    }

    /**
     * Reconcile all eligible Relworx payments.
     */
    public function run(User $user): array
    {
        $query = Payment::query()
            ->with('paymentProvider')
            ->whereNotNull('provider_reference')
            ->whereHas(
                'paymentProvider',
                fn ($q) => $q->where('code', 'RELWORX')
            );

        /*
        |--------------------------------------------------------------------------
        | Organization isolation
        |--------------------------------------------------------------------------
        |
        | Super Admin may reconcile the whole platform.
        | Other users may only reconcile their organization.
        |
        */

        if (!$user->isSuperAdmin()) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        $payments = $query
            ->orderBy('id')
            ->get();

        $summary = [
            'total' => 0,
            'matched' => 0,
            'partial' => 0,
            'unmatched' => 0,
            'provider_errors' => 0,
        ];

        foreach ($payments as $payment) {
            $summary['total']++;

            try {
                $record = $this->reconcilePayment(
                    $payment
                );

                if (
                    isset(
                        $summary[$record->status]
                    )
                ) {
                    $summary[$record->status]++;
                }
            } catch (Throwable $e) {
                $summary['provider_errors']++;

                $this->storeProviderError(
                    $payment,
                    $e
                );

                Log::warning(
                    'Relworx reconciliation failed.',
                    [
                        'payment_id' =>
                            $payment->id,

                        'reference' =>
                            $payment->reference,

                        'provider_reference' =>
                            $payment->provider_reference,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        return $summary;
    }

    /**
     * Reconcile one payment against Relworx.
     */
    public function reconcilePayment(
        Payment $payment
    ): ReconciliationRecord {

        if (!$payment->provider_reference) {
            return $this->storeMissingProviderReference(
                $payment
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Fetch authoritative provider status
        |--------------------------------------------------------------------------
        */

        $providerData =
            $this->relworxService
                ->checkRequestStatus(
                    $payment->provider_reference
                );

        /*
        |--------------------------------------------------------------------------
        | Compare local and provider records
        |--------------------------------------------------------------------------
        */

        $issues = [];

        /*
        |--------------------------------------------------------------------------
        | Customer reference
        |--------------------------------------------------------------------------
        */

        $providerCustomerReference =
            $providerData['customer_reference']
                ?? null;

        if (
            $providerCustomerReference !== null &&
            $providerCustomerReference
                !== $payment->reference
        ) {
            $issues[] = 'reference_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Internal/provider reference
        |--------------------------------------------------------------------------
        */

        $providerInternalReference =
            $providerData['internal_reference']
                ?? null;

        if (
            $providerInternalReference !== null &&
            $providerInternalReference
                !== $payment->provider_reference
        ) {
            $issues[] =
                'provider_reference_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $localAmount =
            round(
                (float) $payment->amount,
                2
            );

        $providerAmount =
            isset($providerData['amount'])
                ? round(
                    (float) $providerData['amount'],
                    2
                )
                : 0.00;

        if (
            isset($providerData['amount']) &&
            abs(
                $localAmount -
                $providerAmount
            ) > 0.009
        ) {
            $issues[] = 'amount_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Currency
        |--------------------------------------------------------------------------
        */

        $providerCurrency =
            strtoupper(
                (string) (
                    $providerData['currency']
                        ?? ''
                )
            );

        $localCurrency =
            strtoupper(
                (string) $payment->currency
            );

        if (
            $providerCurrency !== '' &&
            $providerCurrency !== $localCurrency
        ) {
            $issues[] = 'currency_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Phone number
        |--------------------------------------------------------------------------
        */

        $providerPhone =
            $this->normalizePhone(
                $providerData['msisdn']
                    ?? null
            );

        $localPhone =
            $this->normalizePhone(
                $payment->payer_phone
            );

        if (
            $providerPhone !== null &&
            $localPhone !== null &&
            $providerPhone !== $localPhone
        ) {
            $issues[] = 'phone_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Provider payment status
        |--------------------------------------------------------------------------
        */

        $providerStatus =
            strtolower(
                (string) (
                    $providerData['request_status']
                        ?? $providerData['status']
                        ?? ''
                )
            );

        $localStatus =
            strtolower(
                (string) $payment->status
            );

        if (
            !$this->statusesMatch(
                $localStatus,
                $providerStatus
            )
        ) {
            $issues[] = 'status_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Provider transaction ID
        |--------------------------------------------------------------------------
        */

        $providerTransactionId =
            $providerData[
                'provider_transaction_id'
            ] ?? null;

        if (
            $payment->provider_transaction_id &&
            $providerTransactionId &&
            $providerTransactionId !== 'N/A' &&
            (string) $payment->provider_transaction_id
                !== (string) $providerTransactionId
        ) {
            $issues[] =
                'provider_transaction_id_mismatch';
        }

        /*
        |--------------------------------------------------------------------------
        | Determine reconciliation status
        |--------------------------------------------------------------------------
        */

        $status =
            $this->determineStatus(
                $issues,
                $providerData
            );

        /*
        |--------------------------------------------------------------------------
        | Persist idempotently
        |--------------------------------------------------------------------------
        */

        return ReconciliationRecord::updateOrCreate(
            [
                'provider' => 'relworx',

                'internal_reference' =>
                    'RELWORX:' . $payment->id,
            ],
            [
                'organization_id' =>
                    $payment->organization_id,

                'reconciliation_type' =>
                    'provider_payment',

                'provider_reference' =>
                    $payment->provider_reference,

                'transaction_date' =>
                    $payment->completed_at
                    ?? $payment->initiated_at
                    ?? $payment->created_at,

                'expected_amount' =>
                    $localAmount,

                'actual_amount' =>
                    $providerAmount,

                'difference' =>
                    round(
                        $providerAmount -
                        $localAmount,
                        2
                    ),

                'status' =>
                    $status,

                'external_data' => [
                    'payment_id' =>
                        $payment->id,

                    'local_reference' =>
                        $payment->reference,

                    'provider_reference' =>
                        $payment->provider_reference,

                    'local_status' =>
                        $payment->status,

                    'provider_status' =>
                        $providerStatus,

                    'local_amount' =>
                        $localAmount,

                    'provider_amount' =>
                        $providerAmount,

                    'local_currency' =>
                        $localCurrency,

                    'provider_currency' =>
                        $providerCurrency,

                    'local_phone' =>
                        $payment->payer_phone,

                    'provider_phone' =>
                        $providerData['msisdn']
                            ?? null,

                    'mobile_money_provider' =>
                        $providerData['provider']
                            ?? null,

                    'local_provider_transaction_id' =>
                        $payment
                            ->provider_transaction_id,

                    'provider_transaction_id' =>
                        $providerTransactionId,

                    'provider_charge' =>
                        $providerData['charge']
                            ?? null,

                    'provider_completed_at' =>
                        $providerData['completed_at']
                            ?? null,

                    'issues' =>
                        $issues,

                    'provider_response' =>
                        $providerData,
                ],
            ]
        );
    }

    /**
     * Determine reconciliation classification.
     */
    protected function determineStatus(
        array $issues,
        array $providerData
    ): string {

        if (empty($issues)) {
            return 'matched';
        }

        $providerStatus =
            strtolower(
                (string) (
                    $providerData['request_status']
                        ?? $providerData['status']
                        ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Pending is not necessarily a financial mismatch.
        |--------------------------------------------------------------------------
        */

        if (
            count($issues) === 1 &&
            $issues[0] === 'status_mismatch' &&
            $providerStatus === 'pending'
        ) {
            return 'partial';
        }

        /*
        |--------------------------------------------------------------------------
        | Amount/reference/currency discrepancies are serious.
        |--------------------------------------------------------------------------
        */

        $criticalIssues = [
            'amount_mismatch',
            'currency_mismatch',
            'reference_mismatch',
            'provider_reference_mismatch',
        ];

        if (
            count(
                array_intersect(
                    $issues,
                    $criticalIssues
                )
            ) > 0
        ) {
            return 'unmatched';
        }

        return 'partial';
    }

    /**
     * Compare local status terminology against Relworx.
     */
    protected function statusesMatch(
        string $localStatus,
        string $providerStatus
    ): bool {

        $localStatus =
            strtolower($localStatus);

        $providerStatus =
            strtolower($providerStatus);

        if (
            $localStatus === 'successful' &&
            $providerStatus === 'success'
        ) {
            return true;
        }

        if (
            $localStatus === 'failed' &&
            $providerStatus === 'failed'
        ) {
            return true;
        }

        if (
            in_array(
                $localStatus,
                ['pending', 'processing'],
                true
            ) &&
            $providerStatus === 'pending'
        ) {
            return true;
        }

        return $localStatus === $providerStatus;
    }

    /**
     * Normalize Ugandan MSISDN formatting.
     */
    protected function normalizePhone(
        ?string $phone
    ): ?string {

        if (!$phone) {
            return null;
        }

        $phone =
            preg_replace(
                '/\D+/',
                '',
                $phone
            );

        if (!$phone) {
            return null;
        }

        if (
            str_starts_with(
                $phone,
                '0'
            )
        ) {
            return
                '256' .
                substr(
                    $phone,
                    1
                );
        }

        return $phone;
    }

    /**
     * Store an error while communicating with Relworx.
     */
    protected function storeProviderError(
        Payment $payment,
        Throwable $e
    ): ReconciliationRecord {

        return ReconciliationRecord::updateOrCreate(
            [
                'provider' => 'relworx',

                'internal_reference' =>
                    'RELWORX:' . $payment->id,
            ],
            [
                'organization_id' =>
                    $payment->organization_id,

                'reconciliation_type' =>
                    'provider_payment',

                'provider_reference' =>
                    $payment->provider_reference,

                'transaction_date' =>
                    $payment->completed_at
                    ?? $payment->initiated_at
                    ?? $payment->created_at,

                'expected_amount' =>
                    $payment->amount,

                'actual_amount' =>
                    0,

                'difference' =>
                    0 - (float) $payment->amount,

                'status' =>
                    'unmatched',

                'external_data' => [
                    'payment_id' =>
                        $payment->id,

                    'issue' =>
                        'provider_request_failed',

                    'error' =>
                        $e->getMessage(),
                ],
            ]
        );
    }

    /**
     * Store missing provider-reference problem.
     */
    protected function storeMissingProviderReference(
        Payment $payment
    ): ReconciliationRecord {

        return ReconciliationRecord::updateOrCreate(
            [
                'provider' => 'relworx',

                'internal_reference' =>
                    'RELWORX:' . $payment->id,
            ],
            [
                'organization_id' =>
                    $payment->organization_id,

                'reconciliation_type' =>
                    'provider_payment',

                'provider_reference' =>
                    null,

                'transaction_date' =>
                    $payment->completed_at
                    ?? $payment->initiated_at
                    ?? $payment->created_at,

                'expected_amount' =>
                    $payment->amount,

                'actual_amount' =>
                    0,

                'difference' =>
                    0 - (float) $payment->amount,

                'status' =>
                    'unmatched',

                'external_data' => [
                    'payment_id' =>
                        $payment->id,

                    'issue' =>
                        'missing_provider_reference',
                ],
            ]
        );
    }
}