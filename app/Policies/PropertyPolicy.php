<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function before(
        User $user,
        string $ability
    ): bool|null {

        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('properties.view');
    }

    public function view(
        User $user,
        Property $property
    ): bool {

        return $user->can('properties.view')
            && $property->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('properties.create');
    }

    public function update(
        User $user,
        Property $property
    ): bool {

        return $user->can('properties.update')
            && $property->organization_id
                === $user->organization_id;
    }

    public function delete(
        User $user,
        Property $property
    ): bool {

        return $user->can('properties.delete')
            && $property->organization_id
                === $user->organization_id;
    }
}