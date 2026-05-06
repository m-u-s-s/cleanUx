<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mention @user, @here, @channel ou @team dans un message.
 *
 * Pré-extrait au moment de saveMessage() pour permettre :
 *   - notifs ciblées (`Notification::send($mentionedUser, …)`)
 *   - badge "X mentions non lues" via scope unread()
 *   - rendu rich text côté client via start_offset/length
 */
class MessageMention extends Model
{
    use HasFactory;

    public const TYPE_USER    = 'user';
    public const TYPE_HERE    = 'here';     // notifie online seulement
    public const TYPE_CHANNEL = 'channel';  // notifie tous les membres
    public const TYPE_TEAM    = 'team';     // notifie une FieldTeam donnée

    protected $fillable = [
        'message_id',
        'mentioned_user_id',
        'mention_type',
        'start_offset',
        'length',
        'read_at',
    ];

    protected $casts = [
        'start_offset' => 'integer',
        'length'       => 'integer',
        'read_at'      => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('mentioned_user_id', $userId);
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
