<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'reading',
        'consumption_m3',
        'reading_source',
        'reading_at',
        'raw_data',
    ];

    protected $casts = [
        'reading' => 'decimal:3',
        'consumption_m3' => 'decimal:3',
        'reading_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }
}
