<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'filename',
        'filepath',
        'period',
        'downloaded_at',
        'expires_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isDownloaded(): bool
    {
        return $this->downloaded_at !== null;
    }

    public function markAsDownloaded(): void
    {
        $this->update(['downloaded_at' => now()]);
    }
}
