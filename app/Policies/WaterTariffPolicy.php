<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterTariff;

class WaterTariffPolicy
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
            'water_tariffs.view'
        );
    }

    public function view(
        User $user,
        WaterTariff $tariff
    ): bool {
        return $user->can(
            'water_tariffs.view'
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->can(
            'water_tariffs.create'
        );
    }

    public function update(
        User $user,
        WaterTariff $tariff
    ): bool {
        return $user->can(
            'water_tariffs.update'
        );
    }

    public function delete(
        User $user,
        WaterTariff $tariff
    ): bool {
        return $user->can(
            'water_tariffs.delete'
        );
    }
}
