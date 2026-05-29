<?php

namespace App\Services\WebView;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues and redeems opaque, single-use, short-lived tickets that hand a
 * mobile (Sanctum-authenticated) user off into a web session inside a WebView.
 *
 * The ticket string is a cryptographically-random opaque secret. Its SHA-256
 * is the cache key; the cache value carries the binding payload. Redemption is
 * atomic (Cache::pull = get+forget) so a ticket can be used at most once.
 */
class WebViewTicketService
{
    private const TTL_SECONDS = 60;

    private const PREFIX = 'webview_ticket:';

    public function issue(User $user, string $deviceId, string $targetPath): string
    {
        $ticket = Str::random(64);

        Cache::put(self::key($ticket), [
            'user_id' => $user->id,
            'token_id' => optional($user->currentAccessToken())->id,
            'device_id' => $deviceId,
            'target_path' => $targetPath,
        ], self::TTL_SECONDS);

        return $ticket;
    }

    /** @return array{user_id:int,token_id:int|null,device_id:string,target_path:string}|null */
    public function redeem(string $ticket): ?array
    {
        if ($ticket === '') {
            return null;
        }

        return Cache::pull(self::key($ticket));
    }

    private static function key(string $ticket): string
    {
        return self::PREFIX.hash('sha256', $ticket);
    }
}
