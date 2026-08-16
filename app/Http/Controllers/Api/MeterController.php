<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meter\StoreMeterRequest;
use App\Http\Requests\Meter\UpdateMeterRequest;
use App\Models\Meter;
use App\Services\MeterService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MeterController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MeterService $service
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
        StoreMeterRequest $request
    ) {
        $meter = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Meter created successfully.',
            'data' => $meter,
        ], 201);
    }

    public function show(Meter $meter)
    {
        $this->authorize('view', $meter);

        return response()->json([
            'data' => $this->service->find(
                request()->user(),
                $meter
            ),
        ]);
    }

    public function update(
        UpdateMeterRequest $request,
        Meter $meter
    ) {
        $this->authorize('update', $meter);

        $meter = $this->service->update(
            $request->user(),
            $meter,
            $request->validated()
        );

        return response()->json([
            'message' => 'Meter updated successfully.',
            'data' => $meter,
        ]);
    }

    public function destroy(Meter $meter)
    {
        $this->authorize('delete', $meter);

        $this->service->delete(
            request()->user(),
            $meter
        );

        return response()->json([
            'message' => 'Meter deleted successfully.',
        ]);
    }
}