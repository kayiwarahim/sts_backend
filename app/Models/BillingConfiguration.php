<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'water_percentage',
        'service_fee_percentage',
        'vat_percentage',
        'gateway_fee_percentage',
        'landlord_percentage',
        'saas_percentage',
        'effective_from',
        'effective_to',
        'is_active',
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
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}