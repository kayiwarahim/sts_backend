<?php

namespace App\Policies;

use App\Models\Settlement;
use App\Models\User;

class SettlementPolicy
{
    public function before(
        User $user,
        string $ability
    ): bool|null {

        return $user->isSuperAdmin()
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('settlements.view');
    }

    public function view(
        User $user,
        Settlement $settlement
    ): bool {

        return $user->can('settlements.view')
            && $settlement->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('settlements.create');
    }

    public function process(User $user): bool
    {
        return $user->can('settlements.process');
    }

    public function approve(User $user): bool
    {
        return $user->can('settlements.approve');
    }
}