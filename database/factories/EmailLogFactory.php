<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'template_key'       => fake()->randomElement(['welcome', 'booking_confirmed', 'invoice', 'reminder']),
            'subject'            => fake()->sentence(),
            'status'             => 'sent',
            'channel'            => 'email',
            'recipient_email'    => fake()->safeEmail(),
            'notifiable_type'    => User::class,
            'notifiable_id'      => fn () => User::factory()->create()->id,
            'previewed_by_user_id' => null,
            'context'            => null,
            'meta'               => null,
            'sent_at'            => now(),
            'failed_at'          => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'    => 'failed',
            'sent_at'   => null,
            'failed_at' => now(),
        ]);
    }
}
