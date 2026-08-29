<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRecord;
use App\Services\ReconciliationPersistenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationRecordController extends Controller
{
    public function __construct(
        protected ReconciliationPersistenceService $service
    ) {}

    /*
    |--------------------------------------------------------------------------
    | List persisted records
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResponse {

        $user =
            $request->user();

        $query =
            ReconciliationRecord::query()
                ->with([
                    'organization:id,name',
                    'resolvedBy:id,name,email',
                ]);

        if (
            ! $user->isSuperAdmin()
        ) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        if (
            $request->filled(
                'reconciliation_type'
            )
        ) {
            $query->where(
                'reconciliation_type',
                $request
                    ->reconciliation_type
            );
        }

        if (
            $request->filled(
                'provider'
            )
        ) {
            $query->where(
                'provider',
                $request->provider
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
                'transaction_date',
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
                'transaction_date',
                '<=',
                $request->date_to
            );
        }

        return response()->json([
            'success' => true,

            'data' => $query
                ->latest(
                    'transaction_date'
                )
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

    /*
    |--------------------------------------------------------------------------
    | Run reconciliation
    |--------------------------------------------------------------------------
    */

    public function run(
        Request $request
    ): JsonResponse {

        $result =
            $this
                ->service
                ->run(
                    $request->user()
                );

        return response()->json([
            'success' => true,

            'message' => 'Reconciliation completed successfully.',

            'data' => $result,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve discrepancy
    |--------------------------------------------------------------------------
    */

    public function resolve(
        Request $request,
        ReconciliationRecord $record
    ): JsonResponse {

        $data =
            $request->validate([
                'notes' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ]);

        $record =
            $this
                ->service
                ->resolve(
                    $request->user(),
                    $record,
                    $data['notes'] ?? null
                );

        return response()->json([
            'success' => true,

            'message' => 'Reconciliation issue resolved.',

            'data' => $record,
        ]);
    }
}
