<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'operation',
        'method',
        'url',
        'request_reference',
        'http_status',
        'request_data',
        'response_data',
        'response_time_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];
}