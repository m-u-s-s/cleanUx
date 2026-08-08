<?php

namespace Database\Factories;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sector> */
class SectorFactory extends Factory
{
    protected $model = Sector::class;

    public function definition(): array
    {
        $nom = fake()->unique()->words(2, true);

        return [
            'slug' => str($nom)->slug()->toString().'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => ucfirst($nom),
            'tagline' => fake()->sentence(4),
            'sort_order' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'published_at' => now(),
        ];
    }

    /** Un secteur encore invisible du public. */
    public function brouillon(): static
    {
        return $this->state(fn () => ['is_active' => false, 'published_at' => null]);
    }
}
