<?php

namespace Database\Factories;

use App\Models\ProviderQuest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProviderQuest>
 */
class ProviderQuestFactory extends Factory
{
    protected $model = ProviderQuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'quest-'.Str::lower(Str::random(8)),
            'title' => '10 missions ce mois-ci',
            'metric' => ProviderQuest::METRIC_MISSIONS,
            'target' => 10,
            'reward_type' => ProviderQuest::REWARD_LOYALTY,
            'reward_value' => 200,
            'is_active' => true,
        ];
    }
}
