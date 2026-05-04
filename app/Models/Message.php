<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'content',
        'type',
        'parent_id',
        'metadata',
        'edited_at',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'edited_at' => 'datetime',
    ];

    public const TYPE_TEXT   = 'text';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_FILE   = 'file';

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }
}

// ──────────────────────────────────────────────────────────────
// MessageReaction
// ──────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    protected $fillable = ['message_id', 'user_id', 'emoji'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
