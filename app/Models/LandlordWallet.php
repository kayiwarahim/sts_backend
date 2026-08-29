<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'currency',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function transactions()
    {
        return $this->hasMany(
            LandlordWalletTransaction::class
        );
    }

    public function withdrawals()
    {
        return $this->hasMany(
            LandlordWithdrawal::class
        );
    }
}
