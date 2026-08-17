<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\TripTracking\TripTrackingService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE CHRONOMÈTRE AVEC PAUSES (F4) ET LA FICHE D'ACCÈS (F5).
 *
 * LE CHRONOMÈTRE SAVAIT S'ARRÊTER, PAS COMPTER. `is_paused` et `paused_at` existaient, mais la
 * reprise se contentait de baisser le drapeau : la durée écoulée n'était cumulée nulle part, et
 * `paused_at` restait en place — il continuait de se lire comme « en pause depuis ce matin ».
 * Conséquence : le temps travaillé n'était pas calculable. Sur une intervention de quatre heures
 * dont une de déjeuner, la seule durée disponible en facturait une de trop.
 *
 * LA FICHE D'ACCÈS NE S'OUVRE QU'À L'ARRIVÉE, et c'est le cœur de ce fichier. Un code d'alarme,
 * l'emplacement d'une boîte à clés : ce sont les clés du domicile de quelqu'un. Les rendre lisibles
 * dès l'assignation — parfois plusieurs jours à l'avance, parfois à un prestataire qui annulera —
 * reviendrait à les distribuer à tous ceux qui passent dans la file d'affectation.
 */
class ChronometreEtFicheDAccesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mission, 2: Booking} */
    private function scenario(?OrganizationSite $site = null): array
    {
        $client = User::factory()->create();
        $prestataire = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
            'organization_site_id' => $site?->id,
            'commentaire_client' => 'Sonner deux fois, le chien aboie.',
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::ASSIGNED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
        ]);

        return [$prestataire, $mission->fresh(), $booking->fresh()];
    }

    private function sessionDeSuivi(Booking $booking, User $prestataire): TripTrackingSession
    {
        return TripTrackingSession::query()->create([
            'code' => 'TTS-'.$booking->id,
            'booking_id' => $booking->id,
            'provider_user_id' => $prestataire->id,
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'started_at' => now()->subHours(4),
            'in_mission_at' => now()->subHours(4),
        ]);
    }

    // ── F4 : le chronomètre ──────────────────────────────────────────────────

    #[Test]
    public function une_pause_reprise_est_cumulee(): void
    {
        [$prestataire, , $booking] = $this->scenario();
        $session = $this->sessionDeSuivi($booking, $prestataire);

        $service = app(TripTrackingService::class);

        // Pause posée il y a une heure : la reprise doit en garder trace.
        $session->forceFill(['is_paused' => true, 'paused_at' => now()->subHour()])->save();

        $service->resumeSession($session->fresh());

        $releve = $session->fresh();

        $this->assertGreaterThanOrEqual(3500, $releve->paused_total_seconds);
        // `paused_at` DOIT être effacé : laissé en place, il se lit ensuite comme « en pause depuis
        // ce matin » alors que le prestataire travaille.
        $this->assertNull($releve->paused_at);
        $this->assertFalse($releve->is_paused);
    }

    #[Test]
    public function reprendre_deux_fois_ne_compte_pas_deux_fois(): void
    {
        [$prestataire, , $booking] = $this->scenario();
        $session = $this->sessionDeSuivi($booking, $prestataire);

        $service = app(TripTrackingService::class);
        $session->forceFill(['is_paused' => true, 'paused_at' => now()->subMinutes(30)])->save();

        $service->resumeSession($session->fresh());
        $premier = $session->fresh()->paused_total_seconds;

        // Double appui, ou deux appareils : la seconde reprise ajouterait une durée déjà comptée.
        $service->resumeSession($session->fresh());

        $this->assertSame($premier, $session->fresh()->paused_total_seconds);
    }

    #[Test]
    public function le_temps_travaille_deduit_les_pauses(): void
    {
        [$prestataire, , $booking] = $this->scenario();
        $session = $this->sessionDeSuivi($booking, $prestataire);

        // Quatre heures de présence, une heure de pause.
        $session->forceFill(['paused_total_seconds' => 3600])->save();

        $travaille = $session->fresh()->workedSeconds();

        /*
         * C'EST LA VALEUR QUE CONSOMMERONT LES FEUILLES D'HEURES. Confondre présence et travail
         * fait payer ou facturer une heure de trop sur une intervention avec déjeuner.
         */
        $this->assertGreaterThanOrEqual(10700, $travaille);
        $this->assertLessThanOrEqual(10900, $travaille);
        $this->assertSame(180, $session->fresh()->workedMinutes());
    }

    #[Test]
    public function une_pause_en_cours_a_la_cloture_compte_quand_meme(): void
    {
        [$prestataire, , $booking] = $this->scenario();
        $session = $this->sessionDeSuivi($booking, $prestataire);

        $session->forceFill(['is_paused' => true, 'paused_at' => now()->subMinutes(20)])->save();

        // Le prestataire clôture sans reprendre — parce qu'il a fini, ou parce que l'application
        // s'est fermée. Cette dernière pause disparaîtrait sinon du décompte.
        app(TripTrackingService::class)->endSession($session->fresh());

        $this->assertGreaterThanOrEqual(1150, $session->fresh()->paused_total_seconds);
    }

    #[Test]
    public function une_mission_pas_encore_commencee_ne_compte_rien(): void
    {
        [$prestataire, , $booking] = $this->scenario();

        $session = TripTrackingSession::query()->create([
            'code' => 'TTS-X'.$booking->id,
            'booking_id' => $booking->id,
            'provider_user_id' => $prestataire->id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
            'started_at' => now()->subHour(),
        ]);

        // Le trajet n'est pas du travail sur place : le compte part de l'entrée en mission.
        $this->assertSame(0, $session->workedSeconds());
    }

    // ── F5 : la fiche d'accès ────────────────────────────────────────────────

    #[Test]
    public function la_fiche_reste_fermee_avant_l_arrivee(): void
    {
        [$prestataire, $mission] = $this->scenario();

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/access-sheet")
            ->assertOk();

        /*
         * C'EST L'ASSERTION QUI PORTE F5. Le code d'alarme et l'emplacement d'une boîte à clés sont
         * les clés du domicile de quelqu'un ; les exposer dès l'assignation les distribuerait à tous
         * ceux qui passent dans la file d'affectation, y compris à ceux qui annuleront.
         */
        $this->assertFalse($reponse->json('data.available'));
        $this->assertNull($reponse->json('data.access_instructions'));
        // Le refus est EXPLICITE : une fiche vide se lirait comme une donnée manquante et ferait
        // appeler le support.
        $this->assertNotNull($reponse->json('data.message'));
    }

    #[Test]
    public function la_fiche_s_ouvre_une_fois_l_arrivee_confirmee(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $organisation->id,
            'floor' => '3e étage, porte droite',
            'access_instructions' => 'Digicode 45A12, boîte à clés sous le paillasson.',
            'alarm_code_required' => true,
        ]);

        [$prestataire, $mission] = $this->scenario($site);

        $mission->forceFill(['status' => MissionStatus::ARRIVED])->save();

        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/access-sheet")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.floor', '3e étage, porte droite')
            ->assertJsonPath('data.access_instructions', 'Digicode 45A12, boîte à clés sous le paillasson.')
            // Une alarme demande une manœuvre chronométrée : le prestataire doit le savoir AVANT
            // d'ouvrir la porte, pas en entendant la sirène.
            ->assertJsonPath('data.alarm_code_required', true);
    }

    #[Test]
    public function sans_local_d_entreprise_le_commentaire_du_client_fait_office(): void
    {
        [$prestataire, $mission] = $this->scenario();

        $mission->forceFill(['status' => MissionStatus::STARTED])->save();

        // Le carnet d'adresses des particuliers n'existe pas encore : le commentaire de commande
        // est la seule consigne disponible, et vaut mieux qu'un champ vide.
        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/access-sheet")
            ->assertOk()
            ->assertJsonPath('data.access_instructions', 'Sonner deux fois, le chien aboie.');
    }

    #[Test]
    public function un_prestataire_etranger_n_obtient_rien(): void
    {
        [, $mission] = $this->scenario();
        $mission->forceFill(['status' => MissionStatus::ARRIVED])->save();

        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        // L'identifiant de mission est un entier : sans ce contrôle, en essayer d'autres livrerait
        // les codes d'entrée de domiciles inconnus.
        $this->actingAs($intrus, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/access-sheet")
            ->assertForbidden();
    }
}
