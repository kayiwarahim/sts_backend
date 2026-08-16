<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\StsTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaterTokenGeneratedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
        public StsTransaction $stsTransaction
    ) {
        $this->payment->loadMissing([
            'tenant',
            'property',
            'waterVending.waterTariff',
        ]);

        $this->stsTransaction->loadMissing([
            'meter',
            'tokens',
        ]);
    }

    public function envelope(): Envelope
    {
        $meterNumber =
            $this->stsTransaction
                ->meter
                ?->meter_number
            ?? 'Unknown Meter';

        return new Envelope(
            subject: 'Your Water Token for ' . $meterNumber
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.water-token-generated'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}