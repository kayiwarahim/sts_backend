<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrganizationScope
{
    public static function apply(
        Builder $query,
        ?User $user = null
    ): Builder {

        $user ??= Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($user->isSuperAdmin()) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Organization
        |--------------------------------------------------------------------------
        */

        return $query->where(
            $query->getModel()->getTable()
                . '.organization_id',

            $user->organization_id
        );
    }
}