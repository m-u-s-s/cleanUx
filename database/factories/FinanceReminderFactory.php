<?php

namespace Database\Factories;

use App\Models\FinanceInvoice;
use App\Models\FinanceReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceReminderFactory extends Factory
{
    protected $model = FinanceReminder::class;

    public function definition(): array
    {
        return [
            'finance_invoice_id' => fn () => FinanceInvoice::factory()->create()->id,
            'reminder_type'      => fake()->randomElement(['first', 'second', 'final']),
            'channel'            => 'email',
            'status'             => 'sent',
            'recipient_email'    => fake()->safeEmail(),
            'sent_at'            => now(),
            'error_message'      => null,
            'meta'               => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'        => 'failed',
            'sent_at'       => null,
            'error_message' => 'Delivery failed',
        ]);
    }
}
