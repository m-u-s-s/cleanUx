<?php

namespace Database\Seeders;

use App\Models\RecurringTemplate;
use Illuminate\Database\Seeder;

/**
 * Phase 6.1 — Seeder pour les templates système.
 *
 * Crée une galerie de ~10 templates "1-clic" couvrant les cas d'usage typiques
 * d'une plateforme de services aux entreprises (nettoyage, maintenance, etc.).
 */
class RecurringTemplateSystemSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── Bureaux ──
            [
                'slug' => 'office-daily-5d',
                'name' => 'Nettoyage bureaux quotidien (5j/sem)',
                'description' => 'Nettoyage chaque jour ouvré (lundi au vendredi). Idéal pour bureaux occupés en permanence.',
                'category' => RecurringTemplate::CATEGORY_OFFICE,
                'icon' => '🏢',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'default_time' => '06:30:00',
                'default_duration_minutes' => 90,
                'display_order' => 10,
            ],
            [
                'slug' => 'office-weekly-friday',
                'name' => 'Nettoyage bureaux hebdomadaire (vendredi)',
                'description' => 'Nettoyage approfondi 1x/semaine en fin de semaine. Convient aux PME et bureaux peu occupés.',
                'category' => RecurringTemplate::CATEGORY_OFFICE,
                'icon' => '🧽',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['friday'],
                'default_time' => '17:00:00',
                'default_duration_minutes' => 180,
                'display_order' => 20,
            ],
            [
                'slug' => 'office-biweekly-monday',
                'name' => 'Nettoyage bureaux bi-mensuel (lundi)',
                'description' => 'Toutes les 2 semaines, le lundi matin. Bonne fréquence pour open-spaces de moins de 200 m².',
                'category' => RecurringTemplate::CATEGORY_OFFICE,
                'icon' => '🗓',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 2,
                'days' => ['monday'],
                'default_time' => '07:00:00',
                'default_duration_minutes' => 150,
                'display_order' => 30,
            ],

            // ── Commerces ──
            [
                'slug' => 'retail-3x-week',
                'name' => 'Nettoyage commerce 3x/semaine',
                'description' => 'Lundi, mercredi, vendredi avant ouverture. Conçu pour boutiques et retail à fort trafic.',
                'category' => RecurringTemplate::CATEGORY_RETAIL,
                'icon' => '🛍',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['monday', 'wednesday', 'friday'],
                'default_time' => '07:30:00',
                'default_duration_minutes' => 60,
                'display_order' => 40,
            ],
            [
                'slug' => 'retail-daily-morning',
                'name' => 'Nettoyage commerce quotidien matin',
                'description' => 'Tous les jours sauf dimanche, juste avant ouverture. Pour boutiques 6j/7.',
                'category' => RecurringTemplate::CATEGORY_RETAIL,
                'icon' => '🌅',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'default_time' => '08:00:00',
                'default_duration_minutes' => 45,
                'display_order' => 50,
            ],

            // ── Hôtellerie / Restaurant ──
            [
                'slug' => 'restaurant-daily-evening',
                'name' => 'Nettoyage restaurant quotidien (après service)',
                'description' => 'Tous les jours après le service du soir. Cuisine + salle.',
                'category' => RecurringTemplate::CATEGORY_HOSPITALITY,
                'icon' => '🍽',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'default_time' => '23:30:00',
                'default_duration_minutes' => 90,
                'display_order' => 60,
            ],
            [
                'slug' => 'hotel-daily-checkout',
                'name' => 'Ménage hôtel quotidien (post check-out)',
                'description' => 'Tous les jours en milieu de matinée pour rotation des chambres.',
                'category' => RecurringTemplate::CATEGORY_HOSPITALITY,
                'icon' => '🏨',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'default_time' => '11:00:00',
                'default_duration_minutes' => 240,
                'display_order' => 70,
            ],

            // ── Résidentiel ──
            [
                'slug' => 'residential-weekly-tuesday',
                'name' => 'Aide ménagère hebdo (mardi)',
                'description' => 'Tous les mardis. Idéal particulier ou grand appartement.',
                'category' => RecurringTemplate::CATEGORY_RESIDENTIAL,
                'icon' => '🏠',
                'frequency' => RecurringTemplate::FREQ_WEEKLY,
                'interval' => 1,
                'days' => ['tuesday'],
                'default_time' => '09:00:00',
                'default_duration_minutes' => 180,
                'display_order' => 80,
            ],
            [
                'slug' => 'residential-monthly',
                'name' => 'Grand ménage mensuel',
                'description' => 'Une fois par mois, en profondeur. Pour résidences principales ou secondaires.',
                'category' => RecurringTemplate::CATEGORY_RESIDENTIAL,
                'icon' => '✨',
                'frequency' => RecurringTemplate::FREQ_MONTHLY,
                'interval' => 1,
                'default_time' => '09:00:00',
                'default_duration_minutes' => 360,
                'display_order' => 90,
            ],

            // ── Autre ──
            [
                'slug' => 'monthly-deep-clean',
                'name' => 'Grand nettoyage trimestriel',
                'description' => 'Tous les 3 mois. Vitres, sols profonds, désinfection complète.',
                'category' => RecurringTemplate::CATEGORY_OTHER,
                'icon' => '🧴',
                'frequency' => RecurringTemplate::FREQ_MONTHLY,
                'interval' => 3,
                'default_time' => '08:00:00',
                'default_duration_minutes' => 480,
                'display_order' => 100,
            ],
        ];

        foreach ($templates as $tpl) {
            RecurringTemplate::updateOrCreate(
                ['slug' => $tpl['slug']],
                array_merge($tpl, [
                    'is_system' => true,
                    'is_active' => true,
                    'usage_count' => 0,
                ])
            );
        }
    }
}
