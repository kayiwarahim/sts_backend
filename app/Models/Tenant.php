<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'national_id',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class)
            ->where('status', 'active');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function waterVendings()
    {
        return $this->hasMany(WaterVending::class);
    }
}