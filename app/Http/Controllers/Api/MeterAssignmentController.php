<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeterAssignment\StoreMeterAssignmentRequest;
use App\Http\Requests\MeterAssignment\UpdateMeterAssignmentRequest;
use App\Http\Requests\MeterAssignment\ReassignMeterRequest;
use App\Models\Unit;
use App\Models\MeterAssignment;
use App\Services\MeterAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MeterAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MeterAssignmentService $service
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
        StoreMeterAssignmentRequest $request
    ) {
        $assignment =
            $this->service->create(
                $request->user(),
                $request->validated()
            );

        return response()->json([
            'message' =>'Meter assigned successfully.',
            'data' => $assignment->load([
                'meter',
                'unit.property',
            ]),
        ], 201);
    }

    public function show(
        MeterAssignment $meterAssignment
    ) {
        $this->authorize(
            'view',
            $meterAssignment
        );

        return response()->json([
            'data' =>
                $this->service->find(
                    request()->user(),
                    $meterAssignment
                ),
        ]);
    }

    public function update(
        UpdateMeterAssignmentRequest $request,
        MeterAssignment $meterAssignment
    ) {
        $this->authorize(
            'update',
            $meterAssignment
        );

        $assignment =
            $this->service->update(
                $request->user(),
                $meterAssignment,
                $request->validated()
            );

        return response()->json([
            'message' =>'Meter assignment updated successfully.',
            'data' => $assignment,
        ]);
    }

    public function destroy(
        MeterAssignment $meterAssignment
    ) {
        $this->authorize(
            'delete',
            $meterAssignment
        );

        $this->service->delete(
            request()->user(),
            $meterAssignment
        );

        return response()->json([
            'message' =>'Meter assignment deleted successfully.',
        ]);
    }

    public function reassign(
        ReassignMeterRequest $request,
        MeterAssignment $meterAssignment
    ) {
        $validated =
            $request->validated();

        $targetUnit =
            Unit::findOrFail(
                $validated[
                    'unit_id'
                ]
            );

        $assignment =
            $this->service->reassign(
                $request->user(),
                $meterAssignment,
                $targetUnit,
                $validated[
                    'assigned_at'
                ]
                ?? null
            );

        return response()->json([
            'message' =>
                'Meter reassigned successfully.',

            'data' =>
                $assignment,
        ]);
    }
}