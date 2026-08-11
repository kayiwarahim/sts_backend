<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}