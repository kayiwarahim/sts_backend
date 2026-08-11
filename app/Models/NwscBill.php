<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NwscBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'nwsc_account_id',
        'bill_number',
        'billing_period',
        'amount',
        'due_date',
        'balance',
        'status',
        'raw_data',
    ];

    protected $casts = [
        'billing_period' => 'date',
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'balance' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(
            NwscAccount::class,
            'nwsc_account_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(NwscPayment::class);
    }
}