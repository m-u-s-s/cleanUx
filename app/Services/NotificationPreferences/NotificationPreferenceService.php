<?php

namespace App\Services\NotificationPreferences;

use App\Models\DeviceToken;
use App\Models\MarketingOptOut;
use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * NotificationPreferenceService — single source of truth pour les préférences notif.
 *
 *   - isAllowed(user, channel, category) : check rapide pour le caller (sms/push/email/etc.)
 *   - getPreferences(user) : matrice complète, init lazy depuis default_matrix
 *   - setPreference(user, channel, category, allowed, source, request) : versionné + audit logged
 *   - setMany(user, prefs[]) : bulk
 *   - syncToExternalModules(user) : propage vers push device_tokens + marketing_opt_outs
 *
 * Forced-on (config 'forced_on') : refusé silencieusement si user essaie de désactiver.
 */
class NotificationPreferenceService
{
    public function isAllowed(User $user, string $channel, string $category): bool
    {
        if (! Config::get('notification_preferences.enabled', true)) {
            return true;
        }

        $pref = NotificationPreference::query()
            ->forUser($user->id)
            ->where('channel', $channel)
            ->where('category', $category)
            ->first();

        if ($pref) {
            return (bool) $pref->is_allowed;
        }

        // Fallback to default matrix
        return $this->defaultFor($channel, $category);
    }

    /**
     * Returns full matrix for a user, initialising missing pairs from defaults.
     *
     * @return array<string, array<string, bool>>  [channel][category] => bool
     */
    public function getPreferences(User $user): array
    {
        $channels = (array) Config::get('notification_preferences.channels', []);
        $categories = (array) Config::get('notification_preferences.categories', []);

        $existing = NotificationPreference::query()
            ->forUser($user->id)
            ->get()
            ->keyBy(fn ($p) => $p->channel . ':' . $p->category);

        $matrix = [];
        foreach ($channels as $ch) {
            foreach ($categories as $cat) {
                $key = "{$ch}:{$cat}";
                $matrix[$ch][$cat] = $existing->has($key)
                    ? (bool) $existing->get($key)->is_allowed
                    : $this->defaultFor($ch, $cat);
            }
        }
        return $matrix;
    }

    /**
     * Set a single preference. Versioned + audit logged + cross-module synced.
     */
    public function setPreference(
        User $user,
        string $channel,
        string $category,
        bool $isAllowed,
        string $source = NotificationPreference::SOURCE_USER,
        ?Request $request = null,
        ?User $actor = null,
    ): NotificationPreference {
        $this->ensureValid($channel, $category);

        // Forced-on : refuse silently if user tries to disable
        if (! $isAllowed && $this->isForcedOn($channel, $category)) {
            throw ValidationException::withMessages([
                'channel_category' => "La paire {$channel}/{$category} est obligatoire (légal/sécurité) et ne peut être désactivée.",
            ]);
        }

        return DB::transaction(function () use ($user, $channel, $category, $isAllowed, $source, $request, $actor) {
            $pref = NotificationPreference::query()
                ->forUser($user->id)
                ->where('channel', $channel)
                ->where('category', $category)
                ->first();

            $oldValue = $pref?->is_allowed;
            $oldVersion = $pref?->version;

            $ipHash = $request?->ip() ? hash('sha256', (string) $request->ip()) : null;

            $newVersion = ($oldVersion ?? 0) + 1;

            if ($pref) {
                $pref->forceFill([
                    'is_allowed' => $isAllowed,
                    'version' => $newVersion,
                    'source' => $source,
                    'updated_via_ip_hash' => $ipHash,
                    'last_changed_at' => now(),
                ])->save();
            } else {
                $pref = NotificationPreference::create([
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'category' => $category,
                    'is_allowed' => $isAllowed,
                    'version' => $newVersion,
                    'source' => $source,
                    'updated_via_ip_hash' => $ipHash,
                    'last_changed_at' => now(),
                ]);
            }

            NotificationPreferenceAudit::create([
                'user_id' => $user->id,
                'channel' => $channel,
                'category' => $category,
                'old_value' => $oldValue,
                'new_value' => $isAllowed,
                'version_from' => $oldVersion,
                'version_to' => $newVersion,
                'source' => $source,
                'actor_user_id' => $actor?->id ?? Auth::id(),
                'ip_hash' => $ipHash,
                'user_agent_short' => $request?->userAgent() ? Str::limit((string) $request->userAgent(), 191, '') : null,
                'changed_at' => now(),
            ]);

            $this->syncToExternalModules($user, $channel, $category, $isAllowed);

            ActivityLogger::log('notification_preference.changed', $pref, [
                'channel' => $channel,
                'category' => $category,
                'is_allowed' => $isAllowed,
                'source' => $source,
            ]);

            return $pref->fresh();
        });
    }

