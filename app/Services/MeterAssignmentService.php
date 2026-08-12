<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MeterAssignmentService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {

        $query = MeterAssignment::query()
            ->with([
                'meter',
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
        MeterAssignment $assignment
    ): MeterAssignment {

        $this->ensureAccess(
            $user,
            $assignment
        );

        return $assignment->load([
            'meter',
            'unit.property',
            'unit.activeTenancy.tenant',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): MeterAssignment {

        $unit = Unit::with('property')
            ->findOrFail(
                $data['unit_id']
            );

        $meter = Meter::findOrFail(
            $data['meter_id']
        );

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        /*
        |--------------------------------------------------------------------------
        | Meter must belong to same organization
        |--------------------------------------------------------------------------
        */

        if (
            !$user->isSuperAdmin() &&
            $meter->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Meter does not belong to your organization.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent meter from being assigned twice
        |--------------------------------------------------------------------------
        */

        if (
            ($data['status'] ?? 'active') === 'active' &&
            $meter->assignments()
                ->where('status', 'active')
                ->exists()
        ) {
            abort(
                422,
                'This meter is already assigned to another unit.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Only one active meter per unit
        |--------------------------------------------------------------------------
        */

        if (
            ($data['status'] ?? 'active') === 'active' &&
            $unit->meterAssignments()
                ->where('status', 'active')
                ->exists()
        ) {
            abort(
                422,
                'This unit already has an active meter.'
            );
        }

        return DB::transaction(
            function () use ($data) {

                return MeterAssignment::create([
                    'meter_id' =>
                        $data['meter_id'],

                    'unit_id' =>
                        $data['unit_id'],

                    'assigned_at' =>
                        $data['assigned_at'],

                    'unassigned_at' =>
                        $data['unassigned_at']
                        ?? null,

                    'status' =>
                        $data['status']
                        ?? 'active',

                    'notes' =>
                        $data['notes']
                        ?? null,
                ]);
            }
        );
    }

    public function update(
        User $user,
        MeterAssignment $assignment,
        array $data
    ): MeterAssignment {

        $this->ensureAccess(
            $user,
            $assignment
        );

        /*
        |--------------------------------------------------------------------------
        | Automatically set unassigned date
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['status']) &&
            $data['status'] === 'ended' &&
            empty($data['unassigned_at'])
        ) {
            $data['unassigned_at'] = now();
        }

        return DB::transaction(
            function () use (
                $assignment,
                $data
            ) {

                $assignment->update($data);

                /*
                |--------------------------------------------------------------------------
                | Update meter status when assignment ends
                |--------------------------------------------------------------------------
                */

                if (
                    isset($data['status']) &&
                    $data['status'] === 'ended'
                ) {
                    $assignment->meter->update([
                        'status' => 'inactive',
                    ]);
                }

                return $assignment->fresh([
                    'meter',
                    'unit.property',
                ]);
            }
        );
    }

    public function delete(
        User $user,
        MeterAssignment $assignment
    ): void {

        $this->ensureAccess(
            $user,
            $assignment
        );

        if ($assignment->status === 'active') {
            abort(
                422,
                'An active meter assignment cannot be deleted. End it first.'
            );
        }

        DB::transaction(function () use (
            $assignment
        ) {
            $assignment->delete();
        });
    }

    protected function ensureAccess(
        User $user,
        MeterAssignment $assignment
    ): void {

        $assignment->loadMissing(
            'unit.property'
        );

        $this->ensureUnitAccess(
            $user,
            $assignment->unit
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
                'Unauthorized meter assignment access.'
            );
        }
    }
}