<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingConfiguration\StoreBillingConfigurationRequest;
use App\Http\Requests\BillingConfiguration\UpdateBillingConfigurationRequest;
use App\Models\BillingConfiguration;
use App\Services\BillingConfigurationService;
use Illuminate\Http\Request;

class BillingConfigurationController extends Controller
{
    public function __construct(
        protected BillingConfigurationService $service
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
        StoreBillingConfigurationRequest $request
    ) {
        $configuration =
            $this->service->create(
                $request->user(),
                $request->validated()
            );

        return response()->json([
            'message' =>
                'Billing configuration created successfully.',

            'data' => $configuration->load([
                'property',
                'waterTariff',
            ]),
        ], 201);
    }

    public function show(
        BillingConfiguration $billingConfiguration
    ) {
        $this->authorize(
            'view',
            $billingConfiguration
        );

        return response()->json([
            'data' => $this->service->show(
                request()->user(),
                $billingConfiguration
            ),
        ]);
    }

    public function update(
        UpdateBillingConfigurationRequest $request,
        BillingConfiguration $billingConfiguration
    ) {
        $this->authorize(
            'update',
            $billingConfiguration
        );

        $configuration =
            $this->service->update(
                $request->user(),
                $billingConfiguration,
                $request->validated()
            );

        return response()->json([
            'message' =>
                'Billing configuration updated successfully.',

            'data' => $configuration,
        ]);
    }
}