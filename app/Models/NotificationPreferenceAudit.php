<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreferenceAudit extends Model
{
    protected $fillable = [
        'user_id', 'channel', 'category',
        'old_value', 'new_value',
        'version_from', 'version_to',
        'source', 'actor_user_id',
        'ip_hash', 'user_agent_short',
        'changed_at', 'metadata',
    ];

    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
        'version_from' => 'integer',
        'version_to' => 'integer',
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }
}
