<?php

namespace Database\Factories;

use App\Models\SubscriptionsV2\SubscriptionInvoiceV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionInvoiceV2Factory extends Factory
{
    protected $model = SubscriptionInvoiceV2::class;

    public function definition(): array
    {
        return [
            'code' => SubscriptionInvoiceV2::generateCode(),
            'subscription_id' => fn () => SubscriptionV2::factory()->create()->id,
            'cycle_id' => null,
            'amount_cents' => fake()->numberBetween(1000, 50000),
            'currency' => 'EUR',
            'status' => SubscriptionInvoiceV2::STATUS_OPEN,
            'stripe_invoice_id' => null,
            'due_at' => now()->addDays(3),
            'paid_at' => null,
            'last_error' => null,
            'payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionInvoiceV2::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionInvoiceV2::STATUS_FAILED,
            'last_error' => 'Card declined',
        ]);
    }
}
