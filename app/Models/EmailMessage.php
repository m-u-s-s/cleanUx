<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EmailMessage extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_COMPLAINED = 'complained';
    public const STATUS_FAILED = 'failed';

    public const CATEGORY_TRANSACTIONAL = 'transactional';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_NOTIFICATION = 'notification';
    public const CATEGORY_SYSTEM = 'system';

    protected $fillable = [
        'code', 'provider', 'provider_message_id',
        'to_email', 'to_name', 'to_user_id',
        'from_email', 'from_name', 'reply_to',
        'subject', 'body_html', 'body_text',
        'cc', 'bcc', 'attachments', 'headers',
        'category', 'template_code', 'locale', 'status',
        'queued_at', 'sent_at', 'delivered_at', 'opened_at',
        'clicked_at', 'bounced_at', 'complained_at',
        'last_error', 'attempts', 'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'cc' => 'array',
        'bcc' => 'array',
        'attachments' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public static function generateCode(): string
    {
        return 'em_' . Str::lower(Str::random(20));
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(EmailWebhookEvent::class);
    }

    public function scopeForRecipient(Builder $q, string $email): Builder
    {
        return $q->where('to_email', $email);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_FAILED, self::STATUS_BOUNCED]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED, self::STATUS_BOUNCED,
            self::STATUS_FAILED, self::STATUS_COMPLAINED,
        ], true);
    }
}
