<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PromoCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'budget_cap',
        'total_discounted',
        'total_redemptions',
        'target_audience',
        'metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'budget_cap' => 'decimal:2',
        'total_discounted' => 'decimal:2',
        'total_redemptions' => 'integer',
        'metadata' => 'array',
    ];

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isRunning(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->greaterThan($at)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lessThan($at)) {
            return false;
        }

        return true;
    }

    public function hasBudgetRemaining(): bool
    {
        if ($this->budget_cap === null) {
            return true;
        }

        return (float) $this->total_discounted < (float) $this->budget_cap;
    }

    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
