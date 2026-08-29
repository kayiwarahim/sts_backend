<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'price_per_m3',
        'currency',
        'effective_from',
        'effective_to',
        'status',
        'notes',
    ];

    protected $casts = [
        'price_per_m3' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(
            Property::class
        );
    }

    public function billingConfigurations(): HasMany
    {
        return $this->hasMany(
            BillingConfiguration::class
        );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'status',
            'active'
        );
    }
}
