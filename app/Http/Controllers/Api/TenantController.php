<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TenantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TenantService $service
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->service->list(
                $request->user(),
                $request->integer('per_page', 20),
                $request->input('search')
            )
        );
    }

    public function store(
        StoreTenantRequest $request
    ) {
        $tenant = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Tenant created successfully.',
            'data' => $tenant,
        ], 201);
    }

    public function show(Tenant $tenant)
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => $this->service->find(
                request()->user(),
                $tenant
            ),
        ]);
    }

    public function update(
        UpdateTenantRequest $request,
        Tenant $tenant
    ) {
        $this->authorize('update', $tenant);

        $tenant = $this->service->update(
            $request->user(),
            $tenant,
            $request->validated()
        );

        return response()->json([
            'message' => 'Tenant updated successfully.',
            'data' => $tenant,
        ]);
    }

    public function destroy(Tenant $tenant)
    {
        $this->authorize('delete', $tenant);

        $this->service->delete(
            request()->user(),
            $tenant
        );

        return response()->json([
            'message' => 'Tenant deleted successfully.',
        ]);
    }
}