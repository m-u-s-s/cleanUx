<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatParticipant extends Model
{
    use HasFactory;

    public const ROLE_CLIENT = 'client';

    public const ROLE_PROVIDER = 'provider';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OBSERVER = 'observer';

    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'thread_id', 'user_id', 'role',
        'is_muted', 'can_send',
        'joined_at', 'last_read_at', 'last_read_message_id', 'left_at',
    ];

    protected $casts = [
        'is_muted' => 'boolean',
        'can_send' => 'boolean',
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /** @return BelongsTo<ChatThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('left_at');
    }

    public function canSendMessages(): bool
    {
        return $this->left_at === null && $this->can_send && ! $this->is_muted;
    }
}
