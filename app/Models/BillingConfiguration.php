<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'water_tariff_id',

        'water_percentage',
        'service_fee_percentage',
        'vat_percentage',
        'gateway_fee_percentage',
        'landlord_percentage',
        'saas_percentage',

        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'water_percentage' => 'decimal:2',
        'service_fee_percentage' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'gateway_fee_percentage' => 'decimal:2',
        'landlord_percentage' => 'decimal:2',
        'saas_percentage' => 'decimal:2',

        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(
            Property::class
        );
    }

    public function waterTariff(): BelongsTo
    {
        return $this->belongsTo(
            WaterTariff::class
        );
    }

    public function percentagesTotal(): float
    {
        return
            (float) $this->water_percentage +
            (float) $this->service_fee_percentage +
            (float) $this->vat_percentage +
            (float) $this->gateway_fee_percentage +
            (float) $this->landlord_percentage +
            (float) $this->saas_percentage;
    }
    
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(
            PaymentAllocation::class
        );
    }

    public function isValidSplit(): bool
    {
        return abs(
            $this->percentagesTotal() - 100
        ) < 0.001;
    }
}