<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BillingConfigurationService
{
    public function list(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {

        $query = BillingConfiguration::query()
            ->with('property');

        if (! $user->isSuperAdmin()) {
            $query->whereHas(
                'property',
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

    public function create(
        User $user,
        array $data
    ): BillingConfiguration {

        $property = Property::findOrFail(
            $data['property_id']
        );

        $this->ensurePropertyAccess(
            $user,
            $property
        );

        if (
            ($data['status'] ?? 'active') === 'active' &&
            $property->billingConfigurations()
                ->where('status', 'active')
                ->where(function ($query) use ($data) {

                    $effectiveFrom = $data['effective_from'];

                    $query
                        ->whereNull('effective_to')
                        ->orWhere(
                            'effective_to',
                            '>=',
                            $effectiveFrom
                        );
                })
                ->exists()
        ) {
            abort(
                422,
                'This property already has an overlapping active billing configuration.'
            );
        }

        return DB::transaction(
            function () use ($data) {

                return BillingConfiguration::create([
                    'property_id' => $data['property_id'],
                    'name' => $data['name'],
                    'water_percentage' => $data['water_percentage'],
                    'service_fee_percentage' => $data['service_fee_percentage'],
                    'vat_percentage' => $data['vat_percentage'],
                    'gateway_fee_percentage' => $data['gateway_fee_percentage'],
                    'landlord_percentage' => $data['landlord_percentage'],
                    'saas_percentage' => $data['saas_percentage'],
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);
            }
        );
    }

    public function update(
        User $user,
        BillingConfiguration $configuration,
        array $data
    ): BillingConfiguration {

        $configuration->loadMissing('property');

        $this->ensurePropertyAccess(
            $user,
            $configuration->property
        );

        $configuration->update($data);

        return $configuration->fresh([
            'property',
        ]);
    }

    public function show(
        User $user,
        BillingConfiguration $configuration
    ): BillingConfiguration {

        $configuration->loadMissing('property');

        $this->ensurePropertyAccess(
            $user,
            $configuration->property
        );

        return $configuration;
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
}
