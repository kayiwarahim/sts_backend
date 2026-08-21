<?php

namespace App\Services;

use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenancyService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query =
            Tenancy::query()
                ->with([
                    'tenant',
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ]);

        if (!$user->isSuperAdmin()) {
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
        $unit =
            Unit::with('property')
                ->findOrFail(
                    $data['unit_id']
                );

        $tenant =
            Tenant::findOrFail(
                $data['tenant_id']
            );

        $this->ensureUnitAccess(
            $user,
            $unit
        );

        /*
        |--------------------------------------------------------------------------
        | Tenant and unit must belong to same organization
        |--------------------------------------------------------------------------
        */

        if (
            (int) $tenant->organization_id !==
            (int) $unit->property->organization_id
        ) {
            abort(
                422,
                'Tenant and unit must belong to the same organization.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Organization access
        |--------------------------------------------------------------------------
        */

        if (
            !$user->isSuperAdmin() &&
            (int) $tenant->organization_id !==
            (int) $user->organization_id
        ) {
            abort(
                403,
                'Tenant does not belong to your organization.'
            );
        }

        $status =
            $data['status']
            ?? 'active';

        /*
        |--------------------------------------------------------------------------
        | Normalize active tenancy
        |--------------------------------------------------------------------------
        */

        if ($status === 'active') {
            $data['end_date'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Ended tenancy requires an end date
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    'ended',
                    'terminated',
                ],
                true
            ) &&
            empty(
                $data['end_date']
            )
        ) {
            $data['end_date'] =
                now()->toDateString();
        }

        return DB::transaction(
            function () use (
                $unit,
                $tenant,
                $data,
                $status
            ) {
                /*
                |--------------------------------------------------------------------------
                | Lock unit
                |--------------------------------------------------------------------------
                */

                $unit =
                    Unit::query()
                        ->with('property')
                        ->lockForUpdate()
                        ->findOrFail(
                            $unit->id
                        );

                /*
                |--------------------------------------------------------------------------
                | Only one active tenancy per unit
                |--------------------------------------------------------------------------
                */

                if ($status === 'active') {
                    $unitOccupied =
                        Tenancy::query()
                            ->where(
                                'unit_id',
                                $unit->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'end_date'
                            )
                            ->exists();

                    if ($unitOccupied) {
                        throw new RuntimeException(
                            'This unit already has an active tenancy.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Tenant cannot occupy two units simultaneously
                    |--------------------------------------------------------------------------
                    */

                    $tenantHasActiveTenancy =
                        Tenancy::query()
                            ->where(
                                'tenant_id',
                                $tenant->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'end_date'
                            )
                            ->exists();

                    if (
                        $tenantHasActiveTenancy
                    ) {
                        throw new RuntimeException(
                            'This tenant already has an active tenancy. Transfer the tenant instead.'
                        );
                    }
                }

                $tenancy =
                    Tenancy::create([
                        'unit_id' =>
                            $unit->id,

                        'tenant_id' =>
                            $tenant->id,

                        'start_date' =>
                            $data[
                                'start_date'
                            ],

                        'end_date' =>
                            $data[
                                'end_date'
                            ]
                            ?? null,

                        'status' =>
                            $status,

                        'notes' =>
                            $data[
                                'notes'
                            ]
                            ?? null,
                    ]);

                if (
                    $status ===
                    'active'
                ) {
                    $unit->update([
                        'status' =>
                            'occupied',
                    ]);
                }

                return $tenancy->load([
                    'tenant',
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ]);
            }
        );
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
        | Moving to another unit is NOT a normal update.
        |--------------------------------------------------------------------------
        */

        unset(
            $data['unit_id'],
            $data['tenant_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Normalize status / end date
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
                    'end_date'
                ] = null;
            }

            if (
                in_array(
                    $data[
                        'status'
                    ],
                    [
                        'ended',
                        'terminated',
                    ],
                    true
                ) &&
                empty(
                    $data[
                        'end_date'
                    ]
                )
            ) {
                $data[
                    'end_date'
                ] =
                    now()
                        ->toDateString();
            }
        }

        if (
            !empty(
                $data[
                    'end_date'
                ]
            ) &&
            (
                !isset(
                    $data[
                        'status'
                    ]
                ) ||
                $data[
                    'status'
                ] === 'active'
            )
        ) {
            $data['status'] =
                'ended';
        }

        return DB::transaction(
            function () use (
                $tenancy,
                $data
            ) {
                $tenancy =
                    Tenancy::query()
                        ->with('unit')
                        ->lockForUpdate()
                        ->findOrFail(
                            $tenancy->id
                        );

                /*
                |--------------------------------------------------------------------------
                | Prevent reactivating into an occupied unit
                |--------------------------------------------------------------------------
                */

                if (
                    (
                        $data['status']
                        ?? $tenancy->status
                    ) === 'active'
                ) {
                    $unitConflict =
                        Tenancy::query()
                            ->where(
                                'unit_id',
                                $tenancy->unit_id
                            )
                            ->where(
                                'id',
                                '!=',
                                $tenancy->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'end_date'
                            )
                            ->exists();

                    if ($unitConflict) {
                        throw new RuntimeException(
                            'This unit already has another active tenancy.'
                        );
                    }

                    $tenantConflict =
                        Tenancy::query()
                            ->where(
                                'tenant_id',
                                $tenancy->tenant_id
                            )
                            ->where(
                                'id',
                                '!=',
                                $tenancy->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'end_date'
                            )
                            ->exists();

                    if ($tenantConflict) {
                        throw new RuntimeException(
                            'This tenant already has another active tenancy.'
                        );
                    }
                }

                $tenancy->update(
                    $data
                );

                if (
                    $tenancy->status ===
                    'active'
                ) {
                    $tenancy
                        ->unit
                        ->update([
                            'status' =>
                                'occupied',
                        ]);
                } else {
                    $stillOccupied =
                        Tenancy::query()
                            ->where(
                                'unit_id',
                                $tenancy->unit_id
                            )
                            ->where(
                                'id',
                                '!=',
                                $tenancy->id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'end_date'
                            )
                            ->exists();

                    if (
                        !$stillOccupied
                    ) {
                        $tenancy
                            ->unit
                            ->update([
                                'status' =>
                                    'vacant',
                            ]);
                    }
                }

                return $tenancy->fresh([
                    'tenant',
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer Tenant
    |--------------------------------------------------------------------------
    |
    | Ends old tenancy and creates a new active tenancy.
    |--------------------------------------------------------------------------
    */

    public function transfer(
        User $user,
        Tenancy $tenancy,
        Unit $targetUnit,
        ?string $transferDate = null,
        ?string $notes = null
    ): Tenancy {
        $this->ensureAccess(
            $user,
            $tenancy
        );

        $this->ensureUnitAccess(
            $user,
            $targetUnit
        );

        $transferDate =
            $transferDate
            ?? now()->toDateString();

        return DB::transaction(
            function () use (
                $tenancy,
                $targetUnit,
                $transferDate,
                $notes
            ) {
                $tenancy =
                    Tenancy::query()
                        ->with([
                            'tenant',
                            'unit.property',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $tenancy->id
                        );

                $targetUnit =
                    Unit::query()
                        ->with('property')
                        ->lockForUpdate()
                        ->findOrFail(
                            $targetUnit->id
                        );

                /*
                |--------------------------------------------------------------------------
                | Only current tenancy can move
                |--------------------------------------------------------------------------
                */

                if (
                    $tenancy->status !==
                    'active' ||
                    $tenancy->end_date !==
                    null
                ) {
                    throw new RuntimeException(
                        'Only an active tenancy can be transferred.'
                    );
                }

                if (
                    (int) $tenancy->unit_id ===
                    (int) $targetUnit->id
                ) {
                    throw new RuntimeException(
                        'The tenant is already assigned to this unit.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Same organization only
                |--------------------------------------------------------------------------
                */

                if (
                    (int)
                    $tenancy
                        ->unit
                        ->property
                        ->organization_id
                    !==
                    (int)
                    $targetUnit
                        ->property
                        ->organization_id
                ) {
                    throw new RuntimeException(
                        'The target unit must belong to the same organization.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Date validation
                |--------------------------------------------------------------------------
                */

                $oldStart =
                    Carbon::parse(
                        $tenancy->start_date
                    )
                        ->startOfDay();

                $moveDate =
                    Carbon::parse(
                        $transferDate
                    )
                        ->startOfDay();

                if (
                    $moveDate->lt(
                        $oldStart
                    )
                ) {
                    throw new RuntimeException(
                        'Transfer date cannot be earlier than the tenancy start date.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Target unit must be free
                |--------------------------------------------------------------------------
                */

                $targetOccupied =
                    Tenancy::query()
                        ->where(
                            'unit_id',
                            $targetUnit->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereNull(
                            'end_date'
                        )
                        ->exists();

                if ($targetOccupied) {
                    throw new RuntimeException(
                        'The target unit already has an active tenancy.'
                    );
                }

                $oldUnit =
                    $tenancy->unit;

                /*
                |--------------------------------------------------------------------------
                | End previous tenancy
                |--------------------------------------------------------------------------
                */

                $tenancy->update([
                    'status' =>
                        'ended',

                    'end_date' =>
                        $transferDate,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Create replacement tenancy
                |--------------------------------------------------------------------------
                */

                $newTenancy =
                    Tenancy::create([
                        'tenant_id' =>
                            $tenancy
                                ->tenant_id,

                        'unit_id' =>
                            $targetUnit
                                ->id,

                        'start_date' =>
                            $transferDate,

                        'end_date' =>
                            null,

                        'status' =>
                            'active',

                        'notes' =>
                            $notes
                            ??
                            'Transferred from tenancy #'
                            . $tenancy->id
                            . ' / unit #'
                            . $oldUnit->id,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Unit occupancy
                |--------------------------------------------------------------------------
                */

                $oldUnit->update([
                    'status' =>
                        'vacant',
                ]);

                $targetUnit->update([
                    'status' =>
                        'occupied',
                ]);

                return $newTenancy->load([
                    'tenant',
                    'unit.property',
                    'unit.activeMeterAssignment.meter',
                ]);
            }
        );
    }

    public function delete(
        User $user,
        Tenancy $tenancy
    ): void {
        $this->ensureAccess(
            $user,
            $tenancy
        );

        if (
            $tenancy->status ===
            'active'
        ) {
            abort(
                422,
                'An active tenancy cannot be deleted. End the tenancy instead.'
            );
        }

        DB::transaction(
            function () use (
                $tenancy
            ) {
                $tenancy->delete();
            }
        );
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
        $unit->loadMissing(
            'property'
        );

        if (
            !$user->isSuperAdmin() &&
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
                'Unauthorized tenancy access.'
            );
        }
    }
}