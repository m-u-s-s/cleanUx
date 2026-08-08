<?php

namespace Database\Factories;

use App\Models\CatalogTranslation;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogTranslation>
 *
 * Traduction polymorphe d'un champ de catalogue. `Trade` sert de cible par défaut parce que c'est
 * le cas le plus courant ; toute autre entité traduisible se passe en état.
 */
class CatalogTranslationFactory extends Factory
{
    protected $model = CatalogTranslation::class;

    public function definition(): array
    {
        return [
            'translatable_type' => Trade::class,
            'translatable_id' => Trade::factory(),
            'locale' => fake()->randomElement(['fr', 'nl', 'en', 'de']),
            'field' => fake()->randomElement(['name', 'description', 'tagline']),
            'value' => fake()->sentence(),
        ];
    }
}
