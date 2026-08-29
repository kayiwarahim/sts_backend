<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'reconciliation_type',
        'provider',
        'provider_reference',
        'internal_reference',
        'transaction_date',
        'expected_amount',
        'actual_amount',
        'difference',
        'status',
        'external_data',
        'resolved_by',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'external_data' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function organization()
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
