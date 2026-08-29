<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_wallet_id',
        'payment_id',
        'settlement_id',
        'withdrawal_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet()
    {
        return $this->belongsTo(
            LandlordWallet::class,
            'landlord_wallet_id'
        );
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function withdrawal()
    {
        return $this->belongsTo(
            LandlordWithdrawal::class,
            'withdrawal_id'
        );
    }
}
