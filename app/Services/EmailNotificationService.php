<?php

namespace App\Services;

use App\Mail\PaymentFailedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Mail\WaterTokenGeneratedMail;
use App\Models\Payment;
use App\Models\StsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Send successful payment email to tenant.
     */
    public function paymentSuccessful(
        Payment $payment
    ): void {
        $email = $this->resolveTenantEmail(
            $payment
        );

        if (! $email) {
            return;
        }

        Mail::to($email)->send(
            new PaymentSuccessfulMail(
                $payment
            )
        );
    }

    /**
     * Send failed payment email to tenant.
     */
    public function paymentFailed(
        Payment $payment,
        ?string $friendlyReason = null
    ): void {
        $email = $this->resolveTenantEmail(
            $payment
        );

        if (! $email) {
            return;
        }

        Mail::to($email)->send(
            new PaymentFailedMail(
                $payment,
                $friendlyReason
            )
        );
    }

    /**
     * Send generated STS water token to tenant.
     */
    public function waterTokenGenerated(
        Payment $payment,
        StsTransaction $transaction
    ): void {
        $email = $this->resolveTenantEmail(
            $payment
        );

        if (! $email) {
            return;
        }

        $transaction->loadMissing([
            'tokens',
            'meter',
        ]);

        Mail::to($email)->send(
            new WaterTokenGeneratedMail(
                $payment,
                $transaction
            )
        );
    }

    /**
     * Resolve where tenant email should be sent.
     *
     * Priority:
     *
     * 1. Tenant.email
     * 2. Tenant.user_id -> User.email
     * 3. Matching application user email
     */
    public function resolveTenantEmail(
        Payment $payment
    ): ?string {
        $payment->loadMissing(
            'tenant'
        );

        $tenant = $payment->tenant;

        if (! $tenant) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Primary email: tenants.email
        |--------------------------------------------------------------------------
        */

        if (
            filled($tenant->email) &&
            filter_var(
                $tenant->email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $tenant->email;
        }

        /*
        |--------------------------------------------------------------------------
        | Future direct Tenant → User relationship
        |--------------------------------------------------------------------------
        */

        if (
            isset($tenant->user_id) &&
            $tenant->user_id
        ) {
            $user = User::find(
                $tenant->user_id
            );

            if (
                $user &&
                filled($user->email)
            ) {
                return $user->email;
            }
        }

        return null;
    }
}
