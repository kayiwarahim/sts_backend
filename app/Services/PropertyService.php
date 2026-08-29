<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PropertyService
{
    public function list(
        User $user,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {

        $query = Property::query()
            ->with('organization');

        if (! $user->isSuperAdmin()) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        return $query
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'property_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'city',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'district',
                            'like',
                            "%{$search}%"
                        );
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        User $user,
        Property $property
    ): Property {

        $this->ensureOrganizationAccess(
            $user,
            $property
        );

        return $property->load([
            'organization',
            'units',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): Property {

        if (! $user->isSuperAdmin()) {
            $data['organization_id'] =
                $user->organization_id;
        }

        return DB::transaction(function () use ($data) {
            return Property::create($data);
        });
    }

    public function update(
        User $user,
        Property $property,
        array $data
    ): Property {

        $this->ensureOrganizationAccess(
            $user,
            $property
        );

        if (! $user->isSuperAdmin()) {
            unset($data['organization_id']);
        }

        return DB::transaction(function () use (
            $property,
            $data
        ) {
            $property->update($data);

            return $property->fresh();
        });
    }

    public function delete(
        User $user,
        Property $property
    ): void {

        $this->ensureOrganizationAccess(
            $user,
            $property
        );

        DB::transaction(function () use ($property) {
            $property->delete();
        });
    }

    protected function ensureOrganizationAccess(
        User $user,
        Property $property
    ): void {

        if (
            ! $user->isSuperAdmin() &&
            $property->organization_id
                !== $user->organization_id
        ) {
            abort(403, 'Unauthorized property access.');
        }
    }
}
