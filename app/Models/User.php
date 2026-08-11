<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'organization_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

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
