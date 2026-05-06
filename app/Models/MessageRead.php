<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read receipt : un user a lu un message.
 *
 * Combinaison (message_id, user_id) UNIQUE → un user ne marque qu'une fois.
 * Permet de calculer le compteur "messages non lus par canal" :
 *
 *   $unread = Message::query()
 *       ->where('channel_id', $channelId)
 *       ->whereNotIn('id', MessageRead::where('user_id', $userId)->pluck('message_id'))
 *       ->count();
 */
class MessageRead extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
