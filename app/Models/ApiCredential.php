<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'name',
        'base_url',
        'client_id',
        'client_secret',
        'api_key',
        'username',
        'password',
        'additional_config',
        'is_active',
        'last_tested_at',
    ];

    protected $casts = [
        'additional_config' => 'array',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];
}
