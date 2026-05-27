<?php

namespace Database\Factories;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignStepFactory extends Factory
{
    protected $model = MarketingCampaignStep::class;

    public function definition(): array
    {
        return [
            'campaign_id' => fn () => MarketingCampaign::factory()->create()->id,
            'position' => fake()->numberBetween(1, 10),
            'delay_minutes' => fake()->randomElement([0, 60, 1440, 10080]),
            'channel' => fake()->randomElement([
                MarketingCampaignStep::CHANNEL_EMAIL,
                MarketingCampaignStep::CHANNEL_SMS,
                MarketingCampaignStep::CHANNEL_PUSH,
            ]),
            'subject' => fake()->optional()->sentence(6),
            'template_code' => 'tpl_' . fake()->slug(2),
            'variant_label' => 'control',
            'is_active' => true,
            'content_overrides' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
