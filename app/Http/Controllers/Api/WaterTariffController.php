<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaterTariff\StoreWaterTariffRequest;
use App\Http\Requests\WaterTariff\UpdateWaterTariffRequest;
use App\Models\WaterTariff;
use App\Services\WaterTariffService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WaterTariffController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected WaterTariffService $service
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
        StoreWaterTariffRequest $request
    ) {
        $tariff = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Water tariff created successfully.',
            'data' => $tariff,
        ], 201);
    }

    public function show(
        WaterTariff $waterTariff
    ) {
        $waterTariff->load('property');

        $this->authorize('view', $waterTariff);

        return response()->json([
            'data' => $waterTariff,
        ]);
    }

    public function update(
        UpdateWaterTariffRequest $request,
        WaterTariff $waterTariff
    ) {
        $this->authorize('update', $waterTariff);

        $tariff = $this->service->update(
            $request->user(),
            $waterTariff,
            $request->validated()
        );

        return response()->json([
            'message' => 'Water tariff updated successfully.',
            'data' => $tariff,
        ]);
    }
}
