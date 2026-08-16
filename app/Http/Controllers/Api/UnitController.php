<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UnitController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected UnitService $service
    ) {}

    public function index(
        Request $request,
        Property $property
    ) {
        return response()->json(
            $this->service->list(
                $request->user(),
                $property,
                $request->integer('per_page', 20)
            )
        );
    }

    public function store(
        StoreUnitRequest $request,
        Property $property
    ) {
        $unit = $this->service->create(
            $request->user(),
            $property,
            $request->validated()
        );

        return response()->json([
            'message' => 'Unit created successfully.',
            'data' => $unit,
        ], 201);
    }

    public function show(Unit $unit)
    {
        $this->authorize('view', $unit);

        return response()->json([
            'data' => $this->service->find(
                request()->user(),
                $unit
            ),
        ]);
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit
    ) {
        $this->authorize('update', $unit);

        $unit = $this->service->update( $request->user(), $unit, $request->validated());

        return response()->json([
            'message' => 'Unit updated successfully.',
            'data' => $unit,
        ]);
    }

    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);

        $this->service->delete( request()->user(), $unit );

        return response()->json([
            'message' => 'Unit deleted successfully.',
        ]);
    }
}