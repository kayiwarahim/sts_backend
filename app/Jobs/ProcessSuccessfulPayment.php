<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentProcessingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSuccessfulPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $paymentId
    ) {}

    public function uniqueId(): string
    {
        return (string)
            $this->paymentId;
    }

    public function backoff(): array
    {
        return [
            10,
            30,
            60,
            120,
            300,
        ];
    }

    public function handle(
        PaymentProcessingService $service
    ): void {

        $payment =
            Payment::findOrFail(
                $this->paymentId
            );

        /*
        |--------------------------------------------------------------------------
        | Never process unpaid payments
        |--------------------------------------------------------------------------
        */

        if (
            $payment->status
            !==
            'successful'
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Existing service is idempotent
        |--------------------------------------------------------------------------
        */

        $service
            ->processSuccessfulPayment(
                $payment
            );
    }
}
