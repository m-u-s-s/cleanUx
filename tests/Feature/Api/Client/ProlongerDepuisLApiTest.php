<?php

namespace Tests\Feature\Api\Client;

use App\Models\Mission;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** PROLONGER DEPUIS L'APPLICATION — et surtout, ne pas prolonger celle de quelqu'un d'autre. */
class ProlongerDepuisLApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_client_prolonge_sa_reservation(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 100);

        Sanctum::actingAs($scenario->client);

        $reponse = $this->postJson(
            "/api/client/bookings/{$scenario->booking->id}/onsite/extend",
            ['additional_minutes' => 60],
        );

        $reponse->assertOk()
            ->assertJsonPath('clock.purchased_minutes', 240)
            ->assertJsonPath('clock.remaining_minutes', 140);

        $this->assertSame(240, (int) $scenario->booking->refresh()->purchased_minutes);
    }

    /** LA GARDE — un tiers ne prolonge pas la réservation d'autrui. */
    public function test_un_tiers_ne_peut_pas_prolonger(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 100);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(
            "/api/client/bookings/{$scenario->booking->id}/onsite/extend",
            ['additional_minutes' => 60],
        )->assertForbidden();

        $this->assertSame(180, (int) $scenario->booking->refresh()->purchased_minutes);
    }

    public function test_sans_authentification_rien_ne_passe(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 100);

        $this->postJson(
            "/api/client/bookings/{$scenario->booking->id}/onsite/extend",
            ['additional_minutes' => 60],
        )->assertUnauthorized();
    }

    /** LE REFUS EST UNE RÉPONSE, PAS UNE PANNE. */
    public function test_apres_la_franchise_lapi_explique_le_refus(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 220);

        Sanctum::actingAs($scenario->client);

        $this->postJson(
            "/api/client/bookings/{$scenario->booking->id}/onsite/extend",
            ['additional_minutes' => 60],
        )
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'facturation'));

        $this->assertSame(180, (int) $scenario->booking->refresh()->purchased_minutes);
    }

    /** Une valeur négative tenterait de RÉDUIRE le temps acheté : personne n'a autorisé cela. */
    public function test_une_valeur_negative_est_refusee(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 100);

        Sanctum::actingAs($scenario->client);

        $this->postJson(
            "/api/client/bookings/{$scenario->booking->id}/onsite/extend",
            ['additional_minutes' => -60],
        )->assertStatus(422);

        $this->assertSame(180, (int) $scenario->booking->refresh()->purchased_minutes);
    }

    /** Le fil dit à l'écran s'il peut montrer le bouton, avant que quiconque appuie dessus. */
    public function test_le_fil_annonce_letat_de_la_prolongation(): void
    {
        $scenario = $this->scenarioAuTemps(ecouleesMinutes: 100);

        Sanctum::actingAs($scenario->client);

        $this->getJson("/api/client/bookings/{$scenario->booking->id}/onsite/timeline")
            ->assertOk()
            ->assertJsonPath('extension.allowed', true)
            ->assertJsonPath('extension.increment_minutes', 30)
            ->assertJsonPath('clock.applies', true);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function scenarioAuTemps(int $ecouleesMinutes): SpineScenario
    {
        $scenario = SpineScenario::make()->build();

        $scenario->booking->resolveTrade()?->forceFill([
            'hourly_billing' => true,
            'default_hourly_rate' => 45,
        ])->save();

        $scenario->booking->forceFill([
            'purchased_minutes' => 180,
            'estimated_duration_minutes' => 180,
            'devis_estime' => 175.50,
            'payment_amount_cents' => 17550,
        ])->save();

        Mission::query()->whereKey($scenario->mission->getKey())->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subMinutes($ecouleesMinutes),
            'actual_end_at' => null,
        ]);

        return $scenario;
    }
}
