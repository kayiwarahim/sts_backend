<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterVending;

class WaterVendingPolicy
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
        return $user->can('water-vending.view');
    }

    public function view(
        User $user,
        WaterVending $vending
    ): bool {

        return $user->can('water-vending.view')
            && $vending->property
                ->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('water-vending.create');
    }

    public function process(User $user): bool
    {
        return $user->can('water-vending.process');
    }

    public function cancel(
        User $user,
        WaterVending $vending
    ): bool {

        return $user->can('water-vending.cancel')
            && $vending->property
                ->organization_id
                === $user->organization_id;
    }
}