<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileMoneyDisbursement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2', 'gateway_fee' => 'decimal:2', 'total_debit' => 'decimal:2',
        'provider_charge' => 'decimal:2', 'provider_response' => 'array', 'completed_at' => 'datetime',
    ];

    public function property() { return $this->belongsTo(Property::class); }
    public function nwscAccount() { return $this->belongsTo(NwscAccount::class); }
    public function waterWallet() { return $this->belongsTo(WaterWallet::class); }
}
