<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WaterWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'currency',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function transactions()
    {
        return $this->hasMany(WaterWalletTransaction::class);
    }

    public function nwscPayments()
    {
        return $this->hasMany(NwscPayment::class);
    }

        public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}