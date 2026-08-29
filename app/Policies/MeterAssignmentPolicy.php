<?php

namespace App\Policies;

use App\Models\MeterAssignment;
use App\Models\User;

class MeterAssignmentPolicy
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
            'meter_assignments.view'
        );
    }

    public function view(
        User $user,
        MeterAssignment $assignment
    ): bool {
        return $user->can(
            'meter_assignments.view'
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->can(
            'meter_assignments.create'
        );
    }

    public function update(
        User $user,
        MeterAssignment $assignment
    ): bool {
        return $user->can(
            'meter_assignments.update'
        );
    }

    public function delete(
        User $user,
        MeterAssignment $assignment
    ): bool {
        return $user->can(
            'meter_assignments.delete'
        );
    }
}
