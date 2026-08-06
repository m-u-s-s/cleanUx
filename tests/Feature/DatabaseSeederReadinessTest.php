<?php

namespace Tests\Feature;

use App\Support\Platform\PlatformReadinessReport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_builds_a_coherent_demo_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        /** @var PlatformReadinessReport $readinessReport */
        $readinessReport = app(PlatformReadinessReport::class);

        $report = $readinessReport->build();

        $this->assertGreaterThanOrEqual(1, $report['metrics']['admins_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['employees_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['clients_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['organization_accounts_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['organization_sites_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['service_zones_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['zone_rules_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['rendezvous_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['feedbacks_total']);

        /*
         * LES ESPACES SOCIÉTÉ (2026-08-06).
         *
         * Ce test passait au vert alors que les cinq écrans société n'avaient pas une ligne à
         * afficher : aucun compteur ci-dessus ne les regardait. C'est l'angle mort caractéristique
         * d'une liste de vérifications — elle ne peut rien dire de ce qu'on a oublié d'y inscrire.
         *
         * Ajouter la table ici est donc le geste à faire en construisant un écran sur une table
         * neuve : c'est ce qui empêche de livrer une interface qu'on n'a jamais vue autrement
         * que vide.
         */
        $this->assertGreaterThanOrEqual(1, $report['metrics']['field_teams_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['tasks_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['channels_total']);
        $this->assertGreaterThanOrEqual(1, $report['metrics']['missions_total']);

        $this->assertTrue($report['summary']['seed_ready'], 'Le report readiness signale encore des erreurs bloquantes.');
    }
}
