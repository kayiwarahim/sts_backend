<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeterAssignmentService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query =
            MeterAssignment::query()
                ->with([
                    'meter.organization',
                    'unit.property',
                    'unit.activeTenancy.tenant',
                ]);

        if (
            ! $user->isSuperAdmin()
        ) {
            $query->whereHas(
                'unit.property',
                function ($query) use ($user) {
                    $query->where(
                        'organization_id',
                        $user->organization_id
                    );
                }
            );
        }

        return $query
            ->latest()
            ->paginate(
                $perPage
            );
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
            'meter.organization',
            'unit.property',
            'unit.activeTenancy.tenant',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): MeterAssignment {
        $unit =
            Unit::with('property')
                ->findOrFail(
                    $data['unit_id']
                );

        $meter =
            Meter::findOrFail(
                $data['meter_id']
            );

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        /*
        |--------------------------------------------------------------------------
        | Organization inventory rule
        |--------------------------------------------------------------------------
        |
        | Meter may only be assigned inside the organization that owns it.
        |--------------------------------------------------------------------------
        */

        if (
            (int)
            $meter->organization_id
            !==
            (int)
            $unit
                ->property
                ->organization_id
        ) {
            abort(
                422,
                'The meter and unit must belong to the same organization.'
            );
        }

        $status =
            $data['status']
            ?? 'active';

        $assignedAt =
            $data['assigned_at']
            ?? now();

        if (
            $status ===
            'active'
        ) {
            $unassignedAt =
                null;
        } else {
            $unassignedAt =
                $data[
                    'unassigned_at'
                ]
                ?? now();
        }

        if (
            $unassignedAt
            &&
            Carbon::parse(
                $unassignedAt
            )->lt(
                Carbon::parse(
                    $assignedAt
                )
            )
        ) {
            throw new RuntimeException(
                'Unassigned date cannot be earlier than assigned date.'
            );
        }

        return DB::transaction(
            function () use (
                $meter,
                $unit,
                $assignedAt,
                $unassignedAt,
                $status,
                $data
            ) {
                /*
                |--------------------------------------------------------------------------
                | Active meter cannot already be elsewhere
                |--------------------------------------------------------------------------
                */

                if (
                    $status ===
                    'active'
                ) {
                    $meterBusy =
                        MeterAssignment::query()
                            ->where(
                                'meter_id',
                                $meter->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'unassigned_at'
                            )
                            ->lockForUpdate()
                            ->exists();

                    if ($meterBusy) {
                        throw new RuntimeException(
                            'This meter is already assigned to another unit.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Unit only gets one active meter
                    |--------------------------------------------------------------------------
                    */

                    $unitHasMeter =
                        MeterAssignment::query()
                            ->where(
                                'unit_id',
                                $unit->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'unassigned_at'
                            )
                            ->lockForUpdate()
                            ->exists();

                    if (
                        $unitHasMeter
                    ) {
                        throw new RuntimeException(
                            'This unit already has an active meter.'
                        );
                    }
                }

                $assignment =
                    MeterAssignment::create([
                        'meter_id' => $meter->id,

                        'unit_id' => $unit->id,

                        'assigned_at' => $assignedAt,

                        'unassigned_at' => $unassignedAt,

                        'status' => $status,

                        'notes' => $data[
                                'notes'
                            ]
                            ?? null,
                    ]);

                return $assignment->load([
                    'meter.organization',
                    'unit.property',
                    'unit.activeTenancy.tenant',
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
        | Reassignment must use reassign()
        |--------------------------------------------------------------------------
        */

        unset(
            $data['unit_id'],
            $data['meter_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Synchronize status and unassigned date
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $data['status']
            )
        ) {
            if (
                $data['status'] ===
                'active'
            ) {
                $data[
                    'unassigned_at'
                ] = null;
            }

            if (
                $data['status'] ===
                'ended' &&
                empty(
                    $data[
                        'unassigned_at'
                    ]
                )
            ) {
                $data[
                    'unassigned_at'
                ] = now();
            }
        }

        if (
            ! empty(
                $data[
                    'unassigned_at'
                ]
            )
        ) {
            $data['status'] =
                'ended';
        }

        return DB::transaction(
            function () use (
                $assignment,
                $data
            ) {
                $assignment =
                    MeterAssignment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $assignment->id
                        );

                $effectiveAssignedAt =
                    $assignment
                        ->assigned_at;

                if (
                    ! empty(
                        $data[
                            'unassigned_at'
                        ]
                    ) &&
                    Carbon::parse(
                        $data[
                            'unassigned_at'
                        ]
                    )->lt(
                        Carbon::parse(
                            $effectiveAssignedAt
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'Unassigned date cannot be earlier than assigned date.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate active assignment when reactivating
                |--------------------------------------------------------------------------
                */

                if (
                    (
                        $data['status']
                        ?? $assignment->status
                    ) === 'active'
                ) {
                    $meterConflict =
                        MeterAssignment::query()
                            ->where(
                                'meter_id',
                                $assignment
                                    ->meter_id
                            )
                            ->where(
                                'id',
                                '!=',
                                $assignment
                                    ->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'unassigned_at'
                            )
                            ->exists();

                    if (
                        $meterConflict
                    ) {
                        throw new RuntimeException(
                            'This meter already has another active assignment.'
                        );
                    }

                    $unitConflict =
                        MeterAssignment::query()
                            ->where(
                                'unit_id',
                                $assignment
                                    ->unit_id
                            )
                            ->where(
                                'id',
                                '!=',
                                $assignment
                                    ->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'unassigned_at'
                            )
                            ->exists();

                    if (
                        $unitConflict
                    ) {
                        throw new RuntimeException(
                            'This unit already has another active meter.'
                        );
                    }
                }

                $assignment->update(
                    $data
                );

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT:
                |
                | Ending an assignment does NOT deactivate the physical meter.
                | The meter remains organization inventory.
                |--------------------------------------------------------------------------
                */

                return $assignment->fresh([
                    'meter.organization',
                    'unit.property',
                    'unit.activeTenancy.tenant',
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reassign Meter
    |--------------------------------------------------------------------------
    */

    public function reassign(
        User $user,
        MeterAssignment $assignment,
        Unit $targetUnit,
        ?string $assignedAt = null
    ): MeterAssignment {
        $this->ensureAccess(
            $user,
            $assignment
        );

        $this->ensureUnitAccess(
            $user,
            $targetUnit
        );

        return DB::transaction(
            function () use (
                $assignment,
                $targetUnit,
                $assignedAt
            ) {
                $assignment =
                    MeterAssignment::query()
                        ->with([
                            'meter',
                            'unit.property',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $assignment->id
                        );

                $targetUnit =
                    Unit::query()
                        ->with('property')
                        ->lockForUpdate()
                        ->findOrFail(
                            $targetUnit->id
                        );

                if (
                    $assignment->status !==
                    'active' ||
                    $assignment->unassigned_at !==
                    null
                ) {
                    throw new RuntimeException(
                        'Only a currently active meter assignment can be reassigned.'
                    );
                }

                if (
                    (int)
                    $assignment->unit_id
                    ===
                    (int)
                    $targetUnit->id
                ) {
                    throw new RuntimeException(
                        'This meter is already assigned to the selected unit.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Meter can only move within its owning organization
                |--------------------------------------------------------------------------
                */

                if (
                    (int)
                    $assignment
                        ->meter
                        ->organization_id
                    !==
                    (int)
                    $targetUnit
                        ->property
                        ->organization_id
                ) {
                    throw new RuntimeException(
                        'The target unit must belong to the organization that owns this meter.'
                    );
                }

                $assignedAt =
                    $assignedAt
                    ?? now();

                if (
                    Carbon::parse(
                        $assignedAt
                    )->lt(
                        Carbon::parse(
                            $assignment
                                ->assigned_at
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'Reassignment date cannot be earlier than the original assignment date.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Target unit must be available
                |--------------------------------------------------------------------------
                */

                $targetHasMeter =
                    MeterAssignment::query()
                        ->where(
                            'unit_id',
                            $targetUnit->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereNull(
                            'unassigned_at'
                        )
                        ->lockForUpdate()
                        ->exists();

                if (
                    $targetHasMeter
                ) {
                    throw new RuntimeException(
                        'The target unit already has an active meter.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | End old assignment
                |--------------------------------------------------------------------------
                */

                $assignment->update([
                    'status' => 'ended',

                    'unassigned_at' => $assignedAt,
                ]);

                /*
                |--------------------------------------------------------------------------
                | New active assignment
                |--------------------------------------------------------------------------
                */

                $newAssignment =
                    MeterAssignment::create([
                        'meter_id' => $assignment
                            ->meter_id,

                        'unit_id' => $targetUnit
                            ->id,

                        'assigned_at' => $assignedAt,

                        'unassigned_at' => null,

                        'status' => 'active',

                        'notes' => 'Reassigned from assignment #'
                            .$assignment->id
                            .' / unit #'
                            .$assignment->unit_id,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Meter remains operational inventory
                |--------------------------------------------------------------------------
                */

                if (
                    $assignment
                        ->meter
                        ->status ===
                    'inactive'
                ) {
                    $assignment
                        ->meter
                        ->update([
                            'status' => 'active',
                        ]);
                }

                return $newAssignment->load([
                    'meter.organization',
                    'unit.property',
                    'unit.activeTenancy.tenant',
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

        if (
            $assignment->status ===
            'active'
        ) {
            abort(
                422,
                'An active meter assignment cannot be deleted. End it first.'
            );
        }

        DB::transaction(
            function () use (
                $assignment
            ) {
                $assignment->delete();
            }
        );
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
        $unit->loadMissing(
            'property'
        );

        if (
            ! $user->isSuperAdmin() &&
            (int)
            $unit
                ->property
                ->organization_id
            !==
            (int)
            $user
                ->organization_id
        ) {
            abort(
                403,
                'Unauthorized meter assignment access.'
            );
        }
    }
}
