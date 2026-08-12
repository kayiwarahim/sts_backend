<?php

namespace App\Services;

use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TenancyService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {

        $query = Tenancy::query()
            ->with([
                'tenant',
                'unit.property',
            ]);

        if (!$user->isSuperAdmin()) {
            $query->whereHas(
                'unit.property',
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

    public function find(
        User $user,
        Tenancy $tenancy
    ): Tenancy {

        $this->ensureAccess(
            $user,
            $tenancy
        );

        return $tenancy->load([
            'tenant',
            'unit.property',
            'unit.activeMeterAssignment.meter',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): Tenancy {

        $unit = Unit::with('property')
            ->findOrFail(
                $data['unit_id']
            );

        $tenant = Tenant::findOrFail(
            $data['tenant_id']
        );

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        /*
        |--------------------------------------------------------------------------
        | Tenant must belong to same organization
        |--------------------------------------------------------------------------
        */

        if (
            !$user->isSuperAdmin() &&
            $tenant->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Tenant does not belong to your organization.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Only one active tenancy per unit
        |--------------------------------------------------------------------------
        */

        if (
            ($data['status'] ?? 'active') === 'active' &&
            Unit::findOrFail($unit->id)
                ->tenancies()
                ->where('status', 'active')
                ->exists()
        ) {
            abort(
                422,
                'This unit already has an active tenancy.'
            );
        }

        return DB::transaction(function () use ($data) {

            return Tenancy::create([
                'unit_id' => $data['unit_id'],
                'tenant_id' => $data['tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function update(
        User $user,
        Tenancy $tenancy,
        array $data
    ): Tenancy {

        $this->ensureAccess(
            $user,
            $tenancy
        );

        /*
        |--------------------------------------------------------------------------
        | Automatically set end date when tenancy ends
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['status']) &&
            in_array(
                $data['status'],
                ['ended', 'terminated']
            ) &&
            empty($data['end_date'])
        ) {
            $data['end_date'] = now()->toDateString();
        }

        return DB::transaction(function () use (
            $tenancy,
            $data
        ) {

            $tenancy->update($data);

            return $tenancy->fresh([
                'tenant',
                'unit.property',
            ]);
        });
    }

    public function delete(
        User $user,
        Tenancy $tenancy
    ): void {

        $this->ensureAccess(
            $user,
            $tenancy
        );

        /*
        |--------------------------------------------------------------------------
        | Historical records should not normally be deleted.
        |--------------------------------------------------------------------------
        */

        if ($tenancy->status === 'active') {
            abort(
                422,
                'An active tenancy cannot be deleted. End the tenancy instead.'
            );
        }

        DB::transaction(function () use ($tenancy) {
            $tenancy->delete();
        });
    }

    protected function ensureAccess(
        User $user,
        Tenancy $tenancy
    ): void {

        $tenancy->loadMissing(
            'unit.property'
        );

        $this->ensureUnitAccess(
            $user,
            $tenancy->unit
        );
    }

    protected function ensureUnitAccess(
        User $user,
        Unit $unit
    ): void {

        $unit->loadMissing('property');

        if (
            !$user->isSuperAdmin() &&
            $unit->property->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized tenancy access.'
            );
        }
    }
}