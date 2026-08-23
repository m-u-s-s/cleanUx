<?php

namespace App\Models;

use Database\Factories\MessageReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réaction emoji à un message. */
class MessageReaction extends Model
{
    /** @use HasFactory<MessageReactionFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
