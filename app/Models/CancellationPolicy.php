<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CancellationPolicy extends Model
{
    public const ACTOR_CLIENT = 'client';
    public const ACTOR_PROVIDER = 'provider';
    public const ACTOR_BOTH = 'both';

    protected $fillable = [
        'code', 'name', 'description', 'trade_codes',
        'actor_role', 'is_active', 'version',
        'valid_from', 'valid_until', 'metadata',
    ];

    protected $casts = [
        'trade_codes' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(CancellationPolicyTier::class, 'policy_id')->orderBy('position');
    }

    public function exemptReasons(): HasMany
    {
        return $this->hasMany(CancellationExemptReason::class, 'policy_id');
    }

    public function appliesToActor(string $actorRole): bool
    {
        return $this->actor_role === self::ACTOR_BOTH || $this->actor_role === $actorRole;
    }

    public function appliesToTrade(?string $tradeCode): bool
    {
        $trades = $this->trade_codes;
        if (! $trades || count($trades) === 0) {
            return true;
        }
        if (! $tradeCode) {
            return false;
        }
        return in_array($tradeCode, $trades, true);
    }

    public function isWithinValidity(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Carbon\Carbon::instance($at) : now();
        if ($this->valid_from && $at < $this->valid_from) {
            return false;
        }
        if ($this->valid_until && $at > $this->valid_until) {
            return false;
        }
        return true;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
