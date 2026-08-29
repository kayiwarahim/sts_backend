<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'reference',
        'filename',
        'disk',
        'path',
        'database_name',
        'type',
        'status',
        'size_bytes',
        'checksum',
        'created_by',
        'restored_by',
        'started_at',
        'completed_at',
        'restored_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'size_bytes' => 'integer',

        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'restored_at' => 'datetime',

        'metadata' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'restored_by'
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRestorable(): bool
    {
        return in_array(
            $this->status,
            [
                'completed',
                'restored',
            ],
            true
        );
    }
}
