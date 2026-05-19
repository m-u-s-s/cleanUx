<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignStep extends Model
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';

    protected $fillable = [
        'campaign_id', 'position', 'delay_minutes', 'channel',
        'subject', 'template_code', 'variant_label', 'is_active',
        'content_overrides',
    ];

    protected $casts = [
        'position' => 'integer',
        'delay_minutes' => 'integer',
        'is_active' => 'boolean',
        'content_overrides' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
