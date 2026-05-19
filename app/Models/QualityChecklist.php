<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityChecklist extends Model
{
    public const PHASE_PRE = 'pre';
    public const PHASE_DURING = 'during';
    public const PHASE_POST = 'post';
    public const PHASE_ALL = 'all';

    protected $fillable = [
        'code', 'name', 'description', 'trade_codes',
        'phase', 'is_active', 'version',
        'parent_template_id', 'metadata',
    ];

    protected $casts = [
        'trade_codes' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(QualityChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopePhase(Builder $q, string $phase): Builder
    {
        return $q->whereIn('phase', [$phase, self::PHASE_ALL]);
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
}
