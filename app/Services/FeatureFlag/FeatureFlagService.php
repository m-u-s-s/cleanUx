<?php

namespace App\Services\FeatureFlag;

use App\Models\FeatureFlagOverride;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Simple feature flag service.
 *
 * Resolution order (first match wins):
 *   1. config/features.php value (bool) — kill switch
 *   2. percentage rollout — deterministic per (user_id + feature)
 *   3. explicit user list
 *   4. role-based
 *   5. default false (safe off)
 *
 * Usage:
 *   app(FeatureFlagService::class)->isEnabled('chat_v2', $user)
 *   feature('chat_v2', $user)       // helper
 *
 *   @feature('chat_v2')             // Blade directive
 */
class FeatureFlagService
{
    /** @var Collection<string,bool>|null memoised admin overrides (per resolved instance) */
    private ?Collection $overrides = null;

    public function isEnabled(string $feature, ?User $user = null): bool
    {
        // M18 — an admin DB override (kill-switch / force-on from FeatureFlagsManager) wins over
        // the config file, so toggling a flag in the admin UI actually takes effect at runtime.
        $override = $this->overrides()->get($feature);
        if ($override !== null) {
            return $override;
        }

        $flags = config('features', []);

        if (! array_key_exists($feature, $flags)) {
            return false;
        }

        $flag = $flags[$feature];

        if (is_bool($flag)) {
            return $flag;
        }

        if (! is_array($flag)) {
            return (bool) $flag;
        }

        // Explicit kill-switch overrides all other rules
        if (isset($flag['enabled']) && $flag['enabled'] === false) {
            return false;
        }

        if (isset($flag['percentage']) && $user) {
            return $this->matchesPercentage((int) $user->id, $feature, (int) $flag['percentage']);
        }

        if (isset($flag['users']) && $user) {
            return in_array($user->id, (array) $flag['users'], strict: true);
        }

        if (isset($flag['roles']) && $user) {
            // Resolve role string via matchesRole to avoid direct ->role access
            $allowedRoles = (array) $flag['roles'];
            foreach ($allowedRoles as $allowedRole) {
                if (method_exists($user, 'matchesRole') && $user->matchesRole((string) $allowedRole)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Admin overrides keyed by flag, loaded once per resolved instance (the service is a
     * per-request singleton). Soft-fails to an empty set if the table is absent.
     *
     * @return Collection<string,bool>
     */
    private function overrides(): Collection
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        try {
            if (! Schema::hasTable('feature_flag_overrides')) {
                return $this->overrides = collect();
            }

            return $this->overrides = FeatureFlagOverride::query()
                ->pluck('is_enabled', 'flag_key')
                ->map(fn ($v) => (bool) $v);
        } catch (\Throwable $e) {
            return $this->overrides = collect();
        }
    }

    /** Deterministic bucket: same user always falls in same side of the rollout. */
    private function matchesPercentage(int $userId, string $feature, int $percentage): bool
    {
        $bucket = abs(crc32($userId.':'.$feature)) % 100;

        return $bucket < $percentage;
    }
}
