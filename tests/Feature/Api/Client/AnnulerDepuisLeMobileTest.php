<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** ANNULER DEPUIS L'APPLICATION MOBILE TOUCHE À L'ARGENT — ce n'était pas le cas. */
class AnnulerDepuisLeMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_annuler_calcule_et_enregistre_les_frais(): void
    {
        $scenario = $this->reservationAnnulable();

        Sanctum::actingAs($scenario->client);

        $reponse = $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel", [
            'reason' => 'Empêchement',
        ]);

        $reponse->assertOk()->assertJsonPath('ok', true);

        $metadata = (array) $scenario->booking->refresh()->metadata;

        $this->assertArrayHasKey(
            'cancellation_fee',
            $metadata,
            'Sans cette clé, le service de calcul n’a jamais été appelé — c’est le défaut réparé.',
        );
        $this->assertArrayHasKey('cancellation_fee_percent', $metadata);
        $this->assertArrayHasKey('cancellation_reason_code', $metadata);
        $this->assertSame('annule', $scenario->booking->refresh()->status);
    }

    /** LES FRAIS SONT DITS DANS LA RÉPONSE. Le client vient d'être débité, ou de l'être en partie. */
    public function test_les_frais_sont_annonces_au_client(): void
    {
        $scenario = $this->reservationAnnulable();

        Sanctum::actingAs($scenario->client);

        $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel")
            ->assertOk()
            ->assertJsonStructure([
                'cancellation' => ['fee_amount', 'fee_percent', 'reason_code', 'is_free'],
            ]);
    }

    /** La raison donnée par le client est conservée : elle sert au litige comme au produit. */
    public function test_la_raison_est_conservee(): void
    {
        $scenario = $this->reservationAnnulable();

        Sanctum::actingAs($scenario->client);

        $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel", [
            'reason' => 'Voyage annulé',
        ])->assertOk();

        $this->assertSame('Voyage annulé', $scenario->booking->refresh()->cancellation_reason);
    }

    // ── Les gardes, qui ne doivent pas avoir bougé ───────────────────────

    public function test_une_reservation_deja_annulee_est_refusee(): void
    {
        $scenario = $this->reservationAnnulable();
        Booking::query()->whereKey($scenario->booking->getKey())->update(['status' => 'annule']);

        Sanctum::actingAs($scenario->client);

        $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel")
            ->assertStatus(422);
    }

    /** `sur_place` reste non annulable depuis un téléphone, et c'est plus strict que le service. */
    public function test_une_intervention_en_cours_nest_pas_annulable(): void
    {
        $scenario = $this->reservationAnnulable();
        Booking::query()->whereKey($scenario->booking->getKey())->update(['status' => 'sur_place']);

        Sanctum::actingAs($scenario->client);

        // 409 et non 422 : c'est le code que `BookingException::notCancellable()` porte depuis
        // toujours, et l'application mobile le distingue de « deja annulee ».
        $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel")
            ->assertStatus(409);

        $this->assertSame('sur_place', $scenario->booking->refresh()->status);
    }

    /** LA GARDE D'ACCÈS — on n'annule pas la réservation d'un autre. */
    public function test_un_tiers_ne_peut_pas_annuler(): void
    {
        $scenario = $this->reservationAnnulable();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/client/bookings/{$scenario->booking->id}/cancel")
            ->assertForbidden();

        $this->assertNotSame('annule', $scenario->booking->refresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function reservationAnnulable(): SpineScenario
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'status' => 'confirme',
            'estimated_price' => 120,
            'payment_status' => 'pending',
        ]);

        $scenario->booking->refresh();

        return $scenario;
    }
}
