<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        $platform = fake()->randomElement([
            DeviceToken::PLATFORM_IOS,
            DeviceToken::PLATFORM_ANDROID,
            DeviceToken::PLATFORM_WEB,
        ]);

        $provider = $platform === DeviceToken::PLATFORM_IOS
            ? DeviceToken::PROVIDER_APNS
            : DeviceToken::PROVIDER_FCM;

        $token = Str::random(64);

        return [
            'user_id' => User::factory(),
            'platform' => $platform,
            'provider' => $provider,
            'token' => $token,
            'token_hash' => DeviceToken::hashToken($token),
            'app_version' => fake()->semver(),
            'locale' => fake()->randomElement(['fr', 'nl', 'en']),
            'timezone' => fake()->timezone(),
            'device_model' => fake()->randomElement(['iPhone 14', 'Pixel 7', 'Samsung S23']),
            'last_used_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
            'invalidated_at' => null,
            'invalidation_reason' => null,
            'preferences' => [],
            'metadata' => [],
        ];
    }

    public function invalidated(): static
    {
        return $this->state(fn () => [
            'invalidated_at' => now()->subHours(2),
            'invalidation_reason' => 'token_rotated',
        ]);
    }
}
