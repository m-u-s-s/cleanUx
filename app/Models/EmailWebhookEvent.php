<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailWebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'provider_event_id', 'provider_message_id',
        'email_message_id', 'event_type', 'occurred_at', 'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
