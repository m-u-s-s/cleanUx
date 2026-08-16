<?php

namespace Tests\Feature\FaceCheck;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderFaceProfile;
use App\Models\ProviderPresence;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Dispatch\DispatchCandidate;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\FaceCheck\Exceptions\FaceCheckRequiredException;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Presence\ProviderPresenceService;
use App\Services\Provider\ProviderPresenceService as PresenceLegacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

/**
 * LES SEPT POINTS DE PASSAGE QUI MÈNENT UN PRESTATAIRE CHEZ UN CLIENT.
 *
 * Ils posent tous la même question — « cette personne est-elle bien celle qu'elle prétend être ? »
 * — et si chacun se répondait à lui-même, six d'entre eux finiraient par répondre autrement que le
 * septième. La porte se contournerait alors par celui qu'on aurait oublié.
 *
 * CHAQUE REFUS A SON TÉMOIN. Un test qui vérifie qu'une porte se ferme passe au vert si la porte
 * est murée : il faut prouver, dans le même souffle, qu'elle s'ouvre quand elle le doit.
 */
class PortesDuControleFacialTest extends TestCase
{
    use ActiveLeControleFacial;
    use OuvreLeCatalogue;
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();

        $zone = ServiceZone::create([
            'name' => 'Zone visage', 'slug' => 'zone-visage', 'code' => 'FC-Z',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $metier = Trade::create([
            'slug' => 'babysitting-fc', 'code' => 'BABY-FC', 'name' => 'Babysitting',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->activerLeControleFacial($zone, $metier);
        $this->ouvrirAuCatalogue($metier, $zone);
    }

    // ─── 1. Le filtre SQL du dispatch ────────────────────────────────────────────────────────

    public function test_un_prestataire_bloque_disparait_de_la_liste_des_candidats(): void
    {
        $enRegle = $this->prestataireEnLigne();
        $bloque = $this->prestataireEnLigne();
        $this->bloquer($bloque);

        $candidats = $this->candidats($this->reservation());

        // TÉMOIN : celui qui est en règle est bien là — on ne mesure pas une panne générale.
        $this->assertContains($enRegle->id, $candidats);
        $this->assertNotContains($bloque->id, $candidats);
    }

    public function test_un_prestataire_jamais_enrole_disparait_de_la_liste(): void
    {
        $enRegle = $this->prestataireEnLigne();
        $jamaisEnrole = $this->prestataireEnLigne(enroler: false);

        $candidats = $this->candidats($this->reservation());

        $this->assertContains($enRegle->id, $candidats);
        $this->assertNotContains($jamaisEnrole->id, $candidats);
    }

    /**
     * CELUI DONT LE CONTRÔLE EST SIMPLEMENT DÛ RESTE CANDIDAT — délibérément.
     *
     * L'écarter du dispatch le priverait de missions sans qu'aucun écran ne le lui dise : c'est
     * l'angle mort déjà connu de `verification_status` sur ce dépôt. Il sera arrêté à la porte
     * qu'il traverse vraiment, où on peut le lui expliquer.
     */
    public function test_un_controle_simplement_du_ne_retire_pas_des_candidats(): void
    {
        $du = $this->prestataireEnLigne();
        $this->rendreLeControleDu($du);

        $this->assertContains($du->id, $this->candidats($this->reservation()));
    }

    public function test_un_metier_non_soumis_ne_filtre_personne(): void
    {
        $autreMetier = Trade::create([
            'slug' => 'peinture-fc', 'code' => 'PAINT-FC', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 2,
        ]);
        $this->ouvrirAuCatalogue($autreMetier, $this->zoneDuControle);

        $bloque = $this->prestataireEnLigne(metier: $autreMetier);
        $this->bloquer($bloque);

        // Le métier n'exige pas de contrôle : le blocage facial ne le concerne pas.
        $this->assertContains($bloque->id, $this->candidats($this->reservation($autreMetier)));
    }

    // ─── 2. La fabrique d'offres ─────────────────────────────────────────────────────────────

    public function test_aucune_offre_nest_fabriquee_pour_un_visage_bloque(): void
    {
        $enRegle = $this->prestataireEnLigne();
        $bloque = $this->prestataireEnLigne();
        $this->bloquer($bloque);

        $mission = $this->mission();

        $this->assertNotNull(app(DispatchEngine::class)->createOffer($mission, $enRegle, 20));
        $this->assertNull(app(DispatchEngine::class)->createOffer($mission, $bloque, 20));
    }

    // ─── 3. L'acceptation ────────────────────────────────────────────────────────────────────

    public function test_lacceptation_est_refusee_si_le_controle_est_du(): void
    {
        $prestataire = $this->prestataireEnLigne();
        $mission = $this->mission();
        $offre = app(DispatchEngine::class)->createOffer($mission, $prestataire, 600);

        // TÉMOIN : à jour, l'acceptation passe.
        $this->assertNotNull(app(MissionDispatchService::class)->accept($offre));

        /*
         * L'ÉCART QUE CE TEST MESURE : l'offre est fabriquée pendant que tout va bien, et
         * l'échéance tombe PENDANT que la modale est ouverte. C'est exactement le scénario de
         * quelqu'un qui garde son écran allumé — sans cette seconde garde, l'acceptation
         * passerait sur la foi d'un contrôle vieux de trois jours.
         */
        $autre = $this->prestataireEnLigne();
        $offre2 = app(DispatchEngine::class)->createOffer($this->mission(), $autre, 600);
        $this->assertNotNull($offre2, "L'offre existe : c'est bien l'acceptation qu'on mesure.");

        $this->rendreLeControleDu($autre);

        $this->expectException(FaceCheckRequiredException::class);
        app(MissionDispatchService::class)->accept($offre2);
    }

    // ─── 4. « Je pars chez le client » ───────────────────────────────────────────────────────

    public function test_le_depart_vers_le_client_est_refuse_si_le_controle_est_du(): void
    {
        $prestataire = $this->prestataireEnLigne();
        $mission = $this->missionAssigneeA($prestataire);

        // TÉMOIN : à jour, le départ passe.
        $this->assertNotNull(app(MissionLifecycleService::class)->setEnRoute($mission, $prestataire));

        $autre = $this->prestataireEnLigne();
        $missionBis = $this->missionAssigneeA($autre);
        $this->rendreLeControleDu($autre);

        $this->expectException(FaceCheckRequiredException::class);
        app(MissionLifecycleService::class)->setEnRoute($missionBis, $autre);
    }

    // ─── 5. Les DEUX services de présence ────────────────────────────────────────────────────

    public function test_la_mise_en_ligne_v2_est_refusee_si_le_controle_est_du(): void
    {
        $enRegle = $this->prestataireEnLigne();
        $this->assertNotNull(app(ProviderPresenceService::class)->goOnline($enRegle, self::LAT, self::LNG));

        $du = $this->prestataireEnLigne();
        $this->rendreLeControleDu($du);

        $this->expectException(FaceCheckRequiredException::class);
        app(ProviderPresenceService::class)->goOnline($du, self::LAT, self::LNG);
    }

    public function test_la_mise_en_ligne_historique_est_refusee_aussi(): void
    {
        $enRegle = $this->prestataireEnLigne();
        $this->assertNotNull(app(PresenceLegacy::class)->goOnline($enRegle, self::LAT, self::LNG));

        $du = $this->prestataireEnLigne();
        $this->rendreLeControleDu($du);

        $this->expectException(FaceCheckRequiredException::class);
        app(PresenceLegacy::class)->goOnline($du, self::LAT, self::LNG);
    }

    // ─── 7. L'affectation interne d'une société ──────────────────────────────────────────────

    public function test_laffectation_interne_dune_societe_est_refusee_aussi(): void
    {
        $societe = OrganizationAccount::create([
            'name' => 'Société test', 'legal_name' => 'Société test', 'slug' => 'societe-fc',
            'type' => 'provider_company', 'email' => 'fc@example.test', 'status' => 'active',
        ]);

        $salarie = $this->prestataireEnLigne();
        $salarie->providerProfile->forceFill(['organization_account_id' => $societe->id])->save();

        $membre = OrganizationMember::create([
            'organization_account_id' => $societe->id,
            'user_id' => $salarie->id,
            'role' => 'worker',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // TÉMOIN : à jour, l'affectation passe.
        app(MissionAssignmentService::class)->assigner($this->mission(), $membre);
        $this->assertTrue(true);

        $this->rendreLeControleDu($salarie);

        $this->expectException(FaceCheckRequiredException::class);
        app(MissionAssignmentService::class)->assigner($this->mission(), $membre->refresh());
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function prestataireEnLigne(bool $enroler = true, ?Trade $metier = null): User
    {
        $metier ??= $this->metierDuControle;

        $user = $this->prestataireSoumis();

        if ($metier->id !== $this->metierDuControle->id) {
            $user->trades()->syncWithoutDetaching([$metier->id]);
        }

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => 'online',
            'current_lat' => self::LAT,
            'current_lng' => self::LNG,
            'heartbeat_at' => now(),
        ]);

        if ($enroler) {
            app(FaceCheckService::class)->enroll($user, 'reference#face:'.$user->id, 'image/jpeg', true);
        }

        return $user->refresh();
    }

    private function bloquer(User $prestataire): void
    {
        $service = app(FaceCheckService::class);
        $service->block($service->profileFor($prestataire), ProviderFaceProfile::BLOCK_FAILED_CHECKS);
    }

    private function rendreLeControleDu(User $prestataire): void
    {
        app(FaceCheckService::class)
            ->profileFor($prestataire)
            ->forceFill(['next_check_due_at' => now()->subMinute()])
            ->save();
    }

    private function reservation(?Trade $metier = null): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'service_zone_id' => $this->zoneDuControle->id,
            'trade_id' => ($metier ?? $this->metierDuControle)->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
        ]);
    }

    private function mission(?Trade $metier = null): Mission
    {
        $booking = $this->reservation($metier);

        return Mission::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'planned',
        ]);
    }

    private function missionAssigneeA(User $prestataire): Mission
    {
        $mission = $this->mission();

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $mission->forceFill([
            'status' => 'assigned',
            'lead_provider_user_id' => $prestataire->id,
            'lead_employee_id' => $prestataire->id,
        ])->save();

        return $mission->refresh();
    }

    /** @return list<int> */
    private function candidats(Booking $booking): array
    {
        return app(CandidateFinder::class)
            ->immediate($booking, 10000)
            ->map(fn (DispatchCandidate $c) => $c->id())
            ->all();
    }
}
