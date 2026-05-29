<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiTokenUsage extends Model
{
    protected $fillable = [
        'token_id', 'route_path', 'method',
        'response_status', 'latency_ms', 'response_size_bytes',
        'ip_hash', 'user_agent_short',
        'metadata', 'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'response_status' => 'integer',
        'latency_ms' => 'integer',
        'response_size_bytes' => 'integer',
    ];

    public function scopeForToken(Builder $q, int $tokenId): Builder
    {
        return $q->where('token_id', $tokenId);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->whereBetween('response_status', [400, 599]);
    }
}
