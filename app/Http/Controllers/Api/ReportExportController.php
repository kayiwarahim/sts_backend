<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        protected ReportExportService $service
    ) {}

    public function payments(
        Request $request
    ): StreamedResponse {

        return $this->service->payments($request->user(), $request->all());
    }

    public function waterVendings(
        Request $request
    ): StreamedResponse {

        return $this->service->waterVendings($request->user(), $request->all());
    }

    public function ledger(
        Request $request
    ): StreamedResponse {

        return $this->service->ledger($request->user(), $request->all());
    }

    public function paymentReconciliation(
        Request $request
    ): StreamedResponse {

        return $this->service->paymentReconciliation($request->user(), $request->all());
    }

    public function stsReconciliation(
        Request $request
    ): StreamedResponse {

        return $this->service->stsReconciliation($request->user(), $request->all());
    }
}