    /**
     * Bulk update preferences.
     *
     * @param array<int, array{channel: string, category: string, is_allowed: bool}> $prefs
     */
    public function setMany(User $user, array $prefs, string $source = NotificationPreference::SOURCE_USER, ?Request $request = null): array
    {
        $results = [];
        foreach ($prefs as $p) {
            $channel = (string) ($p['channel'] ?? '');
            $category = (string) ($p['category'] ?? '');
            $allowed = (bool) ($p['is_allowed'] ?? true);

            try {
                $results[] = $this->setPreference($user, $channel, $category, $allowed, $source, $request);
            } catch (ValidationException $e) {
                // skip forced-on; continue
                continue;
            }
        }
        return $results;
    }

    /**
     * Apply default matrix to a user (idempotent, only creates missing pairs).
     */
    public function applyDefaultsFor(User $user): int
    {
        $matrix = (array) Config::get('notification_preferences.default_matrix', []);
        $existing = NotificationPreference::query()
            ->forUser($user->id)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->channel . ':' . $p->category => true])
            ->all();

        $inserted = 0;
        foreach ($matrix as $channel => $categories) {
            foreach ((array) $categories as $category => $allowed) {
                if (isset($existing["{$channel}:{$category}"])) {
                    continue;
                }
                NotificationPreference::create([
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'category' => $category,
                    'is_allowed' => (bool) $allowed,
                    'version' => 1,
                    'source' => NotificationPreference::SOURCE_DEFAULT,
                    'last_changed_at' => now(),
                ]);
                $inserted++;
            }
        }
        return $inserted;
    }

    /**
     * Cross-module sync. Called automatically by setPreference.
     */
    public function syncToExternalModules(User $user, string $channel, string $category, bool $isAllowed): void
    {
        $syncCfg = (array) Config::get('notification_preferences.sync_to_modules', []);

        // Push sync : update preferences JSON on all active device_tokens of the user (filtered by category)
        if (! empty($syncCfg['push']) && $channel === 'push' && class_exists(DeviceToken::class)) {
            try {
                DeviceToken::query()
                    ->where('user_id', $user->id)
                    ->whereNull('invalidated_at')
                    ->get()
                    ->each(function (DeviceToken $token) use ($category, $isAllowed) {
                        $prefs = (array) ($token->preferences ?? []);
                        $prefs[$category] = $isAllowed;
                        $token->forceFill(['preferences' => $prefs])->save();
                    });
            } catch (\Throwable $e) {
                Log::warning('NotificationPreferenceService::syncToExternalModules push failed', [
                    'user_id' => $user->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Marketing sync : when category=marketing OR support|product, create/remove marketing_opt_out row
        if (! empty($syncCfg['marketing']) && class_exists(MarketingOptOut::class)) {
            // We only sync marketing-like categories to the marketing module table for now.
            if (in_array($category, ['marketing', 'product'], true)) {
                try {
                    if ($isAllowed) {
                        MarketingOptOut::query()
                            ->where('user_id', $user->id)
                            ->where('channel', $channel)
                            ->delete();
                    } else {
                        MarketingOptOut::query()->updateOrCreate(
                            ['user_id' => $user->id, 'channel' => $channel],
                            [
                                'opted_out_at' => now(),
                                'reason' => 'synced from notification_preferences',
                            ],
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('NotificationPreferenceService::syncToExternalModules marketing failed', [
                        'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function defaultFor(string $channel, string $category): bool
    {
        $matrix = (array) Config::get('notification_preferences.default_matrix', []);
        return (bool) ($matrix[$channel][$category] ?? false);
    }

    public function isForcedOn(string $channel, string $category): bool
    {
        $forced = (array) Config::get('notification_preferences.forced_on', []);
        foreach ($forced as $row) {
            if (($row['channel'] ?? null) === $channel && ($row['category'] ?? null) === $category) {
                return true;
            }
        }
        return false;
    }

    protected function ensureValid(string $channel, string $category): void
    {
        $channels = (array) Config::get('notification_preferences.channels', []);
        $categories = (array) Config::get('notification_preferences.categories', []);

        if (! in_array($channel, $channels, true)) {
            throw ValidationException::withMessages(['channel' => "Channel '{$channel}' non supporté."]);
        }
        if (! in_array($category, $categories, true)) {
            throw ValidationException::withMessages(['category' => "Category '{$category}' non supportée."]);
        }
    }
}
