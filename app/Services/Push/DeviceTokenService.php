<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceTokenService
{
    /**
     * Register (ou upsert) un device token pour un user.
     */
    public function register(
        User $user,
        string $token,
        string $platform,
        string $provider,
        ?string $appVersion = null,
        ?string $locale = null,
        ?string $timezone = null,
        ?string $deviceModel = null,
    ): DeviceToken {
        if (! in_array($platform, [DeviceToken::PLATFORM_IOS, DeviceToken::PLATFORM_ANDROID, DeviceToken::PLATFORM_WEB], true)) {
            throw ValidationException::withMessages(['platform' => 'Plateforme non supportée.']);
        }

        if (! in_array($provider, [DeviceToken::PROVIDER_FCM, DeviceToken::PROVIDER_APNS, DeviceToken::PROVIDER_MOCK], true)) {
            throw ValidationException::withMessages(['provider' => 'Provider push inconnu.']);
        }

        $token = trim($token);
        if ($token === '' || strlen($token) > 4000) {
            throw ValidationException::withMessages(['token' => 'Token push invalide.']);
        }

        $hash = DeviceToken::hashToken($token);

        return DB::transaction(function () use ($user, $token, $hash, $platform, $provider, $appVersion, $locale, $timezone, $deviceModel) {
            $existing = DeviceToken::query()->where('token_hash', $hash)->first();

            if ($existing) {
                $existing->forceFill([
                    'user_id' => $user->id,
                    'platform' => $platform,
                    'provider' => $provider,
                    'app_version' => $appVersion ?? $existing->app_version,
                    'locale' => $locale ?? $existing->locale,
                    'timezone' => $timezone ?? $existing->timezone,
                    'device_model' => $deviceModel ?? $existing->device_model,
                    'last_used_at' => now(),
                    'invalidated_at' => null,
                    'invalidation_reason' => null,
                ])->save();

                ActivityLogger::log('push.token_refreshed', $existing, ['user_id' => $user->id]);

                return $existing->fresh();
            }

            $created = DeviceToken::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'provider' => $provider,
                'token' => $token,
                'token_hash' => $hash,
                'app_version' => $appVersion,
                'locale' => $locale ?? $user->preferredLocale() ?? null,
                'timezone' => $timezone,
                'device_model' => $deviceModel,
                'preferences' => $this->defaultPreferences(),
                'last_used_at' => now(),
            ]);

            ActivityLogger::log('push.token_registered', $created, ['user_id' => $user->id]);

            return $created;
        });
    }

    public function unregister(User $user, string $token): bool
    {
        $hash = DeviceToken::hashToken(trim($token));

        $row = DeviceToken::query()
            ->where('token_hash', $hash)
            ->where('user_id', $user->id)
            ->first();

        if (! $row) {
            return false;
        }

        $row->invalidate('user_unregistered');

        ActivityLogger::log('push.token_unregistered', $row, ['user_id' => $user->id]);

        return true;
    }

    public function updatePreferences(DeviceToken $token, array $preferences): DeviceToken
    {
        $allowed = ['transactional', 'verification', 'reminder', 'marketing'];

        $clean = [];
        foreach ($preferences as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $clean[$key] = (bool) $value;
            }
        }

        $token->forceFill([
            'preferences' => array_merge((array) $token->preferences, $clean),
        ])->save();

        ActivityLogger::log('push.preferences_updated', $token, [
            'user_id' => $token->user_id,
            'preferences' => $clean,
        ]);

        return $token->fresh();
    }

    public function defaultPreferences(): array
    {
        $defaults = [];
        foreach ((array) Config::get('push.categories', []) as $cat => $meta) {
            $defaults[$cat] = (bool) ($meta['default_opt_in'] ?? true);
        }
        return $defaults;
    }
}
