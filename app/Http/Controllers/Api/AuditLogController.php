<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {

        $user =
            $request->user();

        $query =
            AuditLog::query()
                ->with([
                    'user:id,name,email',
                    'organization:id,name',
                ]);

        if (
            !$user->isSuperAdmin()
        ) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        if (
            $request->filled(
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        if (
            $request->filled(
                'action'
            )
        ) {
            $query->where(
                'action',
                $request->action
            );
        }

        if (
            $request->filled(
                'auditable_type'
            )
        ) {
            $query->where(
                'auditable_type',
                $request
                    ->auditable_type
            );
        }

        if (
            $request->filled(
                'organization_id'
            ) &&
            $user->isSuperAdmin()
        ) {
            $query->where(
                'organization_id',
                $request
                    ->organization_id
            );
        }

        if (
            $request->filled(
                'date_from'
            )
        ) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if (
            $request->filled(
                'date_to'
            )
        ) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        return response()->json([
            'success' => true,

            'data' =>
                $query
                    ->latest()
                    ->paginate(
                        min(
                            (int)
                            $request->input(
                                'per_page',
                                25
                            ),
                            100
                        )
                    ),
        ]);
    }
}