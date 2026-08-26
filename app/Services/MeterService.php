<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeterService
{
    public function list(
        User $user,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {

        $query = Meter::query()->with(['organization', 'assignments.unit.property', ]);

        /*
        |--------------------------------------------------------------------------
        | Organization Scope
        |--------------------------------------------------------------------------
        |
        | Super Admin:
        |   Can see inventory from every organization.
        |
        | Landlord / organization users:
        |   Can only see meters owned by their organization.
        |--------------------------------------------------------------------------
        */

        if (!$user->isSuperAdmin()) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        return $query
            ->when(
                $search,
                function ($query) use ($search) {

                    $query->where(
                        function ($q) use ($search) {

                            $q->where(
                                'meter_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'serial_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'manufacturer',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'model',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
                }
            )
            ->latest()
            ->paginate(
                $perPage
            );
    }

    public function find(
        User $user,
        Meter $meter
    ): Meter {

        $this->ensureAccess(
            $user,
            $meter
        );

        return $meter->load([
            'organization',
            'assignments.unit.property',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): Meter {

        /*
        |--------------------------------------------------------------------------
        | Determine Owning Organization
        |--------------------------------------------------------------------------
        |
        | A meter is an organization-owned inventory asset.
        |
        | Super Admin:
        |   organization_id must come from the validated request.
        |
        | Landlord:
        |   organization_id is always forced from the authenticated account.
        |
        | The landlord cannot spoof organization ownership.
        |--------------------------------------------------------------------------
        */

        if (
            $user->isSuperAdmin()
        ) {
            $organizationId =
                $data[
                    'organization_id'
                ]
                ?? null;
        } else {
            $organizationId =
                $user->organization_id;
        }

        if (
            !$organizationId
        ) {
            throw new RuntimeException(
                'Unable to determine the meter owning organization.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Force Organization Ownership
        |--------------------------------------------------------------------------
        */

        $data[
            'organization_id'
        ] = $organizationId;

        /*
        |--------------------------------------------------------------------------
        | Defaults
        |--------------------------------------------------------------------------
        */

        $data[
            'meter_type'
        ] =
            $data[
                'meter_type'
            ]
            ?? '2';

        $data[
            'status'
        ] =
            $data[
                'status'
            ]
            ?? 'active';

        /*
        |--------------------------------------------------------------------------
        | Create Inventory Meter
        |--------------------------------------------------------------------------
        */

        return DB::transaction(
            function () use ($data) {

                return Meter::create(
                    $data
                );
            }
        );
    }

    public function update(
        User $user,
        Meter $meter,
        array $data
    ): Meter {

        $this->ensureAccess(
            $user,
            $meter
        );

        /*
        |--------------------------------------------------------------------------
        | Organization Ownership Is Not Editable Here
        |--------------------------------------------------------------------------
        |
        | A normal meter update should only change meter attributes.
        |
        | Moving a meter from one organization to another should later use
        | a dedicated audited "transfer inventory" operation.
        |--------------------------------------------------------------------------
        */

        unset(
            $data[
                'organization_id'
            ]
        );

        return DB::transaction(
            function () use (
                $meter,
                $data
            ) {

                $meter->update(
                    $data
                );

                return $meter
                    ->fresh();
            }
        );
    }

    public function delete(
        User $user,
        Meter $meter
    ): void {

        $this->ensureAccess(
            $user,
            $meter
        );

        /*
        |--------------------------------------------------------------------------
        | Active Assignment Guard
        |--------------------------------------------------------------------------
        |
        | Never delete a meter that is currently assigned.
        |--------------------------------------------------------------------------
        */

        if (
            $meter
                ->assignments()
                ->whereNull(
                    'unassigned_at'
                )
                ->exists()
        ) {
            abort(
                422,
                'Cannot delete a meter that is currently assigned.'
            );
        }

        DB::transaction(
            function () use (
                $meter
            ) {

                $meter->delete();
            }
        );
    }

    protected function ensureAccess(
        User $user,
        Meter $meter
    ): void {

        if (
            !$user->isSuperAdmin()
            &&
            (int) $meter->organization_id
                !==
            (int) $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized meter access.'
            );
        }
    }
}