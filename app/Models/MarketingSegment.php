<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSegment extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'rules', 'is_active',
        'member_count', 'last_computed_at', 'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'rules' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'member_count' => 'integer',
        'last_computed_at' => 'datetime',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(MarketingSegmentMember::class, 'segment_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'marketing_segment_members', 'segment_id', 'user_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
