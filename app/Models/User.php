<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Helpers
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function isLandlord(): bool
    {
        return $this->hasRole('Landlord');
    }

    public function isTenant(): bool
    {
        return $this->hasRole('Tenant');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('Staff');
    }

    public function isFinance(): bool
    {
        return $this->hasRole('Finance');
    }

    public function isSupport(): bool
    {
        return $this->hasRole('Support');
    }

    public function isPropertyManager(): bool
    {
        return $this->hasRole('Property Manager');
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Relationships
    |--------------------------------------------------------------------------
    */

    public function resolvedMeterEvents()
    {
        return $this->hasMany(
            MeterEvent::class,
            'resolved_by'
        );
    }

    public function requestedRefunds()
    {
        return $this->hasMany(
            PaymentRefund::class,
            'requested_by'
        );
    }

    public function approvedRefunds()
    {
        return $this->hasMany(
            PaymentRefund::class,
            'approved_by'
        );
    }

    public function requestedReversals()
    {
        return $this->hasMany(
            PaymentReversal::class,
            'requested_by'
        );
    }

    public function approvedSettlements()
    {
        return $this->hasMany(
            Settlement::class,
            'approved_by'
        );
    }

    public function requestedWithdrawals()
    {
        return $this->hasMany(
            LandlordWithdrawal::class,
            'requested_by'
        );
    }

    public function approvedWithdrawals()
    {
        return $this->hasMany(
            LandlordWithdrawal::class,
            'approved_by'
        );
    }

    public function ledgerTransactions()
    {
        return $this->hasMany(
            LedgerTransaction::class,
            'created_by'
        );
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function resolvedReconciliations()
    {
        return $this->hasMany(
            ReconciliationRecord::class,
            'resolved_by'
        );
    }
}