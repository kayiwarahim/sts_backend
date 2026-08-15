<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentProviderAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_provider_id',
        'organization_id',
        'name',
        'merchant_code',
        'credentials',
        'environment',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function paymentProvider()
    {
        return $this->belongsTo(
            PaymentProvider::class,
            'payment_provider_id'
        );
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}