<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function list(
        User $user,
        Property $property,
        int $perPage = 20
    ): LengthAwarePaginator {

        $this->ensurePropertyAccess(
            $user,
            $property
        );

        return Unit::query()
            ->where(
                'property_id',
                $property->id
            )
            ->with('property')
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        User $user,
        Unit $unit
    ): Unit {

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        return $unit->load([
            'property',
            'tenancies',
            'meterAssignments',
        ]);
    }

    public function create(
        User $user,
        Property $property,
        array $data
    ): Unit {

        $this->ensurePropertyAccess(
            $user,
            $property
        );

        $data['property_id'] = $property->id;

        return DB::transaction(function () use ($data) {
            return Unit::create($data);
        });
    }

    public function update(
        User $user,
        Unit $unit,
        array $data
    ): Unit {

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        unset($data['property_id']);

        return DB::transaction(function () use (
            $unit,
            $data
        ) {
            $unit->update($data);

            return $unit->fresh();
        });
    }

    public function delete(
        User $user,
        Unit $unit
    ): void {

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        DB::transaction(function () use ($unit) {
            $unit->delete();
        });
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

    protected function ensureUnitAccess(
        User $user,
        Unit $unit
    ): void {

        $this->ensurePropertyAccess(
            $user,
            $unit->property
        );
    }
}
