<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WaterVending extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'tenant_id',
        'property_id',
        'meter_id',
        'water_tariff_id',
        'amount',
        'price_per_m3',
        'volume_m3',
        'reference',
        'status',
        'vended_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price_per_m3' => 'decimal:2',
        'volume_m3' => 'decimal:3',
        'vended_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function waterTariff()
    {
        return $this->belongsTo(WaterTariff::class);
    }

    public function tokens()
    {
        return $this->hasMany(MeterToken::class);
    }
}