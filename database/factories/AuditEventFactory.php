<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => fake()->randomElement(['user.login', 'booking.created', 'payment.captured', 'admin.action']),
            'domain' => fake()->randomElement(['auth', 'booking', 'payment', 'admin', 'gdpr']),
            'severity' => fake()->randomElement(['info', 'warning', 'error', 'critical']),
            'actor_type' => fake()->randomElement(['user', 'system', 'webhook', 'job']),
            'actor_id' => fake()->numberBetween(1, 100),
            'actor_label' => fake()->name(),
            'subject_type' => fake()->optional()->randomElement(['App\\Models\\Booking', 'App\\Models\\User']),
            'subject_id' => fake()->optional()->numberBetween(1, 1000),
            'context' => ['action' => fake()->word()],
            'ip_hash' => fake()->sha256(),
            'request_id' => fake()->uuid(),
            'idempotency_key' => fake()->uuid(),
            'is_pinned' => false,
            'occurred_at' => now(),
        ];
    }
}
