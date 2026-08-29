<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'water_wallet_id',
        'payment_id',
        'nwsc_payment_id',
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
        return $this->belongsTo(WaterWallet::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function nwscPayment()
    {
        return $this->belongsTo(
            NwscPayment::class,
            'nwsc_payment_id'
        );
    }
}
