<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NwscAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'account_number',
        'account_name',
        'meter_number',
        'phone',
        'status',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function bills()
    {
        return $this->hasMany(NwscBill::class);
    }

    public function payments()
    {
        return $this->hasMany(NwscPayment::class);
    }
}
