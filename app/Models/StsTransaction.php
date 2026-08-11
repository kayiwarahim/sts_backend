<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'payment_id',
        'reference',
        'transaction_type',
        'external_reference',
        'status',
        'amount',
        'volume_m3',
        'token',
        'request_data',
        'response_data',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'volume_m3' => 'decimal:3',
        'request_data' => 'array',
        'response_data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function tokens()
    {
        return $this->hasMany(MeterToken::class);
    }
}