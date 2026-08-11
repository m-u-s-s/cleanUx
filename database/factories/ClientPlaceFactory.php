<?php

namespace Database\Factories;

use App\Models\ClientPlace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPlace>
 */
class ClientPlaceFactory extends Factory
{
    protected $model = ClientPlace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Chez moi',
            'address' => 'Rue Haute 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'country' => 'BE',
            'is_default' => false,
        ];
    }

    /** Le lieu qui pré-remplit le parcours de commande. */
    public function parDefaut(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /** Avec ses consignes — ce qui distingue un carnet de lieux d'un carnet d'adresses. */
    public function avecConsignes(): static
    {
        return $this->state(fn () => [
            'floor' => '3e étage, porte gauche',
            'access_instructions' => 'Digicode 4512B. La clé est chez la voisine du 2e si absente.',
            'alarm_code_required' => true,
            'preferences' => [
                'products' => 'Produits sans chlore uniquement.',
                'allergies' => 'Allergie aux parfums d’agrumes.',
                'pets' => 'Un chat, qui se cache.',
            ],
        ]);
    }
}
