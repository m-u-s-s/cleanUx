<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use Illuminate\Database\Seeder;

/**
 * Phase 1 — Services de démo pour les corps de métier autres que Nettoyage.
 *
 * Le seeder historique `ServiceCatalogSeeder` ne crée que des services de
 * nettoyage. Sans ce seeder, la liste de services dans /admin/services
 * et dans la réservation client paraît vide pour les métiers Peinture,
 * Bâtiment, Levage, Jardinage — donc le multi-trade reste invisible côté
 * UX même quand TradeSeeder a tourné.
 *
 * Idempotent : updateOrCreate sur (slug). Re-runnable sans danger.
 *
 * À ajouter dans la chaîne de seeding (déjà fait dans ReferencePlatformSeeder
 * en mai 2026 si tu prends le patch correspondant).
 */
class MultiTradeDemoServicesSeeder extends Seeder
{
    public function run(): void
    {
        // Mapping nécessaire : on a besoin des trade_id résolus depuis le slug
        $trades = Trade::whereIn('slug', ['peinture', 'batiment', 'levage', 'jardinage'])
            ->get()
            ->keyBy('slug');

        if ($trades->isEmpty()) {
            $this->command?->warn(
                "MultiTradeDemoServicesSeeder: aucun trade trouvé. "
                . "Lance d'abord TradeSeeder."
            );
            return;
        }

        $services = [
            // ────── PEINTURE ──────
            [
                'trade_slug'  => 'peinture',
                'code'        => 'PAINT_INDOOR',
                'slug'        => 'peinture-interieure',
                'name'        => 'Peinture intérieure',
                'description' => 'Peinture pièces, plafonds, boiseries. Matériel et préparation des surfaces inclus.',
                'service_type'=> 'standard',
                'base_price'  => 350,
                'duration'    => 240,
                'sort_order'  => 10,
            ],
            [
                'trade_slug'  => 'peinture',
                'code'        => 'PAINT_FACADE',
                'slug'        => 'peinture-facade',
                'name'        => 'Peinture façade',
                'description' => 'Ravalement de façade, peinture extérieure, traitement anti-mousse.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 480,
                'sort_order'  => 20,
                'requires_quote' => true,
                'requires_site_visit' => true,
            ],
            [
                'trade_slug'  => 'peinture',
                'code'        => 'PAINT_TOUCHUP',
                'slug'        => 'peinture-retouches',
                'name'        => 'Retouches & raccords',
                'description' => 'Petites zones, raccords après dégât des eaux, harmonisation de teinte.',
                'service_type'=> 'standard',
                'base_price'  => 120,
                'duration'    => 90,
                'sort_order'  => 30,
            ],

            // ────── BÂTIMENT ──────
            [
                'trade_slug'  => 'batiment',
                'code'        => 'BUILD_RENOV',
                'slug'        => 'renovation-interieure',
                'name'        => 'Rénovation intérieure',
                'description' => 'Travaux de second œuvre : cloisons, sols, plafonds, plomberie, électricité.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 480,
                'sort_order'  => 10,
                'requires_quote' => true,
                'requires_site_visit' => true,
            ],
            [
                'trade_slug'  => 'batiment',
                'code'        => 'BUILD_REPAIR',
                'slug'        => 'petits-travaux',
                'name'        => 'Petits travaux & dépannage',
                'description' => 'Réparation portes, fixations, joints, hublots, petites maçonneries.',
                'service_type'=> 'standard',
                'base_price'  => 150,
                'duration'    => 120,
                'sort_order'  => 20,
            ],
            [
                'trade_slug'  => 'batiment',
                'code'        => 'BUILD_TILING',
                'slug'        => 'carrelage',
                'name'        => 'Carrelage & faïence',
                'description' => 'Pose de carrelage sol et mur, salle de bain, cuisine, terrasse.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 480,
                'sort_order'  => 30,
                'requires_quote' => true,
            ],

            // ────── LEVAGE ──────
            [
                'trade_slug'  => 'levage',
                'code'        => 'LIFT_NACELLE',
                'slug'        => 'nacelle-elevatrice',
                'name'        => 'Nacelle élévatrice',
                'description' => 'Location avec opérateur CACES R486, intervention en hauteur jusqu\'à 18m.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 240,
                'sort_order'  => 10,
                'requires_quote' => true,
                'is_entreprise' => true,
            ],
            [
                'trade_slug'  => 'levage',
                'code'        => 'LIFT_HEAVY',
                'slug'        => 'manutention-lourde',
                'name'        => 'Manutention lourde',
                'description' => 'Déménagement industriel, équipements lourds, machines outils.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 240,
                'sort_order'  => 20,
                'requires_quote' => true,
                'is_entreprise' => true,
            ],

            // ────── JARDINAGE ──────
            [
                'trade_slug'  => 'jardinage',
                'code'        => 'GARDEN_MOW',
                'slug'        => 'tonte-pelouse',
                'name'        => 'Tonte de pelouse',
                'description' => 'Tonte régulière, ramassage et évacuation des déchets verts.',
                'service_type'=> 'standard',
                'base_price'  => 80,
                'duration'    => 90,
                'sort_order'  => 10,
            ],
            [
                'trade_slug'  => 'jardinage',
                'code'        => 'GARDEN_PRUNE',
                'slug'        => 'taille-haies',
                'name'        => 'Taille de haies & arbustes',
                'description' => 'Taille de formation et d\'entretien, élagage léger, évacuation.',
                'service_type'=> 'standard',
                'base_price'  => 120,
                'duration'    => 120,
                'sort_order'  => 20,
            ],
            [
                'trade_slug'  => 'jardinage',
                'code'        => 'GARDEN_DESIGN',
                'slug'        => 'amenagement-paysager',
                'name'        => 'Aménagement paysager',
                'description' => 'Création de massifs, plantation, terrassement, allées.',
                'service_type'=> 'premium',
                'base_price'  => 0,
                'duration'    => 480,
                'sort_order'  => 30,
                'requires_quote' => true,
                'requires_site_visit' => true,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($services as $payload) {
            $trade = $trades->get($payload['trade_slug']);
            if (! $trade) continue;

            $service = ServiceCatalog::updateOrCreate(
                ['slug' => $payload['slug']],
                [
                    'code'                       => $payload['code'],
                    'name'                       => $payload['name'],
                    'description'                => $payload['description'],
                    'service_type'               => $payload['service_type'],
                    'is_active'                  => true,
                    'requires_quote'             => $payload['requires_quote'] ?? false,
                    'requires_site_visit'        => $payload['requires_site_visit'] ?? false,
                    'is_entreprise'              => $payload['is_entreprise'] ?? false,
                    'is_b2b_available'           => true,
                    'is_personal_available'      => ! ($payload['is_entreprise'] ?? false),
                    'default_duration_minutes'   => $payload['duration'],
                    'base_price'                 => $payload['base_price'],
                    'currency'                   => 'EUR',
                    'sort_order'                 => $payload['sort_order'],
                    'trade_id'                   => $trade->id,
                ]
            );

            $service->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->command?->info(
            "MultiTradeDemoServicesSeeder: {$created} service(s) créé(s), {$updated} mis à jour."
        );
    }
}
