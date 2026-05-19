<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatThread extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'code', 'context_type', 'context_id', 'title', 'status',
        'is_archived', 'last_message_at', 'last_message_preview',
        'message_count', 'flagged_count', 'metadata',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'last_message_at' => 'datetime',
        'message_count' => 'integer',
        'flagged_count' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'thr_' . Str::lower(Str::random(20));
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)->where('is_archived', false);
    }

    public function scopeForContext(Builder $q, string $type, int $id): Builder
    {
        return $q->where('context_type', $type)->where('context_id', $id);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereHas('participants', function ($w) use ($userId) {
            $w->where('user_id', $userId)->whereNull('left_at');
        });
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->is_archived;
    }
}
