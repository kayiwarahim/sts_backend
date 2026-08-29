<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function list(
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {

        return Organization::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere(
                            'registration_number',
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
                        );
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): Organization
    {
        return Organization::with([
            'users',
            'properties',
            'tenants',
            'meters',
        ])->findOrFail($id);
    }

    public function create(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            return Organization::create($data);
        });
    }

    public function update(
        Organization $organization,
        array $data
    ): Organization {

        return DB::transaction(function () use (
            $organization,
            $data
        ) {
            $organization->update($data);

            return $organization->fresh();
        });
    }

    public function delete(
        Organization $organization
    ): void {

        DB::transaction(function () use ($organization) {
            $organization->delete();
        });
    }
}
