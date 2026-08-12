<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'property_code',
        'address',
        'city',
        'district',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function waterTariffs()
    {
        return $this->hasMany(WaterTariff::class);
    }

    public function billingConfigurations()
    {
        return $this->hasMany(BillingConfiguration::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function waterVendings()
    {
        return $this->hasMany(WaterVending::class);
    }

    public function waterWallet()
    {
        return $this->hasOne(WaterWallet::class);
    }

    public function nwscAccounts()
    {
        return $this->hasMany(NwscAccount::class);
    }


    public function activeBillingConfiguration(): HasOne
    {
        return $this->hasOne(
            BillingConfiguration::class
        )
        ->where('status', 'active')
        ->latestOfMany();
    }
}