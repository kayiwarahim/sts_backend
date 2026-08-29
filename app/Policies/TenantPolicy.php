<?php

namespace App\Policies;

use App\Models\Tenancy;
use App\Models\User;

class TenancyPolicy
{
    public function before(
        User $user,
        string $ability
    ): ?bool {

        return $user->isSuperAdmin()
            ? true
            : null;
    }

    public function viewAny(
        User $user
    ): bool {
        return $user->can(
            'tenancies.view'
        );
    }

    public function view(
        User $user,
        Tenancy $tenancy
    ): bool {

        return $user->can(
            'tenancies.view'
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->can(
            'tenancies.create'
        );
    }

    public function update(
        User $user,
        Tenancy $tenancy
    ): bool {
        return $user->can(
            'tenancies.update'
        );
    }

    public function delete(
        User $user,
        Tenancy $tenancy
    ): bool {
        return $user->can(
            'tenancies.delete'
        );
    }
}
