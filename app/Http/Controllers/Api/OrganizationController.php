<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationService $service
    ) {}

    public function index(Request $request)
    {
        $organizations = $this->service->list(
            $request->integer('per_page', 20),
            $request->input('search')
        );

        return response()->json($organizations);
    }

    public function store(
        StoreOrganizationRequest $request
    ) {
        $organization = $this->service->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Organization created successfully.',
            'data' => $organization,
        ], 201);
    }

    public function show(
        Organization $organization
    ) {
        $this->authorize(
            'view',
            $organization
        );

        return response()->json([
            'data' => $this->service->find(
                $organization->id
            ),
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization
    ) {
        $this->authorize(
            'update',
            $organization
        );

        $organization = $this->service->update(
            $organization,
            $request->validated()
        );

        return response()->json([
            'message' => 'Organization updated successfully.',
            'data' => $organization,
        ]);
    }

    public function destroy(
        Organization $organization
    ) {
        $this->authorize(
            'delete',
            $organization
        );

        $this->service->delete($organization);

        return response()->json([
            'message' => 'Organization deleted successfully.',
        ]);
    }
}