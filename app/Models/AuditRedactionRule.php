<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditRedactionRule extends Model
{
    public const MATCH_KEY = 'key';
    public const MATCH_REGEX = 'regex';
    public const MATCH_PATH = 'path';

    protected $fillable = [
        'code', 'name', 'pattern', 'match_type',
        'replacement', 'scope_domain', 'is_active', 'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('priority');
    }

    public function appliesToDomain(?string $domain): bool
    {
        return $this->scope_domain === null || $this->scope_domain === $domain;
    }
}
