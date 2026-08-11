<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
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
        return $user->can('tenants.view');
    }

    public function view(
        User $user,
        Tenant $tenant
    ): bool {

        return $user->can('tenants.view')
            && $tenant->organization_id
                === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('tenants.create');
    }

    public function update(
        User $user,
        Tenant $tenant
    ): bool {

        return $user->can('tenants.update')
            && $tenant->organization_id
                === $user->organization_id;
    }

    public function delete(
        User $user,
        Tenant $tenant
    ): bool {

        return $user->can('tenants.delete')
            && $tenant->organization_id
                === $user->organization_id;
    }
}