<?php

namespace Database\Seeders;

use App\Models\Trade;
use Illuminate\Database\Seeder;

/**
 * Phase 1 — Seed des corps de métier (Trades) de référence.
 *
 * Idempotent : utilise updateOrCreate par slug.
 * À ajouter dans DatabaseSeeder.php (ou ton profil de seed) :
 *   $this->call(TradeSeeder::class);
 *   $this->call(ServiceCatalogTradeBackfillSeeder::class);
 */
class TradeSeeder extends Seeder
{
    public function run(): void
    {
        $trades = [
            [
                'slug'                    => 'nettoyage',
                'code'                    => 'CLEANING',
                'name'                    => 'Nettoyage',
                'icon'                    => 'broom',
                'color'                   => '#0EA5E9',
                'short_description'       => 'Nettoyage récurrent ou ponctuel pour particuliers et entreprises.',
                'description'             => 'Tous types de prestations de nettoyage : domestique, bureaux, vitrerie, fin de chantier, désinfection.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 10,
            ],
            [
                'slug'                    => 'batiment',
                'code'                    => 'BUILDING',
                'name'                    => 'Bâtiment',
                'icon'                    => 'hammer',
                'color'                   => '#F59E0B',
                'short_description'       => 'Travaux de construction, rénovation et maçonnerie.',
                'description'             => 'Gros œuvre, second œuvre, isolation, plâtrerie, carrelage, étanchéité.',
                'is_active'               => true,
                'requires_certification'  => true,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 20,
            ],
            [
                'slug'                    => 'peinture',
                'code'                    => 'PAINTING',
                'name'                    => 'Peinture',
                'icon'                    => 'paint-brush',
                'color'                   => '#A855F7',
                'short_description'       => 'Peinture intérieure, extérieure et décoration murale.',
                'description'             => 'Peinture, enduits, revêtements muraux, papiers peints, ravalement de façade.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 30,
            ],
            [
                'slug'                    => 'levage',
                'code'                    => 'LIFTING',
                'name'                    => 'Levage / Manutention',
                'icon'                    => 'forklift',
                'color'                   => '#EF4444',
                'short_description'       => 'Engins de levage, nacelles, monte-charges, déménagement lourd.',
                'description'             => 'Location avec opérateur certifié, manutention industrielle, chantiers en hauteur.',
                'is_active'               => true,
                'requires_certification'  => true,    // CACES R486 etc.
                'requires_insurance_proof'=> true,
                'is_b2b_default'          => true,
                'is_personal_default'     => false,
                'sort_order'              => 40,
            ],
            [
                'slug'                    => 'jardinage',
                'code'                    => 'GARDENING',
                'name'                    => 'Jardinage',
                'icon'                    => 'leaf',
                'color'                   => '#22C55E',
                'short_description'       => 'Entretien, aménagement, élagage et création paysagère.',
                'description'             => 'Tonte, taille, élagage, désherbage, plantation, création de jardins.',
                'is_active'               => true,
                'requires_certification'  => false,
                'requires_insurance_proof'=> false,
                'is_b2b_default'          => true,
                'is_personal_default'     => true,
                'sort_order'              => 50,
            ],
        ];

        foreach ($trades as $payload) {
            Trade::updateOrCreate(
                ['slug' => $payload['slug']],
                $payload
            );
        }
    }
}
