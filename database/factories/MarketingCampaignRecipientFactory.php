<?php

namespace Database\Factories;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MarketingCampaignRecipientFactory extends Factory
{
    protected $model = MarketingCampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id' => fn () => MarketingCampaign::factory()->create()->id,
            'step_id' => null,
            'user_id' => fn () => User::factory()->create()->id,
            'channel' => fake()->randomElement(['email', 'sms', 'push']),
            'status' => MarketingCampaignRecipient::STATUS_QUEUED,
            'idempotency_key' => (string) Str::uuid(),
            'scheduled_for' => now()->addMinutes(30),
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'failed_at' => null,
            'failed_reason' => null,
            'variant_label' => 'control',
            'metadata' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => MarketingCampaignRecipient::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }
}
