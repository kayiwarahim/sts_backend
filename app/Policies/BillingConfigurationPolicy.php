<?php

namespace App\Policies;

use App\Models\BillingConfiguration;
use App\Models\User;

class BillingConfigurationPolicy
{
    public function before(
        User $user,
        string $ability
    ): bool|null {
        return $user->isSuperAdmin()
            ? true
            : null;
    }

    public function viewAny(
        User $user
    ): bool {
        return $user->can(
            'billing_configurations.view'
        );
    }

    public function view(
        User $user,
        BillingConfiguration $configuration
    ): bool {
        return $user->can(
            'billing_configurations.view'
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->can(
            'billing_configurations.create'
        );
    }

    public function update(
        User $user,
        BillingConfiguration $configuration
    ): bool {
        return $user->can(
            'billing_configurations.update'
        );
    }

    public function delete(
        User $user,
        BillingConfiguration $configuration
    ): bool {
        return $user->can(
            'billing_configurations.delete'
        );
    }
}