<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'payment_transaction_id',
        'reference',
        'amount',
        'reason',
        'status',
        'provider_reference',
        'requested_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction()
    {
        return $this->belongsTo(
            PaymentTransaction::class,
            'payment_transaction_id'
        );
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}