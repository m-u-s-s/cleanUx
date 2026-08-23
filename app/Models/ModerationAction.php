<?php

namespace App\Models;

use Database\Factories\ModerationActionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'audit pour la modération de canaux et messages. */
class ModerationAction extends Model
{
    /** @use HasFactory<ModerationActionFactory> */
    use HasFactory;

    public const TYPE_DELETE_MESSAGE = 'delete_message';

    public const TYPE_PIN_MESSAGE = 'pin_message';

    public const TYPE_UNPIN_MESSAGE = 'unpin_message';

    public const TYPE_LOCK_CHANNEL = 'lock_channel';

    public const TYPE_UNLOCK_CHANNEL = 'unlock_channel';

    public const TYPE_ARCHIVE_CHANNEL = 'archive_channel';

    public const TYPE_UNARCHIVE_CHANNEL = 'unarchive_channel';

    public const TYPE_KICK_MEMBER = 'kick_member';

    public const TYPE_MUTE_MEMBER = 'mute_member';

    public const TYPE_UNMUTE_MEMBER = 'unmute_member';

    public const TYPE_ROLE_CHANGE = 'role_change';

    protected $fillable = [
        'actor_user_id',
        'channel_id',
        'message_id',
        'target_user_id',
        'action_type',
        'reason',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function scopeForChannel(Builder $q, int $channelId): Builder
    {
        return $q->where('channel_id', $channelId);
    }

    public function scopeRecent(Builder $q): Builder
    {
        return $q->latest()->limit(100);
    }
}
