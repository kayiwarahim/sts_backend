<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeterEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'event_type',
        'event_code',
        'description',
        'data',
        'status',
        'occurred_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}