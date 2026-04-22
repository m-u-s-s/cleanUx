<?php

namespace Tests\Unit;

use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RendezVousModelHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_notification_tracking_if_needed_clears_reminders_and_urgent_alert_when_schedule_changes(): void
    {
        $rdv = RendezVous::factory()->create([
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00:00',
            'status' => 'confirme',
            'priorite' => 'urgente',
            'rappel_24h_envoye_at' => now(),
            'rappel_2h_envoye_at' => now(),
            'alerte_urgence_envoyee_at' => now(),
        ]);

        $original = [
            'date' => $rdv->date->copy()->subDay()->toDateString(),
            'heure' => '09:00:00',
            'status' => 'confirme',
            'priorite' => 'urgente',
        ];

        $rdv->resetNotificationTrackingIfNeeded($original);

        $this->assertNull($rdv->rappel_24h_envoye_at);
        $this->assertNull($rdv->rappel_2h_envoye_at);
        $this->assertNull($rdv->alerte_urgence_envoyee_at);
    }

    public function test_final_status_helper_and_client_editability_are_correct(): void
    {
        $termine = RendezVous::factory()->termine()->create();
        $refuse = RendezVous::factory()->refuse()->create();
        $confirme = RendezVous::factory()->confirme()->create();
        $surPlace = RendezVous::factory()->create(['status' => 'sur_place']);

        $this->assertTrue($termine->isFinalStatus());
        $this->assertTrue($refuse->isFinalStatus());
        $this->assertFalse($confirme->isFinalStatus());

        $this->assertFalse($termine->canStillBeEditedByClient());
        $this->assertFalse($surPlace->canStillBeEditedByClient());
        $this->assertTrue($confirme->canStillBeEditedByClient());
    }


    public function test_search_structured_matches_catalog_name_and_postal_code_relation_without_legacy_fallbacks(): void
    {
        $service = ServiceCatalog::factory()->create([
            'name' => 'Nettoyage Vitrage Premium',
            'code' => 'SVC-VITRES-PREM',
            'service_type' => 'vitres-premium',
        ]);

        $postalCode = PostalCode::factory()->create([
            'code' => '1050',
            'city_name' => 'Ixelles',
        ]);

        $rdv = RendezVous::factory()->create([
            'service_catalog_id' => $service->id,
            'postal_code_id' => $postalCode->id,
            'code_postal' => null,
            'ville' => 'Ixelles',
            'pricing_snapshot' => [
                'service_identifier' => 'SVC-VITRES-PREM',
                'service_name' => 'Nettoyage Vitrage Premium',
                'service' => [
                    'service_identifier' => 'SVC-VITRES-PREM',
                    'code' => 'SVC-VITRES-PREM',
                    'name' => 'Nettoyage Vitrage Premium',
                ],
            ],
        ]);

        $this->assertTrue(RendezVous::query()->searchStructured('Vitrage Premium')->whereKey($rdv->id)->exists());
        $this->assertTrue(RendezVous::query()->searchStructured('1050')->whereKey($rdv->id)->exists());
        $this->assertTrue(RendezVous::query()->whereServiceMatches('vitres')->whereKey($rdv->id)->exists());
    }

    public function test_location_display_and_service_display_use_structured_relations_first(): void
    {
        $service = ServiceCatalog::factory()->create([
            'name' => 'Entretien Bureaux',
            'code' => 'SVC-BUREAUX',
        ]);

        $postalCode = PostalCode::factory()->create([
            'code' => '1000',
            'city_name' => 'Bruxelles',
        ]);

        $rdv = RendezVous::factory()->create([
            'service_catalog_id' => $service->id,
            'postal_code_id' => $postalCode->id,
            'code_postal' => null,
            'adresse' => 'Rue du Test 10',
            'ville' => 'Bruxelles',
            'pricing_snapshot' => [
                'service_identifier' => 'SVC-BUREAUX',
                'service_name' => 'Entretien Bureaux',
                'service' => [
                    'service_identifier' => 'SVC-BUREAUX',
                    'code' => 'SVC-BUREAUX',
                    'name' => 'Entretien Bureaux',
                ],
            ],
        ]);

        $this->assertSame('Entretien Bureaux', $rdv->service_display_name);
        $this->assertSame('1000', $rdv->postal_code_display);
        $this->assertSame('Rue du Test 10, 1000, Bruxelles', $rdv->location_display);
    }
}
