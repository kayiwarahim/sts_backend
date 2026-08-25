<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'property_id',
        'tenant_id',
        'payment_provider_id',
        'ledger_transaction_id',
        'payment_provider_account_id',
        'reference',
        'amount',
        'currency',
        'payer_phone',
        'status',
        'initiated_at',
        'completed_at',
        'failure_reason',
        'provider_reference',
        'mobile_money_provider',
        'provider_transaction_id',
        'provider_charge',
        'provider_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_charge' => 'decimal:2',
        'provider_response' => 'array',

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

    public function paymentProvider()
    {
        return $this->belongsTo(
            PaymentProvider::class,
            'payment_provider_id'
        );
    }

    public function paymentProviderAccount()
    {
        return $this->belongsTo(
            PaymentProviderAccount::class,
            'payment_provider_account_id'
        );
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'ledger_transaction_id'
        );
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function meter()
    {
        return $this->belongsTo(
            Meter::class
        );
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

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}