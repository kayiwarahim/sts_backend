<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TenantService
{
    public function list(
        User $user,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {

        $query = Tenant::query()
            ->with([
                'organization',
                'tenancies.unit.property',
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
                        'first_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'last_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'national_id',
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
        Tenant $tenant
    ): Tenant {

        $this->ensureAccess(
            $user,
            $tenant
        );

        return $tenant->load([
            'organization',
            'tenancies.unit.property',
            'tenancies.meterAssignments',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): Tenant {

        if (!$user->isSuperAdmin()) {
            $data['organization_id'] =
                $user->organization_id;
        }

        return DB::transaction(function () use ($data) {
            return Tenant::create($data);
        });
    }

    public function update(
        User $user,
        Tenant $tenant,
        array $data
    ): Tenant {

        $this->ensureAccess(
            $user,
            $tenant
        );

        unset($data['organization_id']);

        return DB::transaction(function () use (
            $tenant,
            $data
        ) {

            $tenant->update($data);

            return $tenant->fresh();
        });
    }

    public function delete(
        User $user,
        Tenant $tenant
    ): void {

        $this->ensureAccess(
            $user,
            $tenant
        );

        DB::transaction(function () use ($tenant) {
            $tenant->delete();
        });
    }

    protected function ensureAccess(
        User $user,
        Tenant $tenant
    ): void {

        if (
            !$user->isSuperAdmin() &&
            $tenant->organization_id
                !== $user->organization_id
        ) {
            abort(
                403,
                'Unauthorized tenant access.'
            );
        }
    }
}