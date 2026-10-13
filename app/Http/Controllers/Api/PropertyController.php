<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\PropertyService;
use App\Services\PropertyFinancialStatisticsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PropertyService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->list(
                $request->user(),
                $request->integer('per_page', 20),
                $request->input('search')
            )
        );
    }

    public function allFinancialStatistics(Request $request, PropertyFinancialStatisticsService $statistics): JsonResponse
    {
        $this->authorize('viewAny', Property::class);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'transactions_per_page' => ['sometimes', 'integer', 'between:1,100'],
            'transactions_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $statistics->allProperties(
                $request->user(),
                (int) ($validated['per_page'] ?? 20),
                (int) ($validated['transactions_per_page'] ?? 20),
                (int) ($validated['transactions_page'] ?? 1)
            ),
        ]);
    }

    public function financialStatistics(Request $request, Property $property, PropertyFinancialStatisticsService $statistics): JsonResponse
    {
        $this->authorize('view', $property);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $statistics->report($property, (int) ($validated['per_page'] ?? 20)),
        ]);
    }

    public function store(
        StorePropertyRequest $request
    ): JsonResponse {
        $property = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Property created successfully.',
            'data' => $property,
        ], 201);
    }

    public function show(
        Request $request,
        Property $property
    ): JsonResponse {
        $this->authorize('view', $property);

        return response()->json([
            'data' => $this->service->find($request->user(), $property),
        ]);
    }

    public function update(
        UpdatePropertyRequest $request,
        Property $property
    ): JsonResponse {
        $this->authorize('update', $property);

        $property = $this->service->update(
            $request->user(),
            $property,
            $request->validated()
        );

        return response()->json([
            'message' => 'Property updated successfully.',
            'data' => $property,
        ]);
    }

    public function destroy(
        Request $request,
        Property $property
    ): JsonResponse {
        $this->authorize('delete', $property);

        $this->service->delete($request->user(), $property);

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }
}
