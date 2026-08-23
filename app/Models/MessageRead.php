<?php

namespace App\Models;

use Database\Factories\MessageReadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Read receipt : un user a lu un message. */
class MessageRead extends Model
{
    /** @use HasFactory<MessageReadFactory> */
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
