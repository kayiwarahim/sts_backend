<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
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
        return $user->can('organizations.view');
    }

    public function view(
        User $user,
        Organization $organization
    ): bool {
        return $user->can('organizations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('organizations.create');
    }

    public function update(
        User $user,
        Organization $organization
    ): bool {
        return $user->can('organizations.update');
    }

    public function delete(
        User $user,
        Organization $organization
    ): bool {
        return $user->can('organizations.delete');
    }
}