<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeterAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'unit_id',
        'assigned_at',
        'unassigned_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}