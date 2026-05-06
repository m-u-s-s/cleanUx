<?php

namespace App\Services\Presence;

use App\Events\Presence\UserPresenceChanged;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 3 — Service de présence léger (cache-based).
 *
 * Stocke le statut explicite et le "last seen" de chaque utilisateur dans le cache.
 * Le presence channel d'Echo gère la vraie présence "online/offline" automatiquement
 * (via heartbeat WebSocket) — ce service est pour les statuts manuels et pour le
 * fallback "vu il y a X minutes" quand l'utilisateur est offline.
 *
 * Usage :
 *   PresenceTracker::setStatus($user, UserPresenceChanged::STATUS_BUSY, 'En réunion');
 *   PresenceTracker::touch($user); // last seen = maintenant
 *   $info = PresenceTracker::get($user);
 */
class PresenceTracker
{
    private const TTL_LAST_SEEN_SEC = 600;  // 10 min
    private const TTL_STATUS_SEC    = 86400; // 24h

    public static function setStatus(User $user, string $status, ?string $customMessage = null): void
    {
        Cache::put(
            self::statusKey($user->id),
            [
                'status'         => $status,
                'custom_message' => $customMessage,
                'set_at'         => now()->toIso8601String(),
            ],
            self::TTL_STATUS_SEC
        );

        // Diffuse à toute l'organisation
        broadcast(new UserPresenceChanged(
            user: $user,
            status: $status,
            customMessage: $customMessage,
            organizationAccountId: $user->organization_account_id,
        ))->toOthers();
    }

    public static function touch(User $user): void
    {
        Cache::put(self::lastSeenKey($user->id), now()->toIso8601String(), self::TTL_LAST_SEEN_SEC);
    }

    public static function isOnline(int $userId): bool
    {
        return Cache::has(self::lastSeenKey($userId));
    }

    public static function get(User $user): array
    {
        return [
            'status'    => Cache::get(self::statusKey($user->id), [
                'status'         => UserPresenceChanged::STATUS_AVAILABLE,
                'custom_message' => null,
                'set_at'         => null,
            ]),
            'last_seen' => Cache::get(self::lastSeenKey($user->id)),
            'online'    => self::isOnline($user->id),
        ];
    }

    public static function clear(User $user): void
    {
        Cache::forget(self::statusKey($user->id));
        Cache::forget(self::lastSeenKey($user->id));
    }

    // ──────────────────────────────────────────────────────
    // Helpers internes : clés cache
    // ──────────────────────────────────────────────────────

    private static function statusKey(int $userId): string
    {
        return "presence:status:user:{$userId}";
    }

    private static function lastSeenKey(int $userId): string
    {
        return "presence:lastseen:user:{$userId}";
    }
}
