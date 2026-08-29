<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'base_url',
        'is_active',
        'configuration',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
    ];

    public function accounts()
    {
        return $this->hasMany(PaymentProviderAccount::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function webhooks()
    {
        return $this->hasMany(PaymentWebhook::class);
    }
}
