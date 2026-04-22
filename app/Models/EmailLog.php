<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'subject',
        'status',
        'channel',
        'recipient_email',
        'notifiable_type',
        'notifiable_id',
        'previewed_by_user_id',
        'context',
        'meta',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'meta' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function previewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previewed_by_user_id');
    }
}
