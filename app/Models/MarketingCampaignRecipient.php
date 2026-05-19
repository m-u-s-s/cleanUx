<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignRecipient extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_FAILED = 'failed';
    public const STATUS_OPTED_OUT = 'opted_out';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'campaign_id', 'step_id', 'user_id', 'channel', 'status',
        'idempotency_key', 'scheduled_for', 'sent_at', 'delivered_at',
        'opened_at', 'clicked_at', 'failed_at', 'failed_reason',
        'variant_label', 'metadata',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignStep::class, 'step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeReadyToSend(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_QUEUED)
            ->where('scheduled_for', '<=', now());
    }
}
