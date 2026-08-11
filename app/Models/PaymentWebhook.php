<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_provider_id',
        'event_id',
        'provider_reference',
        'event_type',
        'payload',
        'signature',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->belongsTo(
            PaymentProvider::class,
            'payment_provider_id'
        );
    }
}