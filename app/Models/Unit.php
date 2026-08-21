<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'unit_number',
        'floor',
        'description',
        'status',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function meterAssignments()
    {
        return $this->hasMany(MeterAssignment::class);
    }

    public function activeTenancy()
    {
        return $this
            ->hasOne(
                Tenancy::class
            )
            ->where(
                'status',
                'active'
            )
            ->whereNull(
                'end_date'
            )
            ->latestOfMany();
    }

    public function activeMeterAssignment()
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

}