<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
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
        return $user->can('units.view');
    }

    public function view(
        User $user,
        Unit $unit
    ): bool {

        return $user->can('units.view')
            && $unit->property
                ->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    public function update(
        User $user,
        Unit $unit
    ): bool {

        return $user->can('units.update')
            && $unit->property
                ->organization_id
                === $user->organization_id;
    }

    public function delete(
        User $user,
        Unit $unit
    ): bool {

        return $user->can('units.delete')
            && $unit->property
                ->organization_id
                === $user->organization_id;
    }
}