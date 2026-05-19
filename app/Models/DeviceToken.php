<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceToken extends Model
{
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_WEB = 'web';

    public const PROVIDER_FCM = 'fcm';
    public const PROVIDER_APNS = 'apns';
    public const PROVIDER_MOCK = 'mock';

    protected $fillable = [
        'user_id',
        'platform',
        'provider',
        'token',
        'token_hash',
        'app_version',
        'locale',
        'timezone',
        'device_model',
        'last_used_at',
        'invalidated_at',
        'invalidation_reason',
        'preferences',
        'metadata',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'preferences' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PushNotification::class);
    }

    public function isActive(): bool
    {
        return $this->invalidated_at === null;
    }

    public function isOptedInFor(string $category): bool
    {
        $prefs = $this->preferences ?? [];

        if (array_key_exists($category, $prefs)) {
            return (bool) $prefs[$category];
        }

        $default = (array) (config('push.categories.' . $category, []));
        return (bool) ($default['default_opt_in'] ?? true);
    }

    public function invalidate(string $reason): void
    {
        $this->forceFill([
            'invalidated_at' => now(),
            'invalidation_reason' => $reason,
        ])->save();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('invalidated_at');
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
