<?php

namespace App\Services;

use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantTransferService
{
    public function move(
        User $user,
        Tenancy $tenancy,
        Unit $targetUnit,
        ?string $moveDate = null
    ): Tenancy {
        $moveDate =
            $moveDate
            ?? now()->toDateString();

        return DB::transaction(
            function () use (
                $user,
                $tenancy,
                $targetUnit,
                $moveDate
            ) {
                /*
                |--------------------------------------------------------------------------
                | Lock current tenancy
                |--------------------------------------------------------------------------
                */

                $tenancy =
                    Tenancy::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $tenancy->id
                        );

                if (
                    $tenancy->status !==
                    'active'
                ) {
                    throw new RuntimeException(
                        'Only an active tenancy can be moved.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Organization check
                |--------------------------------------------------------------------------
                */

                $targetUnit->loadMissing(
                    'property'
                );

                if (
                    !$user->isSuperAdmin()
                    &&
                    $targetUnit
                        ->property
                        ?->organization_id
                    !==
                    $user->organization_id
                ) {
                    throw new RuntimeException(
                        'The target unit does not belong to your organization.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Target unit must not already have an active tenancy
                |--------------------------------------------------------------------------
                */

                $occupied =
                    Tenancy::query()
                        ->where(
                            'unit_id',
                            $targetUnit->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($occupied) {
                    throw new RuntimeException(
                        'The target unit already has an active tenancy.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | End old tenancy
                |--------------------------------------------------------------------------
                */

                $tenancy->update([
                    'status' =>
                        'ended',

                    'end_date' =>
                        $moveDate,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Create new tenancy
                |--------------------------------------------------------------------------
                */

                return Tenancy::create([
                    'tenant_id' =>
                        $tenancy->tenant_id,

                    'unit_id' =>
                        $targetUnit->id,

                    'start_date' =>
                        $moveDate,

                    'end_date' =>
                        null,

                    'status' =>
                        'active',

                    'notes' =>
                        'Tenant transferred from tenancy #'
                        . $tenancy->id,
                ]);
            }
        );
    }
}