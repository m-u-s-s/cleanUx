<?php

namespace Database\Factories;

use App\Models\Parametre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parametre>
 */
class ParametreFactory extends Factory
{
    protected $model = Parametre::class;

    public function definition(): array
    {
        return [
            'cle' => fake()->unique()->slug(3, '_'),
            'valeur' => fake()->sentence(),
        ];
    }
}
