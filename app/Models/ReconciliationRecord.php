<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReconciliationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
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
}