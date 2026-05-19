<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    public const TYPE_TOS = 'tos';
    public const TYPE_SLA = 'sla';
    public const TYPE_CLIENT_AGREEMENT = 'client_agreement';
    public const TYPE_PROVIDER_AGREEMENT = 'provider_agreement';
    public const TYPE_NDA = 'nda';
    public const TYPE_OTHER = 'other';

    public const ROLE_CLIENT = 'client';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_ENTERPRISE = 'enterprise';
    public const ROLE_ALL = 'all';

    protected $fillable = [
        'code', 'name', 'description',
        'type', 'role', 'version',
        'body_markdown', 'body_locale_overrides', 'variables',
        'is_active', 'valid_from', 'valid_until',
        'supersedes_template_id', 'metadata',
    ];

    protected $casts = [
        'body_locale_overrides' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class, 'template_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_template_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType(Builder $q, string $type, ?string $role = null): Builder
    {
        return $q->where('type', $type)->when($role, function ($w) use ($role) {
            $w->whereIn('role', [$role, self::ROLE_ALL]);
        });
    }

    public function bodyForLocale(?string $locale): string
    {
        if (! $locale) {
            return $this->body_markdown;
        }
        $overrides = (array) $this->body_locale_overrides;
        return $overrides[$locale] ?? $this->body_markdown;
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
}
