<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreTenancyRequest;
use App\Http\Requests\Tenancy\TransferTenancyRequest;
use App\Http\Requests\Tenancy\UpdateTenancyRequest;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Services\TenancyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TenancyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TenancyService $service
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->service->list(
                $request->user(),
                $request->integer(
                    'per_page',
                    20
                )
            )
        );
    }

    public function store(
        StoreTenancyRequest $request
    ) {
        $tenancy = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Tenancy created successfully.',
            'data' => $tenancy->load([
                'tenant',
                'unit.property',
            ]),
        ], 201);
    }

    public function show(
        Tenancy $tenancy
    ) {
        $this->authorize('view', $tenancy);

        return response()->json([
            'data' => $this->service->find(
                request()->user(),
                $tenancy
            ),
        ]);
    }

    public function update(
        UpdateTenancyRequest $request,
        Tenancy $tenancy
    ) {
        $this->authorize('update', $tenancy);

        $tenancy = $this->service->update(
            $request->user(),
            $tenancy,
            $request->validated()
        );

        return response()->json([
            'message' => 'Tenancy updated successfully.',
            'data' => $tenancy,
        ]);
    }

    public function destroy(
        Tenancy $tenancy
    ) {
        $this->authorize(
            'delete',
            $tenancy
        );

        $this->service->delete(
            request()->user(),
            $tenancy
        );

        return response()->json([
            'message' => 'Tenancy deleted successfully.',
        ]);
    }

    public function transfer(
        TransferTenancyRequest $request,
        Tenancy $tenancy
    ) {
        $validated =
            $request->validated();

        $targetUnit =
            Unit::findOrFail(
                $validated[
                    'unit_id'
                ]
            );

        $newTenancy =
            $this->service->transfer(
                $request->user(),
                $tenancy,
                $targetUnit,
                $validated[
                    'transfer_date'
                ]
                ?? null,
                $validated[
                    'notes'
                ]
                ?? null
            );

        return response()->json([
            'message' => 'Tenant transferred successfully.',

            'data' => $newTenancy,
        ]);
    }
}
