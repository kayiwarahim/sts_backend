<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_transaction_id',
        'ledger_account_id',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'ledger_transaction_id'
        );
    }

    public function account()
    {
        return $this->belongsTo(
            LedgerAccount::class,
            'ledger_account_id'
        );
    }
}