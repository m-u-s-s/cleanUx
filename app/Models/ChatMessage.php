<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    public const MODERATION_CLEAN = 'clean';
    public const MODERATION_FLAGGED = 'flagged';
    public const MODERATION_BLOCKED = 'blocked';

    protected $fillable = [
        'thread_id', 'sender_user_id', 'sender_role', 'body',
        'is_redacted', 'body_original_hash',
        'is_deleted', 'deleted_at', 'deleted_by_user_id',
        'attachment_path', 'attachment_mime', 'attachment_size_bytes',
        'moderation_status', 'moderation_reason', 'metadata',
    ];

    protected $casts = [
        'is_redacted' => 'boolean',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'attachment_size_bytes' => 'integer',
        'metadata' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class, 'message_id');
    }

    public function scopeNotDeleted(Builder $q): Builder
    {
        return $q->where('is_deleted', false);
    }

    public function scopeFlagged(Builder $q): Builder
    {
        return $q->where('moderation_status', self::MODERATION_FLAGGED);
    }

    public function scopeBlocked(Builder $q): Builder
    {
        return $q->where('moderation_status', self::MODERATION_BLOCKED);
    }

    public function isBlocked(): bool
    {
        return $this->moderation_status === self::MODERATION_BLOCKED;
    }

    public function displayBody(): string
    {
        if ($this->is_deleted) {
            return '[message supprimé]';
        }
        if ($this->isBlocked()) {
            return '[message bloqué par modération]';
        }
        return (string) $this->body;
    }
}
