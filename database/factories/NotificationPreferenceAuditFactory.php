<?php

namespace Database\Factories;

use App\Models\NotificationPreferenceAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceAuditFactory extends Factory
{
    protected $model = NotificationPreferenceAudit::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'channel' => fake()->randomElement(['email', 'sms', 'push', 'in_app']),
            'category' => fake()->randomElement(['booking', 'payment', 'marketing', 'system']),
            'old_value' => true,
            'new_value' => false,
            'version_from' => 1,
            'version_to' => 2,
            'source' => 'user',
            'actor_user_id' => null,
            'ip_hash' => null,
            'user_agent_short' => null,
            'changed_at' => now(),
            'metadata' => null,
        ];
    }
}
