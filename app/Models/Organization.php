<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'phone',
        'email',
        'address',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function meters()
    {
        return $this->hasMany(Meter::class);
    }

    public function paymentProviderAccounts()
    {
        return $this->hasMany(PaymentProviderAccount::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function landlordWallet()
    {
        return $this->hasOne(LandlordWallet::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function ledgerAccounts()
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function ledgerTransactions()
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
    
}