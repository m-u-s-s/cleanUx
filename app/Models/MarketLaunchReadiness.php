<?php

namespace App\Models;

use Database\Factories\MarketLaunchReadinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool $catalog_ready
 * @property bool $booking_ready
 * @property bool $mission_ready
 * @property bool $billing_ready
 * @property bool $partner_network_ready
 * @property bool $compliance_ready
 * @property bool $support_ready
 * @property ?Carbon $last_audited_at
 * @property ?array $metadata
 */
class MarketLaunchReadiness extends Model
{
    /** @use HasFactory<MarketLaunchReadinessFactory> */
    use HasFactory;

    protected $table = 'market_launch_readiness';

    protected $fillable = [
        'country_id',
        'catalog_ready',
        'booking_ready',
        'mission_ready',
        'billing_ready',
        'partner_network_ready',
        'compliance_ready',
        'support_ready',
        'notes',
        'last_audited_at',
        'metadata',
    ];

    protected $casts = [
        'catalog_ready' => 'boolean',
        'booking_ready' => 'boolean',
        'mission_ready' => 'boolean',
        'billing_ready' => 'boolean',
        'partner_network_ready' => 'boolean',
        'compliance_ready' => 'boolean',
        'support_ready' => 'boolean',
        'last_audited_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getReadinessScoreAttribute(): int
    {
        $flags = [
            $this->catalog_ready,
            $this->booking_ready,
            $this->mission_ready,
            $this->billing_ready,
            $this->partner_network_ready,
            $this->compliance_ready,
            $this->support_ready,
        ];

        return (int) round((collect($flags)->filter()->count() / count($flags)) * 100);
    }
}
