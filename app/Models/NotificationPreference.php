<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const SOURCE_DEFAULT = 'default';
    public const SOURCE_USER = 'user';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_WEBHOOK = 'webhook';
    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'user_id', 'channel', 'category',
        'is_allowed', 'version', 'source',
        'updated_via_ip_hash', 'last_changed_at', 'metadata',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
        'version' => 'integer',
        'last_changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }
}
