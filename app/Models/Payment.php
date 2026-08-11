<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'property_id',
        'tenant_id',
        'payment_provider_id',
        'payment_provider_account_id',
        'reference',
        'amount',
        'currency',
        'payer_phone',
        'status',
        'initiated_at',
        'completed_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider()
    {
        return $this->belongsTo(
            PaymentProvider::class,
            'payment_provider_id'
        );
    }

    public function providerAccount()
    {
        return $this->belongsTo(
            PaymentProviderAccount::class,
            'payment_provider_account_id'
        );
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function reversals()
    {
        return $this->hasMany(PaymentReversal::class);
    }

    public function stsTransactions()
    {
        return $this->hasMany(StsTransaction::class);
    }

    public function waterVending()
    {
        return $this->hasOne(WaterVending::class);
    }

    public function waterWalletTransactions()
    {
        return $this->hasMany(WaterWalletTransaction::class);
    }

    public function settlementTransactions()
    {
        return $this->hasMany(SettlementTransaction::class);
    }

    public function landlordWalletTransactions()
    {
        return $this->hasMany(LandlordWalletTransaction::class);
    }
}