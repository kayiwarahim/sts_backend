<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(
        User $user,
        string $ability
    ): ?bool {

        return $user->isSuperAdmin()
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(
        User $user,
        Payment $payment
    ): bool {

        return $user->can('payments.view')
            && $payment->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function verify(User $user): bool
    {
        return $user->can('payments.verify');
    }

    public function refund(
        User $user,
        Payment $payment
    ): bool {

        return $user->can('payments.refund')
            && $payment->organization_id
                === $user->organization_id;
    }

    public function reverse(
        User $user,
        Payment $payment
    ): bool {

        return $user->can('payments.reverse')
            && $payment->organization_id
                === $user->organization_id;
    }
}
