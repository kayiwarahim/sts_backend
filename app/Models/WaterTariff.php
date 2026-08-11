<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WaterTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'price_per_m3',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'price_per_m3' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function waterVendings()
    {
        return $this->hasMany(WaterVending::class);
    }
}