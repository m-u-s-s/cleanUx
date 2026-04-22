<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'code' => 'NETTOYAGE_STANDARD',
                'name' => 'Nettoyage standard',
                'service_type' => 'nettoyage_standard',
                'description' => 'Prestation régulière pour particuliers et petits bureaux.',
                'default_duration_minutes' => 120,
                'base_price' => 79,
                'sort_order' => 10,
            ],
            [
                'code' => 'NETTOYAGE_PROFOND',
                'name' => 'Nettoyage profond',
                'service_type' => 'nettoyage_profond',
                'description' => 'Nettoyage approfondi ponctuel ou reprise complète.',
                'default_duration_minutes' => 180,
                'base_price' => 129,
                'sort_order' => 20,
            ],
            [
                'code' => 'FIN_DE_CHANTIER',
                'name' => 'Fin de chantier',
                'service_type' => 'fin_de_chantier',
                'description' => 'Remise en état après travaux.',
                'default_duration_minutes' => 240,
                'base_price' => 189,
                'requires_quote' => true,
                'requires_manual_validation' => true,
                'is_entreprise' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'FIN_DE_BAIL',
                'name' => 'Fin de bail',
                'service_type' => 'fin_de_bail',
                'description' => 'Prestation de sortie avec contrôle renforcé.',
                'default_duration_minutes' => 240,
                'base_price' => 179,
                'sort_order' => 40,
            ],
            [
                'code' => 'BUREAUX',
                'name' => 'Nettoyage bureaux',
                'service_type' => 'bureaux',
                'description' => 'Nettoyage récurrent de bureaux et espaces corporate.',
                'default_duration_minutes' => 150,
                'base_price' => 149,
                'is_entreprise' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'VITRES',
                'name' => 'Vitres & vitrines',
                'service_type' => 'vitres',
                'description' => 'Nettoyage des vitres, vitrines et surfaces vitrées.',
                'default_duration_minutes' => 90,
                'base_price' => 69,
                'sort_order' => 60,
            ],
        ];

        foreach ($services as $service) {
            ServiceCatalog::updateOrCreate(
                ['code' => $service['code']],
                [
                    'name' => $service['name'],
                    'slug' => Str::slug($service['name']),
                    'description' => $service['description'],
                    'service_type' => $service['service_type'],
                    'is_active' => true,
                    'requires_quote' => $service['requires_quote'] ?? false,
                    'requires_manual_validation' => $service['requires_manual_validation'] ?? false,
                    'is_entreprise' => $service['is_entreprise'] ?? false,
                    'default_duration_minutes' => $service['default_duration_minutes'],
                    'base_price' => $service['base_price'],
                    'sort_order' => $service['sort_order'],
                ]
            );
        }

        $this->command?->info('✅ Catalogue de services initialisé.');
    }
}
