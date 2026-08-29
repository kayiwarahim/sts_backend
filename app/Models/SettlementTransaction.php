<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettlementTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'payment_id',
        'type',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
