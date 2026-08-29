<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LedgerTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'reference',
        'transaction_type',
        'description',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDebits(): float
    {
        return (float) $this->entries()->sum('debit');
    }

    public function totalCredits(): float
    {
        return (float) $this->entries()->sum('credit');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(
            Payment::class,
            'ledger_transaction_id'
        );
    }

    public function isBalanced(): bool
    {
        return bccomp(
            (string) $this->totalDebits(),
            (string) $this->totalCredits(),
            2
        ) === 0;
    }
}
