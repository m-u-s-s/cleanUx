<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use App\Services\Safety\MaskedCallService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES APPELS MASQUÉS (F8) — se joindre sans se donner son numéro.
 *
 * `MaskedCallService` était écrit en entier — configuration, pilote de test, ouverture, fermeture,
 * balayage des expirées — et AUCUNE route n'y menait, aucun appelant ne l'invoquait. Les deux
 * parties n'avaient donc que deux options, toutes deux mauvaises : s'échanger leurs vrais numéros,
 * ou ne pas se parler.
 *
 * Le premier cas laisse au prestataire le téléphone personnel d'une cliente chez qui il est allé
 * une fois — définitivement, hors de tout contrôle. Le second fait sonner à la porte d'un immeuble
 * dont on ne trouve pas l'entrée, sans autre recours que d'annuler l'intervention.
 *
 * CE QUE CE FICHIER PROTÈGE AVANT TOUT : le vrai numéro ne sort JAMAIS de la réponse. C'est la
 * raison d'être du module ; une réponse qui laisserait filtrer le numéro réel rendrait tout le
 * reste inutile.
 */
class AppelsMasquesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Mission, 3: Booking} */
    private function scenario(): array
    {
        $client = User::factory()->create(['phone' => '+32470111222']);
        $prestataire = User::factory()->employe()->create([
            'phone' => '+32470333444',
            'is_active' => true,
            'status' => 'active',
        ]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
        ]);

        return [$client, $prestataire, $mission->fresh(), $booking->fresh()];
    }

    #[Test]
    public function le_prestataire_obtient_une_ligne_vers_le_client(): void
    {
        [$client, $prestataire, $mission, $booking] = $this->scenario();

        app(MaskedCallService::class)->openSession($client, $prestataire, $booking);

        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/masked-call")
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    #[Test]
    public function le_vrai_numero_ne_sort_jamais(): void
    {
        [$client, $prestataire, $mission, $booking] = $this->scenario();

        app(MaskedCallService::class)->openSession($client, $prestataire, $booking);

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/masked-call")
            ->assertOk();

        /*
         * C'EST L'ASSERTION QUI PORTE TOUT LE MODULE. Une réponse qui laisse filtrer le numéro réel
         * rend le masquage décoratif : le prestataire l'enregistre, et la cliente reçoit des appels
         * six mois plus tard sans recours.
         */
        $this->assertStringNotContainsString('+32470111222', $reponse->getContent());
        $this->assertNotNull($reponse->json('data.masked_peer_number'));
        $this->assertStringContainsString('*', (string) $reponse->json('data.masked_peer_number'));
    }

    #[Test]
    public function le_client_obtient_une_ligne_vers_le_prestataire(): void
    {
        [$client, $prestataire, , $booking] = $this->scenario();

        app(MaskedCallService::class)->openSession($client, $prestataire, $booking);

        $reponse = $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/masked-call")
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->assertStringNotContainsString('+32470333444', $reponse->getContent());
    }

    #[Test]
    public function sans_session_ouverte_on_le_dit_plutot_que_de_rester_muet(): void
    {
        [, $prestataire, $mission] = $this->scenario();

        // Un champ vide se lirait comme une panne. La raison permet à l'écran d'afficher autre
        // chose qu'un bouton mort.
        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/masked-call")
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonStructure(['data' => ['message']]);
    }

    #[Test]
    public function un_prestataire_etranger_a_la_mission_n_obtient_rien(): void
    {
        [$client, $prestataire, $mission, $booking] = $this->scenario();
        app(MaskedCallService::class)->openSession($client, $prestataire, $booking);

        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        // L'identifiant de mission est un entier : sans ce contrôle, en essayer un autre donnerait
        // une ligne directe vers une cliente inconnue.
        $this->actingAs($intrus, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/masked-call")
            ->assertForbidden();
    }

    #[Test]
    public function la_consultation_ne_cree_pas_de_ligne(): void
    {
        [, $prestataire, $mission] = $this->scenario();

        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/masked-call")
            ->assertOk();

        // Ouvrir une ligne consomme un numéro loué. La créer à la consultation la ferait dépendre
        // du fait que quelqu'un ait ouvert le bon écran, et en réserverait pour des missions que
        // personne n'appellera.
        $this->assertSame(0, MaskedCallSession::query()->count());
    }

    #[Test]
    public function le_balayage_ferme_les_lignes_expirees(): void
    {
        [$client, $prestataire, , $booking] = $this->scenario();

        $session = app(MaskedCallService::class)->openSession($client, $prestataire, $booking);
        $session->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('masked-calls:scan-expired')->assertSuccessful();

        /*
         * `scanExpired()` existait sans qu'aucune commande ne l'appelle. Une ligne qui reste ouverte
         * se paie tous les mois — et surtout, elle permet de rappeler une cliente des semaines après
         * l'intervention, ce que le masquage était censé empêcher.
         */
        $this->assertNotSame(MaskedCallSession::STATUS_ACTIVE, $session->fresh()->status);
    }
}
