<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AnalyticsService — central event tracker (Phase Analytics v2).
 *
 *   - track(name, properties, options) → AnalyticsEvent
 *   - identify(anonymousId, user) → relie le tracking pré-auth au user
 *   - resolveSession(request, user, anonymousId) → AnalyticsSession (lazy create)
 *   - PII sanitization (drop_keys, hash_keys, length clamp, prop count clamp)
 *   - Idempotency via UNIQUE idempotency_key
 *   - Soft-fail (un Log warning) sur erreur, ne casse jamais le flow business
 */
class AnalyticsService
{
    /**
     * Public entrypoint.
     *
     * @param array<string,mixed> $options
     *   - user: ?User (optional)
     *   - source: ?Model (booking, mission, etc.) — fills source_type/source_id via properties.source
     *   - session_id: ?string
     *   - anonymous_id: ?string
     *   - request: ?Request (for IP/locale/url inference)
     *   - idempotency_key: ?string
     *   - revenue_cents: ?int
     *   - currency: ?string
     *   - category: ?string
     *   - occurred_at: ?\DateTimeInterface
     */
    public function track(string $name, array $properties = [], array $options = []): ?AnalyticsEvent
    {
        if (! Config::get('analytics.enabled', true)) {
            return null;
        }

        $name = trim($name);
        $allowed = (array) Config::get('analytics.allowed_events', []);
        if (! empty($allowed) && ! in_array($name, $allowed, true)) {
            // Silently drop unknown events for safety (avoids client-injected pollution)
            return null;
        }

        if ($idempotencyKey = ($options['idempotency_key'] ?? null)) {
            $existing = AnalyticsEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $request = $options['request'] ?? request();
        $user = $options['user'] ?? null;

        try {
            $session = $this->resolveSession(
                request: $request instanceof Request ? $request : null,
                user: $user,
                explicitSessionId: $options['session_id'] ?? null,
                anonymousId: $options['anonymous_id'] ?? null,
            );

            $properties = $this->sanitize($properties);

            $event = AnalyticsEvent::create([
                'event_name' => $name,
                'event_category' => $options['category'] ?? $this->inferCategory($name),
                'session_id' => $session?->session_id,
                'user_id' => $user?->id,
                'anonymous_id' => $options['anonymous_id'] ?? $session?->anonymous_id,
                'properties' => $properties,
                'source' => $options['source_label'] ?? $this->inferSource($request),
                'platform' => $options['platform'] ?? null,
                'locale' => $options['locale'] ?? ($user?->preferredLocale() ?? app()->getLocale()),
                'country_code' => $options['country_code'] ?? null,
                'url' => $this->truncate(($options['url'] ?? $request?->fullUrl()), 500),
                'referrer' => $this->truncate(($options['referrer'] ?? $request?->headers->get('referer')), 500),
                'user_agent_short' => $this->truncate($request?->userAgent(), 191),
                'ip_hash' => $request ? hash('sha256', (string) $request->ip()) : null,
                'revenue_cents' => $options['revenue_cents'] ?? null,
                'currency' => $options['currency'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $options['occurred_at'] ?? now(),
            ]);

            if ($session) {
                $session->increment('event_count');
                $session->forceFill(['last_seen_at' => now()])->save();
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('AnalyticsService::track failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function identify(string $anonymousId, User $user): int
    {
        $updated = 0;

        $updated += AnalyticsEvent::query()
            ->where('anonymous_id', $anonymousId)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        $updated += AnalyticsSession::query()
            ->where('anonymous_id', $anonymousId)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        return $updated;
    }

    public function resolveSession(
        ?Request $request,
        ?User $user,
        ?string $explicitSessionId = null,
        ?string $anonymousId = null,
    ): ?AnalyticsSession {
        $sessionId = $explicitSessionId
            ?: ($request?->cookie(Config::get('analytics.session.cookie_name', 'cleanux_aid')))
            ?: null;

        if (! $sessionId) {
            $sessionId = 'sess_' . Str::lower(Str::random(20));
        }

        $session = AnalyticsSession::query()
            ->where('session_id', $sessionId)
            ->first();

        if ($session) {
            $inactivity = (int) Config::get('analytics.session.inactivity_minutes', 30);
            if ($session->isExpired($inactivity)) {
                $session->forceFill(['ended_at' => $session->last_seen_at])->save();
                $session = null;
                $sessionId = 'sess_' . Str::lower(Str::random(20));
            }
        }

        if (! $session) {
            $session = AnalyticsSession::create([
                'session_id' => $sessionId,
                'user_id' => $user?->id,
                'anonymous_id' => $anonymousId,
                'source' => $this->inferSource($request),
                'platform' => null,
                'locale' => $user?->preferredLocale() ?? ($request?->getPreferredLanguage() ?? app()->getLocale()),
                'first_url' => $this->truncate($request?->fullUrl(), 500),
                'first_referrer' => $this->truncate($request?->headers->get('referer'), 500),
                'user_agent_short' => $this->truncate($request?->userAgent(), 191),
                'started_at' => now(),
                'last_seen_at' => now(),
            ]);
        } elseif ($user && ! $session->user_id) {
            $session->forceFill(['user_id' => $user->id])->save();
        }

        return $session;
    }

    public function sanitize(array $properties): array
    {
        $cfg = (array) Config::get('analytics.sanitize', []);
        $hashKeys = (array) ($cfg['hash_keys'] ?? []);
        $dropKeys = (array) ($cfg['drop_keys'] ?? []);
        $maxStr = (int) ($cfg['max_string_length'] ?? 2000);
        $maxProps = (int) ($cfg['max_properties'] ?? 50);

        $clean = [];
        foreach ($properties as $k => $v) {
            $k = (string) $k;

            if (in_array(strtolower($k), array_map('strtolower', $dropKeys), true)) {
                continue;
            }

            if (in_array(strtolower($k), array_map('strtolower', $hashKeys), true)) {
                if ($v !== null && $v !== '') {
                    $clean[$k] = 'sha256:' . substr(hash('sha256', (string) $v), 0, 16);
                }
                continue;
            }

            if (is_string($v) && strlen($v) > $maxStr) {
                $v = substr($v, 0, $maxStr);
            }

            $clean[$k] = $v;

            if (count($clean) >= $maxProps) {
                break;
            }
        }

        return $clean;
    }

    protected function inferCategory(string $name): string
    {
        if (str_starts_with($name, 'user.') || str_starts_with($name, 'booking.') || str_starts_with($name, 'rating.') || str_starts_with($name, 'kyc.')) {
            return AnalyticsEvent::CATEGORY_LIFECYCLE;
        }
        if (str_starts_with($name, 'search.') || str_starts_with($name, 'provider.') || str_starts_with($name, 'page.') || str_starts_with($name, 'checkout.')) {
            return AnalyticsEvent::CATEGORY_FUNNEL;
        }
        if (str_starts_with($name, 'promo.') || str_starts_with($name, 'loyalty.')) {
            return AnalyticsEvent::CATEGORY_TRANSACTION;
        }
        if (str_starts_with($name, 'error.')) {
            return AnalyticsEvent::CATEGORY_ERROR;
        }
        return AnalyticsEvent::CATEGORY_ENGAGEMENT;
    }

    protected function inferSource(?Request $request): string
    {
        if (! $request) {
            return 'server';
        }
        $ua = strtolower((string) $request->userAgent());
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }
        if (str_starts_with($request->path(), 'api/')) {
            return 'api';
        }
        return 'web';
    }

    protected function truncate(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}
