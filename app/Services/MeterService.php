<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MeterService
{
    public function list(
        User $user,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {

        $query = Meter::query()
            ->with([
                'organization',
                'assignments.unit.property',
            ]);

        if (!$user->isSuperAdmin()) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        return $query
            ->when($search, function ($query) use ($search) {

                $query->where(function ($q) use ($search) {

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
                    );

                });

            })
            ->latest()
            ->paginate($perPage);
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

        if (!$user->isSuperAdmin()) {
            $data['organization_id'] =
                $user->organization_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Default STS meter type
        |--------------------------------------------------------------------------
        */

        $data['meter_type'] =
            $data['meter_type'] ?? '2';

        return DB::transaction(function () use ($data) {
            return Meter::create($data);
        });
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
        | Organization cannot be changed by landlord
        |--------------------------------------------------------------------------
        */

        unset($data['organization_id']);

        return DB::transaction(function () use (
            $meter,
            $data
        ) {

            $meter->update($data);

            return $meter->fresh();
        });
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
        | Don't delete an installed meter casually.
        |--------------------------------------------------------------------------
        */

        if (
            $meter->assignments()
                ->whereNull('unassigned_at')
                ->exists()
        ) {
            abort(
                422,
                'Cannot delete a meter that is currently assigned.'
            );
        }

        DB::transaction(function () use ($meter) {
            $meter->delete();
        });
    }

    protected function ensureAccess(
        User $user,
        Meter $meter
    ): void {

        if (
            !$user->isSuperAdmin() &&
            $meter->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized meter access.'
            );
        }
    }
}