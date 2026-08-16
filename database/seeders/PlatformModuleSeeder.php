<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformModuleSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

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
            $this->updateOrInsertTable(
                'platform_modules',
                ['key' => $module['key']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'category' => $module['category'],
                    'rollout_strategy' => $module['rollout_strategy'] ?? 'global',
                    'is_enabled' => $module['is_enabled'] ?? true,
                    'is_locked' => $module['is_locked'] ?? false,
                    'sort_order' => $module['sort_order'],
                    'settings' => [
                        'category' => $module['category'],
                        'rollout_strategy' => $module['rollout_strategy'] ?? 'global',
                        'sort_order' => $module['sort_order'],
                    ],
                ]
            );
        }

        $this->seedFaceCheckModule();

        $this->command?->info('✅ Modules de plateforme initialisés.');
    }

    /**
     * LE CONTRÔLE FACIAL — semé UNE SEULE FOIS, jamais réécrit.
     *
     * La boucle ci-dessus fait un `updateOrInsert` et REMPLACE `settings` à chaque passage : pour
     * les modules historiques c'est sans conséquence, ils n'y rangent qu'un écho de leurs propres
     * colonnes. Ici, `settings` porte les réglages métier du module (intervalles, seuils, durée de
     * conservation) et l'audience par zone décidée par un administrateur. Repasser le seeder les
     * effacerait sans rien dire — et un module de sécurité qui se réinitialise en silence est pire
     * que pas de module du tout.
     *
     * Désactivé à la création : un module de contrôle d'identité s'allume quand un humain le décide.
     */
    private function seedFaceCheckModule(): void
    {
        $key = (string) config('face_check.module_key', 'security.face_check');

        if (DB::table('platform_modules')->where('key', $key)->exists()) {
            return;
        }

        $this->updateOrInsertTable(
            'platform_modules',
            ['key' => $key],
            [
                'name' => 'Vérification faciale des prestataires',
                'description' => "Enrôlement du visage à l'inscription, contrôles aléatoires avant "
                    ."d'aller chez un client, appariement avec la pièce d'identité et revue "
                    .'manuelle par un administrateur.',
                'category' => 'ops',
                'rollout_strategy' => 'zone',
                'is_enabled' => false,
                'is_locked' => false,
                'sort_order' => 110,
                /*
                 * LES VALEURS VIENNENT DE LA CONFIG, elles ne sont pas recopiees.
                 *
                 * Les ecrire en dur ici creait deja une divergence : le seeder posait un seuil
                 * d'echec de 3 quand `config/face_check.php` en annoncait 2, et c'est la base qui
                 * gagne. Deux sources plausibles pour un meme reglage, c'est le defaut dominant de
                 * ce depot -- et il ne se voit pas, les deux chiffres etant tous deux credibles.
                 */
                'settings' => [
                    'allowed_zone_ids' => [],
                    'face_check' => [
                        'min_hours' => (int) config('face_check.interval.min_hours'),
                        'max_hours' => (int) config('face_check.interval.max_hours'),
                        'match_threshold' => (float) config('face_check.match_threshold'),
                        'id_match_threshold' => (float) config('face_check.id_match_threshold'),
                        'liveness_required' => (bool) config('face_check.liveness_required'),
                        'max_attempts' => (int) config('face_check.max_attempts'),
                        'failure_threshold' => (int) config('face_check.failure_threshold'),
                        'abandon_threshold' => (int) config('face_check.abandon.threshold'),
                        'abandon_window_days' => (int) config('face_check.abandon.window_days'),
                        'abandon_fraud_threshold' => (int) config('face_check.abandon.fraud_threshold'),
                        'selfie_retention_days' => (int) config('face_check.selfie_retention_days'),
                    ],
                ],
            ]
        );
    }
}
