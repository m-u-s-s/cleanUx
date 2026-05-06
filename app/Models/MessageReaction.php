<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réaction emoji à un message.
 *
 * Phase 4 — extrait de Message.php (qui contenait 2 namespaces dans
 * un même fichier, violant PSR-1, et référençait une table inexistante).
 *
 * Une combinaison (message_id, user_id, emoji) est UNIQUE :
 * un user ne peut pas mettre 👍 deux fois sur le même message.
 */
class MessageReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
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
