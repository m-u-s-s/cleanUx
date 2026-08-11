<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionExtra>
 */
class MissionExtraFactory extends Factory
{
    protected $model = MissionExtra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'label' => 'Nettoyage des vitres',
            'description' => null,
            'price_cents' => 2500,
            'currency' => 'EUR',
            'status' => MissionExtra::STATUS_PROPOSED,
        ];
    }

    public function approuve(): static
    {
        return $this->state(fn () => [
            'status' => MissionExtra::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function refuse(): static
    {
        return $this->state(fn () => [
            'status' => MissionExtra::STATUS_DECLINED,
            'declined_at' => now(),
        ]);
    }
}
