<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meter_number',
        'serial_number',
        'manufacturer',
        'model',
        'meter_type',
        'key_revision',
        'supply_group_code',
        'status',
        'installed_at',
    ];

    protected $casts = [
        'installed_at' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignments()
    {
        return $this->hasMany(MeterAssignment::class);
    }

    public function activeAssignment()
    {
        return $this
            ->hasOne(
                MeterAssignment::class
            )
            ->where(
                'status',
                'active'
            )
            ->whereNull(
                'unassigned_at'
            )
            ->latestOfMany();
    }

    public function readings()
    {
        return $this->hasMany(MeterReading::class);
    }

    public function events()
    {
        return $this->hasMany(MeterEvent::class);
    }

    public function stsTransactions()
    {
        return $this->hasMany(StsTransaction::class);
    }

    public function waterVendings()
    {
        return $this->hasMany(WaterVending::class);
    }

    public function tokens()
    {
        return $this->hasMany(MeterToken::class);
    }
}
