<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'landlord_wallet_id',
        'settlement_id',
        'reference',
        'amount',
        'method',
        'account_number',
        'account_name',
        'status',
        'requested_by',
        'approved_by',
        'processed_at',
        'provider_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function wallet()
    {
        return $this->belongsTo(
            LandlordWallet::class,
            'landlord_wallet_id'
        );
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function walletTransactions()
    {
        return $this->hasMany(
            LandlordWalletTransaction::class,
            'withdrawal_id'
        );
    }
}
