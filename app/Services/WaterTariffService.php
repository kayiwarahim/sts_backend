<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Models\WaterTariff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WaterTariffService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {

        $query = WaterTariff::query()
            ->with('property');

        if (! $user->isSuperAdmin()) {
            $query->whereHas(
                'property',
                function ($q) use ($user) {
                    $q->where(
                        'organization_id',
                        $user->organization_id
                    );
                }
            );
        }

        return $query
            ->latest()
            ->paginate($perPage);
    }

    public function create(
        User $user,
        array $data
    ): WaterTariff {

        $property = Property::findOrFail(
            $data['property_id']
        );

        $this->ensurePropertyAccess(
            $user,
            $property
        );

        return DB::transaction(
            function () use (
                $data
            ) {
                return WaterTariff::create([
                    'property_id' => $data['property_id'],

                    'name' => $data['name'],

                    'price_per_m3' => $data['price_per_m3'],

                    'currency' => $data['currency'] ?? 'UGX',

                    'effective_from' => $data['effective_from'],

                    'effective_to' => $data['effective_to'] ?? null,

                    'status' => $data['status'] ?? 'active',

                    'notes' => $data['notes'] ?? null,
                ]);
            }
        );
    }

    public function update(
        User $user,
        WaterTariff $tariff,
        array $data
    ): WaterTariff {

        $tariff->loadMissing(
            'property'
        );

        $this->ensurePropertyAccess(
            $user,
            $tariff->property
        );

        $tariff->update(
            $data
        );

        return $tariff->fresh();
    }

    protected function ensurePropertyAccess(
        User $user,
        Property $property
    ): void {

        if (
            ! $user->isSuperAdmin() &&
            $property->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized property access.'
            );
        }
    }
}
