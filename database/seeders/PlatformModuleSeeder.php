<?php

namespace Database\Seeders;

use App\Models\PlatformModule;
use Illuminate\Database\Seeder;

class PlatformModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'core.booking', 'name' => 'Réservations', 'category' => 'core', 'description' => 'Tunnel principal de réservation.', 'sort_order' => 10],
            ['key' => 'zones.management', 'name' => 'Gestion des zones', 'category' => 'core', 'description' => 'Pilotage Belgique par zones activables.', 'sort_order' => 20],
            ['key' => 'pricing.dynamic', 'name' => 'Tarification dynamique', 'category' => 'pricing', 'description' => 'Prix et règles par zone et service.', 'sort_order' => 30],
            ['key' => 'clients.premium', 'name' => 'Clients premium', 'category' => 'clients', 'description' => 'Fonctionnalités premium et favoris.', 'sort_order' => 40],
            ['key' => 'clients.entreprise', 'name' => 'Comptes entreprise', 'category' => 'clients', 'description' => 'Multi-sites et gestion corporate.', 'sort_order' => 50, 'rollout_strategy' => 'organization'],
            ['key' => 'workforce.management', 'name' => 'Workforce management', 'category' => 'ops', 'description' => 'Gestion équipes, zones et capacités.', 'sort_order' => 60],
            ['key' => 'calendar.sync', 'name' => 'Synchronisation agenda', 'category' => 'integrations', 'description' => 'Connexion agenda interne / Google.', 'sort_order' => 70, 'is_enabled' => false],
            ['key' => 'notifications.center', 'name' => 'Centre de notifications', 'category' => 'communication', 'description' => 'Emails, alertes et historique.', 'sort_order' => 80],
            ['key' => 'analytics.advanced', 'name' => 'Analytics avancés', 'category' => 'analytics', 'description' => 'KPIs par zone, service et équipe.', 'sort_order' => 90, 'is_enabled' => false],
            ['key' => 'support.incidents', 'name' => 'Incidents & support', 'category' => 'ops', 'description' => 'Gestion tickets, incidents et litiges.', 'sort_order' => 100, 'is_enabled' => false],
        ];

        foreach ($modules as $module) {
            PlatformModule::updateOrCreate(
                ['key' => $module['key']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'category' => $module['category'],
                    'rollout_strategy' => $module['rollout_strategy'] ?? 'global',
                    'is_enabled' => $module['is_enabled'] ?? true,
                    'is_locked' => $module['is_locked'] ?? false,
                    'sort_order' => $module['sort_order'],
                ]
            );
        }

        $this->command?->info('✅ Modules de plateforme initialisés.');
    }
}
