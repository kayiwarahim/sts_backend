<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'water_vending_id',
        'meter_id',
        'sts_transaction_id',
        'token',
        'token_sequence',
        'token_type',
        'volume_m3',
        'status',
        'generated_at',
        'issued_at',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:3',
        'generated_at' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function waterVending()
    {
        return $this->belongsTo(WaterVending::class);
    }

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function stsTransaction()
    {
        return $this->belongsTo(
            StsTransaction::class,
            'sts_transaction_id'
        );
    }
}
