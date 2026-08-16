<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'channel',
        'type',
        'title',
        'message',
        'data',
        'sent_at',
        'read_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return
            $this->read_at !==
            null;
    }

    public function isUnread(): bool
    {
        return
            $this->read_at ===
            null;
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update([
                'read_at' =>
                    now(),
            ]);
        }
    }
}