<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
        public ?string $friendlyReason = null
    ) {
        $this->payment->loadMissing([
            'tenant',
            'property',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Water Payment Failed '.$this->payment->reference
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
