<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $service
    ) {
    }

    public function financialSummary(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' =>
                $this->service
                    ->financialSummary(
                        $request->user(),
                        $request->all()
                    ),
        ]);
    }

    public function payments(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' =>
                $this->service
                    ->payments(
                        $request->user(),
                        $request->all()
                    ),
        ]);
    }

    public function waterVendings(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' =>
                $this->service
                    ->waterVendings(
                        $request->user(),
                        $request->all()
                    ),
        ]);
    }

    public function ledger(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'data' =>
                $this->service
                    ->ledgerSummary(
                        $request->user(),
                        $request->all()
                    ),
        ]);
    }
}