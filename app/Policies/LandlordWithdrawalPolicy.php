<?php

namespace App\Policies;

use App\Models\LandlordWithdrawal;
use App\Models\User;

class LandlordWithdrawalPolicy
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
        return $user->can('withdrawals.view');
    }

    public function view(
        User $user,
        LandlordWithdrawal $withdrawal
    ): bool {

        return $user->can('withdrawals.view')
            && $withdrawal->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('withdrawals.create');
    }

    public function approve(User $user): bool
    {
        return $user->can('withdrawals.approve');
    }

    public function process(User $user): bool
    {
        return $user->can('withdrawals.process');
    }

    public function cancel(
        User $user,
        LandlordWithdrawal $withdrawal
    ): bool {

        return $user->can('withdrawals.cancel')
            && $withdrawal->organization_id
                === $user->organization_id;
    }
}
