<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaskedCallSessionFactory extends Factory
{
    protected $model = MaskedCallSession::class;

    public function definition(): array
    {
        return [
            'code' => MaskedCallSession::generateCode(),
            'booking_id' => fn () => Booking::factory()->create()->id,
            'client_user_id' => fn () => User::factory()->create()->id,
            'provider_user_id' => fn () => User::factory()->create()->id,
            'twilio_session_sid' => null,
            'proxy_phone_number' => '+32'.fake()->numerify('4########'),
            'client_phone_masked' => '+32****5678',
            'provider_phone_masked' => '+32****9012',
            'status' => MaskedCallSession::STATUS_ACTIVE,
            'expires_at' => now()->addHours(24),
            'closed_at' => null,
            'calls_count' => 0,
            'sms_count' => 0,
            'metadata' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => MaskedCallSession::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => MaskedCallSession::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }
}
