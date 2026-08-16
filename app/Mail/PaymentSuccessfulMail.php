<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessfulMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment
    ) {
        $this->payment->loadMissing([
            'tenant',
            'property',
            'meter',
        ]);
    }

    public function envelope(): Envelope
    {
        $meterNumber =
            $this->payment
                ->meter
                ?->meter_number
            ?? 'Unknown Meter';

        return new Envelope(
            subject: 'Water Payment Successful for ' . $meterNumber
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-successful'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}