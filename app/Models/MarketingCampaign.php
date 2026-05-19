<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    public const TYPE_SINGLE_BLAST = 'single_blast';
    public const TYPE_DRIP_SEQUENCE = 'drip_sequence';
    public const TYPE_TRIGGERED = 'triggered';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'name', 'description', 'type', 'status',
        'segment_id', 'scheduled_at', 'started_at', 'ended_at',
        'ab_test_config', 'opt_in_required', 'locale',
        'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'ab_test_config' => 'array',
        'opt_in_required' => 'boolean',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'segment_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MarketingCampaignStep::class, 'campaign_id')->orderBy('position');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class, 'campaign_id');
    }

    public function scopeRunning(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_RUNNING]);
    }
}
