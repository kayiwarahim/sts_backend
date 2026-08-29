<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PropertyService $service
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
        StorePropertyRequest $request
    ) {
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
        Property $property
    ) {
        $this->authorize('view', $property);

        return response()->json([
            'data' => $this->service->find(request()->user(), $property),
        ]);
    }

    public function update(
        UpdatePropertyRequest $request,
        Property $property
    ) {
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
        Property $property
    ) {
        $this->authorize('delete', $property);

        $this->service->delete(request()->user(), $property);

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }
}
