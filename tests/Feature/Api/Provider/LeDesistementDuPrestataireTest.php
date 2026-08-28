<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `provider/missions/{mission}/cancel` n'est PAS une annulation : le prestataire se desiste et
 * la mission repart au dispatch. Elle n'a donc pas ete fondue dans le moteur d'annulation.
 */
class LeDesistementDuPrestataireTest extends TestCase
{
    use RefreshDatabase;

    /** Le portail prestataire exige un compte employe, approuve et verifie. */
    private function prestataireApprouve(): User
    {
        $utilisateur = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $utilisateur->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $utilisateur->fresh();
    }

    /** @return array{prestataire: User, mission: Mission, reservation: Booking} */
    private function mission(): array
    {
        $prestataire = $this->prestataireApprouve();
        $quand = now()->addDays(2);

        $reservation = Booking::create([
            'client_id' => User::factory()->client()->create()->id,
            'date' => $quand,
            'heure' => $quand->format('H:i'),
            'scheduled_at' => $quand,
            'status' => 'confirme',
            'estimated_price' => 100.0,
        ]);

        $mission = Mission::factory()->create([
            'booking_id' => $reservation->id,
            'lead_provider_user_id' => $prestataire->id,
        ]);

        return ['prestataire' => $prestataire, 'mission' => $mission, 'reservation' => $reservation];
    }

    /** La reservation RETOURNE au dispatch : elle n'est pas annulee. */
    public function test_le_desistement_remet_la_reservation_en_attente(): void
    {
        ['prestataire' => $prestataire, 'mission' => $mission, 'reservation' => $reservation] = $this->mission();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/cancel", ['reason' => 'Vehicule en panne'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'booking_id', 'penalty']);

        $this->assertSame('en_attente', $reservation->fresh()->status,
            'Un desistement ne doit pas annuler : la mission doit pouvoir repartir au dispatch.');
    }

    /** Le motif et la penalite sont consignes sur la reservation. */
    public function test_le_motif_et_la_penalite_sont_consignes(): void
    {
        ['prestataire' => $prestataire, 'mission' => $mission, 'reservation' => $reservation] = $this->mission();

        $this->actingAs($prestataire, 'sanctum')->postJson("/api/provider/missions/{$mission->id}/cancel", ['reason' => 'Vehicule en panne'])->assertOk();

        $fraiche = $reservation->fresh();
        $metadata = (array) $fraiche->metadata;

        $this->assertStringContainsString('Vehicule en panne', (string) $fraiche->cancellation_reason);
        $this->assertArrayHasKey('provider_penalty_eur', $metadata);
        $this->assertArrayHasKey('provider_reliability_penalty', $metadata);
    }

    /**
     * LA DECISION, FIGEE ICI : ce chemin ne passe pas par CancellationV2. Le fondre dans le
     * moteur ferait passer la reservation a « annule » et arreterait le redispatch.
     */
    public function test_le_desistement_n_ouvre_pas_de_dossier_d_annulation_v2(): void
    {
        ['prestataire' => $prestataire, 'mission' => $mission, 'reservation' => $reservation] = $this->mission();

        $this->actingAs($prestataire, 'sanctum')->postJson("/api/provider/missions/{$mission->id}/cancel")->assertOk();

        $this->assertDatabaseMissing('booking_cancellations_v2', ['booking_id' => $reservation->id]);
    }

    /** TEMOIN — un prestataire etranger a la mission est refuse. */
    public function test_temoin_un_prestataire_etranger_est_refuse(): void
    {
        ['mission' => $mission, 'reservation' => $reservation] = $this->mission();

        $this->actingAs($this->prestataireApprouve(), 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/cancel")->assertForbidden();

        $this->assertSame('confirme', $reservation->fresh()->status);
    }
}
