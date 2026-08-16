<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\ReconciliationRecord;
use App\Models\StsTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(
        protected EmailNotificationService $emailNotificationService
    ) {
    }

    /**
     * Create an idempotent in-app notification.
     */
    public function createSystemNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        $eventKey =
            $data['event_key']
            ?? null;

        if ($eventKey) {

            $existing =
                Notification::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'channel',
                        'system'
                    )
                    ->where(
                        'type',
                        $type
                    )
                    ->where(
                        'data->event_key',
                        $eventKey
                    )
                    ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Notification::create([
            'user_id' =>
                $user->id,

            'channel' =>
                'system',

            'type' =>
                $type,

            'title' =>
                $title,

            'message' =>
                $message,

            'data' =>
                $data,

            'sent_at' =>
                now(),

            'status' =>
                'sent',

            'error_message' =>
                null,
        ]);
    }

    /**
     * Successful payment.
     */
    public function paymentSuccessful(
        Payment $payment
    ): void {
        $payment->loadMissing([
            'tenant',
            'organization',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Tenant in-app notification
        |--------------------------------------------------------------------------
        */

        $tenantUser =
            $this->resolveTenantUser(
                $payment
            );

        if ($tenantUser) {

            $this->createSystemNotification(
                $tenantUser,
                'payment_successful',
                'Payment Successful',
                sprintf(
                    'Your payment of %s %s has been received successfully.',
                    $payment->currency,
                    number_format(
                        (float)
                        $payment->amount,
                        2
                    )
                ),
                [
                    'event_key' =>
                        'PAYMENT_SUCCESS:' .
                        $payment->id,

                    'payment_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'amount' =>
                        (float)
                        $payment->amount,

                    'currency' =>
                        $payment->currency,

                    'status' =>
                        $payment->status,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Landlord / organization notification
        |--------------------------------------------------------------------------
        */

        foreach (
            $this->organizationRecipients(
                $payment->organization_id
            )
            as $user
        ) {

            $this->createSystemNotification(
                $user,
                'payment_received',
                'Payment Received',
                sprintf(
                    'Payment %s for %s %s was received successfully.',
                    $payment->reference,
                    $payment->currency,
                    number_format(
                        (float)
                        $payment->amount,
                        2
                    )
                ),
                [
                    'event_key' =>
                        'PAYMENT_RECEIVED:' .
                        $payment->id,

                    'payment_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'amount' =>
                        (float)
                        $payment->amount,

                    'currency' =>
                        $payment->currency,

                    'tenant_id' =>
                        $payment->tenant_id,

                    'property_id' =>
                        $payment->property_id,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Tenant email
        |--------------------------------------------------------------------------
        */

        try {

            $this
                ->emailNotificationService
                ->paymentSuccessful(
                    $payment
                );

        } catch (Throwable $e) {

            Log::warning(
                'Payment success email failed.',
                [
                    'payment_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Payment failed.
     */
    public function paymentFailed(
        Payment $payment,
        ?string $reason = null
    ): void {
        $tenantUser =
            $this->resolveTenantUser(
                $payment
            );

        $friendlyMessage =
            $this
                ->friendlyPaymentFailureMessage(
                    $reason
                );

        if ($tenantUser) {

            $this->createSystemNotification(
                $tenantUser,
                'payment_failed',
                'Payment Failed',
                $friendlyMessage,
                [
                    'event_key' =>
                        'PAYMENT_FAILED:' .
                        $payment->id,

                    'payment_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'amount' =>
                        (float)
                        $payment->amount,

                    'currency' =>
                        $payment->currency,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Tenant email
        |--------------------------------------------------------------------------
        */

        try {

            $this
                ->emailNotificationService
                ->paymentFailed(
                    $payment,
                    $friendlyMessage
                );

        } catch (Throwable $e) {

            Log::warning(
                'Payment failure email failed.',
                [
                    'payment_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /**
     * STS token generated.
     */
    public function stsTokenGenerated(
        Payment $payment,
        StsTransaction $transaction
    ): void {
        $tenantUser =
            $this->resolveTenantUser(
                $payment
            );

        $transaction->loadMissing([
            'tokens',
            'meter',
        ]);

        $token =
            $transaction
                ->tokens
                ->first()?->token
            ??
            $transaction->token;

        if ($tenantUser) {

            $this->createSystemNotification(
                $tenantUser,
                'sts_token_generated',
                'Water Token Generated',
                'Your prepaid water token has been generated successfully.',
                [
                    'event_key' =>
                        'STS_TOKEN:' .
                        $transaction->id,

                    'payment_id' =>
                        $payment->id,

                    'payment_reference' =>
                        $payment->reference,

                    'sts_transaction_id' =>
                        $transaction->id,

                    'sts_reference' =>
                        $transaction->reference,

                    'meter_id' =>
                        $transaction->meter_id,

                    'meter_number' =>
                        $transaction
                            ->meter
                            ?->meter_number,

                    'token' =>
                        $token,

                    'volume_m3' =>
                        (float) (
                            $transaction
                                ->volume_m3
                            ?? 0
                        ),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Email actual purchased token
        |--------------------------------------------------------------------------
        */

        try {

            $this
                ->emailNotificationService
                ->waterTokenGenerated(
                    $payment,
                    $transaction
                );

        } catch (Throwable $e) {

            Log::warning(
                'Water token email failed.',
                [
                    'payment_id' =>
                        $payment->id,

                    'sts_transaction_id' =>
                        $transaction->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    /**
     * STS vending failed.
     */
    public function stsVendingFailed(
        Payment $payment,
        string $reason
    ): void {
        foreach (
            $this->organizationRecipients(
                $payment->organization_id
            )
            as $user
        ) {

            $this->createSystemNotification(
                $user,
                'sts_vending_failed',
                'STS Vending Failed',
                sprintf(
                    'STS vending failed for payment %s.',
                    $payment->reference
                ),
                [
                    'event_key' =>
                        'STS_FAILED:' .
                        $payment->id,

                    'payment_id' =>
                        $payment->id,

                    'payment_reference' =>
                        $payment->reference,

                    'reason' =>
                        $reason,
                ]
            );
        }

        $tenantUser =
            $this->resolveTenantUser(
                $payment
            );

        if ($tenantUser) {

            $this->createSystemNotification(
                $tenantUser,
                'sts_vending_failed',
                'Water Token Delayed',
                'Your payment was successful, but your water token could not be generated immediately.',
                [
                    'event_key' =>
                        'STS_FAILED_TENANT:' .
                        $payment->id,

                    'payment_id' =>
                        $payment->id,

                    'payment_reference' =>
                        $payment->reference,
                ]
            );
        }
    }

    /**
     * Reconciliation discrepancy.
     */
    public function reconciliationIssue(
        ReconciliationRecord $record
    ): void {
        if (
            in_array(
                $record->status,
                [
                    'matched',
                    'resolved',
                ],
                true
            )
        ) {
            return;
        }

        if ($record->organization_id) {

            foreach (
                $this->organizationRecipients(
                    $record->organization_id
                )
                as $user
            ) {

                $this->createSystemNotification(
                    $user,
                    'reconciliation_issue',
                    'Reconciliation Issue Detected',
                    sprintf(
                        'A %s reconciliation issue requires review.',
                        $record
                            ->reconciliation_type
                        ??
                        $record->provider
                    ),
                    [
                        'event_key' =>
                            'RECON:' .
                            $record->id .
                            ':' .
                            $record->status,

                        'reconciliation_record_id' =>
                            $record->id,

                        'provider' =>
                            $record->provider,

                        'reconciliation_type' =>
                            $record
                                ->reconciliation_type,

                        'status' =>
                            $record->status,

                        'expected_amount' =>
                            (float)
                            $record
                                ->expected_amount,

                        'actual_amount' =>
                            (float)
                            $record
                                ->actual_amount,

                        'difference' =>
                            (float)
                            $record->difference,

                        'issues' =>
                            $record
                                ->external_data[
                                    'issues'
                                ]
                            ?? [],
                    ]
                );
            }
        }

        foreach (
            $this->superAdmins()
            as $admin
        ) {

            $this->createSystemNotification(
                $admin,
                'reconciliation_issue',
                'Platform Reconciliation Issue',
                sprintf(
                    'Reconciliation record #%d is %s.',
                    $record->id,
                    $record->status
                ),
                [
                    'event_key' =>
                        'ADMIN_RECON:' .
                        $record->id .
                        ':' .
                        $record->status,

                    'reconciliation_record_id' =>
                        $record->id,

                    'organization_id' =>
                        $record->organization_id,

                    'provider' =>
                        $record->provider,

                    'reconciliation_type' =>
                        $record
                            ->reconciliation_type,

                    'status' =>
                        $record->status,

                    'difference' =>
                        (float)
                        $record->difference,
                ]
            );
        }
    }

    protected function organizationRecipients(
        ?int $organizationId
    ): Collection {
        if (!$organizationId) {
            return collect();
        }

        return User::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->get();
    }

    protected function superAdmins(): Collection
    {
        return User::role(
            'Super Admin'
        )->get();
    }

    protected function resolveTenantUser(
        Payment $payment
    ): ?User {
        $payment->loadMissing(
            'tenant'
        );

        $tenant =
            $payment->tenant;

        if (!$tenant) {
            return null;
        }

        if (
            isset($tenant->user_id) &&
            $tenant->user_id
        ) {
            return User::find(
                $tenant->user_id
            );
        }

        if (
            filled($tenant->email)
        ) {
            return User::query()
                ->where(
                    'email',
                    $tenant->email
                )
                ->first();
        }

        return null;
    }

    protected function friendlyPaymentFailureMessage(
        ?string $reason
    ): string {
        if (!$reason) {
            return
                'Your payment could not be completed. Please try again.';
        }

        $reasonLower =
            strtolower(
                $reason
            );

        if (
            str_contains(
                $reasonLower,
                'timed out'
            )
            ||
            str_contains(
                $reasonLower,
                'timeout'
            )
            ||
            str_contains(
                $reasonLower,
                'connection'
            )
            ||
            str_contains(
                $reasonLower,
                'curl'
            )
        ) {
            return
                'The payment provider could not be reached. Please try again shortly.';
        }

        if (
            str_contains(
                $reasonLower,
                'insufficient'
            )
        ) {
            return
                'The payment could not be completed due to insufficient funds.';
        }

        if (
            str_contains(
                $reasonLower,
                'cancel'
            )
        ) {
            return
                'The mobile money payment was cancelled.';
        }

        return
            'Your payment could not be completed. Please try again.';
    }
}