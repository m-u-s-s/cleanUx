<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 8 — Souscription Web Push d'un device pour un user.
 * Un user peut avoir plusieurs subscriptions (multi-device).
 */
class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'user_agent',
        'platform',
        'browser',
        'is_active',
        'failure_count',
        'last_failure_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'failure_count'   => 'integer',
        'last_failure_at' => 'datetime',
        'last_used_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /**
     * Format attendu par minishlink/web-push.
     */
    public function toWebPushArray(): array
    {
        return [
            'endpoint'        => $this->endpoint,
            'publicKey'       => $this->p256dh,
            'authToken'       => $this->auth,
            'contentEncoding' => 'aesgcm',
        ];
    }

    public function recordSuccess(): void
    {
        $this->update([
            'last_used_at'  => now(),
            'failure_count' => 0,
        ]);
    }

    public function recordFailure(): void
    {
        $this->increment('failure_count');
        $this->update(['last_failure_at' => now()]);

        // Auto-disable après 5 échecs
        if ($this->failure_count >= 5) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Hash de l'endpoint pour déduplication (sha256 de 64 chars).
     */
    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
