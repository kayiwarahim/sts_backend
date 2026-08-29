<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NwscPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nwsc_account_id',
        'nwsc_bill_id',
        'water_wallet_id',
        'reference',
        'amount',
        'payment_method',
        'status',
        'initiated_by',
        'provider_reference',
        'request_data',
        'response_data',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_data' => 'array',
        'response_data' => 'array',
        'paid_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(
            NwscAccount::class,
            'nwsc_account_id'
        );
    }

    public function bill()
    {
        return $this->belongsTo(
            NwscBill::class,
            'nwsc_bill_id'
        );
    }

    public function waterWallet()
    {
        return $this->belongsTo(WaterWallet::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function walletTransactions()
    {
        return $this->hasMany(
            WaterWalletTransaction::class
        );
    }
}
