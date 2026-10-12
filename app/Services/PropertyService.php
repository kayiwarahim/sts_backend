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
            ->with(['organization', 'nwscAccounts']);

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
            'nwscAccounts',
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
            $account = $data['nwsc_account'] ?? null;
            unset($data['nwsc_account']);
            $property = Property::create($data);
            if ($account !== null) {
                $this->saveNwscAccount($property, $account);
            }

            // Refresh to include database defaults and the persisted account details.
            return $property->fresh()->load(['organization', 'units', 'nwscAccounts']);
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
            $account = $data['nwsc_account'] ?? null;
            unset($data['nwsc_account']);
            $property->update($data);
            if ($account !== null) {
                $this->saveNwscAccount($property, $account);
            }

            return $property->fresh()->load(['organization', 'units', 'nwscAccounts']);
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

    protected function saveNwscAccount(Property $property, array $data): void
    {
        $accountNumber = $data['account_number'];
        unset($data['account_number']);
        $property->nwscAccounts()->updateOrCreate(['account_number' => $accountNumber], $data);
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
