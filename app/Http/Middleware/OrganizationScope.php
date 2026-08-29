<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationScope
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Must be authenticated
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        |
        | Super Admin can access all organizations.
        |
        */

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Normal User Must Belong To Organization
        |--------------------------------------------------------------------------
        */

        if (! $user->organization_id) {
            return response()->json([
                'message' => 'User is not assigned to an organization.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Store Current Organization
        |--------------------------------------------------------------------------
        */

        app()->instance(
            'currentOrganization',
            $user->organization
        );

        /*
        |--------------------------------------------------------------------------
        | Continue
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }
}
