<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(
        protected ReconciliationService $service
    ) {}

    public function payments(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' => $this->service
                ->payments(
                    $request->user(),
                    $request->all()
                ),
        ]);
    }

    public function sts(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' => $this->service
                ->sts(
                    $request->user(),
                    $request->all()
                ),
        ]);
    }
}
