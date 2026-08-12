<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

class MeterPolicy
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
        return $user->can('meters.view');
    }

    public function view(
        User $user,
        Meter $meter
    ): bool {

        return $user->can('meters.view')
            && (
                $user->isSuperAdmin() ||
                $meter->organization_id
                    === $user->organization_id
            );
    }

    public function create(User $user): bool
    {
        return $user->can('meters.create');
    }

    public function update(
        User $user,
        Meter $meter
    ): bool {

        return $user->can('meters.update')
            && (
                $user->isSuperAdmin() ||
                $meter->organization_id
                    === $user->organization_id
            );
    }

    public function delete(
        User $user,
        Meter $meter
    ): bool {

        return $user->can('meters.delete')
            && (
                $user->isSuperAdmin() ||
                $meter->organization_id
                    === $user->organization_id
            );
    }
}