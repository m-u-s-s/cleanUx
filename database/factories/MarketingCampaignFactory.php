<?php

namespace Database\Factories;

use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = PromoCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Campaign',
            'description' => fake()->sentence(),
            /*
             * `completed` N'EST PAS UNE VALEUR AUTORISÉE.
             *
             * La colonne est un `enum('draft', 'scheduled', 'active', 'paused', 'archived')`. La
             * factory tirait `completed`, absent de cette liste : l'échec n'était donc
             * qu'intermittent, une fois sur quatre au gré du tirage — le pire régime pour être
             * remarqué.
             */
            'status' => fake()->randomElement(['draft', 'scheduled', 'active', 'paused', 'archived']),
            /*
             * DEUX COLONNES INVENTÉES, RETIRÉES (2026-08-06).
             *
             * Cette factory renseignait `type` et `audience_segment_id`, absentes de
             * `promo_campaigns` — la table porte `slug`, `target_audience` et `budget_cap`. Toute
             * utilisation levait « table promo_campaigns has no column named type », et par
             * ricochet les factories de `MarketingCampaignRecipient` et `MarketingCampaignStep`,
             * qui créent une campagne parente.
             *
             * SQLite n'en signalait qu'une : l'insertion échoue au PREMIER nom inconnu, ce qui
             * masquait la seconde. `MarketingCampaign::$fillable` liste d'ailleurs `code` et
             * `type`, deux colonnes qui n'existent pas non plus — trace d'un schéma envisagé puis
             * abandonné, dont rien ne lit les valeurs.
             */
            'slug' => fake()->unique()->slug(3),
            'created_by_user_id' => User::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addWeeks(2),
            'metadata' => [],
        ];
    }
}
