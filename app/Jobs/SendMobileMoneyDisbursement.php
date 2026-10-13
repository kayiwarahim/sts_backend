<?php

namespace App\Jobs;

use App\Services\MobileMoneyDisbursementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMobileMoneyDisbursement implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public int $disbursementId) {}
    public function handle(MobileMoneyDisbursementService $service): void { $service->send($this->disbursementId); }
}
