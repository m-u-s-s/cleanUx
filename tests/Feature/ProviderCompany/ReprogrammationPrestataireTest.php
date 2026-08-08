<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LOT 5 — DÉPLACER UNE INTERVENTION : DATE, HEURE ET LIEU.
 *
 * `BookingRescheduleService` était strictement CLIENT/ADMIN : son `authorize()` n'admet que le
 * propriétaire de la réservation ou un membre de l'organisation cliente, et aucun endpoint ne
 * l'exposait au prestataire. Une société qui devait décaler d'une heure — un embouteillage, une clé
 * non remise, un chantier qui déborde — appelait le client pour qu'il le fasse lui-même. Le LIEU,
 * lui, ne bougeait jamais : la notion n'existait dans aucun chemin.
 *
 * LE CHEMIN CLIENT N'EST PAS TOUCHÉ, et ce fichier le vérifie : c'est la moitié du travail sur une
 * méthode partagée.
 */
class ReprogrammationPrestataireTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $this->org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    /** Un rendez-vous confirmé exécuté par notre société — donc une mission qui la porte. */
    private function rendezVous(?Carbon $quand = null, ?int $siteId = null, ?int $clientOrgId = null): Booking
    {
        $quand ??= now()->addWeek()->setTime(9, 0);

        $salarie = $this->membre(OrganizationRole::WORKER);

        return Booking::factory()->create([
            'employe_id' => $salarie->id,
            'assigned_provider_organization_id' => $this->org->id,
            'organization_account_id' => $clientOrgId,
            'organization_site_id' => $siteId,
            'status' => BookingStatus::CONFIRME,
            'date' => $quand->toDateString(),
            'scheduled_date' => $quand->toDateString(),
            'heure' => $quand->format('H:i:s'),
        ]);
    }

    private function missionDe(Booking $rendezVous): Mission
    {
        return Mission::where('rendez_vous_id', $rendezVous->id)->firstOrFail();
    }

    // ──────────────────────────────────────────────────────
    // Le geste, et sa propagation
    // ──────────────────────────────────────────────────────

    public function test_le_dispatcheur_deplace_une_intervention(): void
    {
        $dispatcheur = $this->membre(OrganizationRole::DISPATCHER);
        $rendezVous = $this->rendezVous();
        $mission = $this->missionDe($rendezVous);

        $nouvelleDate = now()->addWeek()->addDay()->toDateString();

        $this->actingAs($dispatcheur, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => $nouvelleDate,
                'heure' => '14:00',
            ])
            ->assertOk();

        $rendezVous->refresh();

        $this->assertSame($nouvelleDate, $rendezVous->scheduled_date->toDateString());
        $this->assertStringStartsWith('14:00', (string) $rendezVous->heure);
    }

    public function test_la_mission_suit_le_rendez_vous(): void
    {
        /*
         * LA PROPAGATION EXISTE DÉJÀ — `RendezVousObserver` resynchronise les `planned_*`. Ce qui
         * pouvait manquer, c'est de mettre à jour les colonnes LEGACY `date`/`heure` : ce sont
         * elles que lit `MissionFromRendezVousSyncService`. Ne toucher que `scheduled_*`
         * déplacerait le rendez-vous sans déplacer la mission, et l'équipe se présenterait à
         * l'ancienne heure.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $rendezVous = $this->rendezVous();
        $mission = $this->missionDe($rendezVous);

        $nouvelleDate = now()->addWeeks(2)->toDateString();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => $nouvelleDate,
                'heure' => '11:30',
            ])
            ->assertOk();

        $mission->refresh();

        $this->assertSame($nouvelleDate, $mission->planned_start_at?->toDateString());
        $this->assertSame('11:30', $mission->planned_start_at?->format('H:i'));
    }

    public function test_l_historique_retient_qui_a_deplace_et_a_quel_titre(): void
    {
        // `booking_reschedule_history` ne disait pas à QUEL TITRE : le client qui s'arrange, l'admin
        // qui corrige et le prestataire qui réorganise sa tournée ne sont pas la même chose.
        $owner = $this->membre(OrganizationRole::OWNER);
        $rendezVous = $this->rendezVous();
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
                'motif' => 'Tournée réorganisée',
            ])
            ->assertOk();

        $this->assertDatabaseHas('booking_reschedule_history', [
            'booking_id' => $rendezVous->id,
            'user_id' => $owner->id,
            'actor_context' => BookingRescheduleService::CONTEXTE_PRESTATAIRE,
            'reason' => 'Tournée réorganisée',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Le LIEU
    // ──────────────────────────────────────────────────────

    public function test_le_lieu_change_vers_un_autre_local_du_meme_client(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);

        $clientOrg = OrganizationAccount::factory()->create(['type' => OrganizationType::CLIENT_COMPANY->value]);
        $siteA = OrganizationSite::factory()->create(['organization_account_id' => $clientOrg->id]);
        $siteB = OrganizationSite::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'address' => 'Rue Neuve 12',
            'city' => 'Anvers',
        ]);

        $rendezVous = $this->rendezVous(siteId: $siteA->id, clientOrgId: $clientOrg->id);
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
                'organization_site_id' => $siteB->id,
            ])
            ->assertOk();

        $this->assertSame($siteB->id, $rendezVous->fresh()->organization_site_id);
        $this->assertSame($siteB->id, $mission->fresh()->organization_site_id);

        // Le déplacement de lieu est tracé : sans lui, une réclamation « l'équipe s'est trompée
        // d'adresse » est impossible à instruire.
        $this->assertDatabaseHas('booking_reschedule_history', [
            'booking_id' => $rendezVous->id,
            'old_site_id' => $siteA->id,
            'new_site_id' => $siteB->id,
        ]);
    }

    public function test_on_ne_deplace_pas_vers_le_local_d_un_autre_client(): void
    {
        /*
         * Au mieux une erreur de saisie envoyant une équipe ailleurs, au pire une fuite sur
         * l'existence de ces locaux.
         */
        $owner = $this->membre(OrganizationRole::OWNER);

        $clientOrg = OrganizationAccount::factory()->create(['type' => OrganizationType::CLIENT_COMPANY->value]);
        $siteA = OrganizationSite::factory()->create(['organization_account_id' => $clientOrg->id]);

        $autreClient = OrganizationAccount::factory()->create(['type' => OrganizationType::CLIENT_COMPANY->value]);
        $siteEtranger = OrganizationSite::factory()->create(['organization_account_id' => $autreClient->id]);

        $rendezVous = $this->rendezVous(siteId: $siteA->id, clientOrgId: $clientOrg->id);
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
                'organization_site_id' => $siteEtranger->id,
            ])
            ->assertStatus(422);

        $this->assertSame($siteA->id, $rendezVous->fresh()->organization_site_id);
    }

    public function test_une_adresse_libre_remplace_le_lieu_en_b2c(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $rendezVous = $this->rendezVous();
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
                'adresse' => 'Chaussée de Waterloo 300',
            ])
            ->assertOk();

        $this->assertSame('Chaussée de Waterloo 300', $rendezVous->fresh()->adresse);
    }

    // ──────────────────────────────────────────────────────
    // La fenêtre de gel
    // ──────────────────────────────────────────────────────

    public function test_a_moins_de_24h_le_dispatcheur_ne_deplace_plus(): void
    {
        /*
         * Déplacer la veille au soir n'est pas la même décision que déplacer la semaine
         * précédente : le client a organisé sa journée autour. La borne n'interdit pas, elle relève
         * le niveau de décision.
         */
        $dispatcheur = $this->membre(OrganizationRole::DISPATCHER);
        $rendezVous = $this->rendezVous(now()->addHours(12));
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($dispatcheur, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_a_moins_de_24h_l_owner_deplace_avec_un_motif(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $rendezVous = $this->rendezVous(now()->addHours(12));
        $mission = $this->missionDe($rendezVous);

        $nouvelleDate = now()->addDays(3)->toDateString();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => $nouvelleDate,
                'motif' => 'Client injoignable, accès impossible',
            ])
            ->assertOk();

        $this->assertSame($nouvelleDate, $rendezVous->fresh()->scheduled_date->toDateString());
    }

    public function test_a_moins_de_24h_le_motif_est_obligatoire(): void
    {
        // Le motif n'est pas décoratif : le client le lira dans sa notification, et c'est ce qui
        // rend l'application immédiate acceptable.
        $owner = $this->membre(OrganizationRole::OWNER);
        $rendezVous = $this->rendezVous(now()->addHours(12));
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────
    // Les gardes
    // ──────────────────────────────────────────────────────

    public function test_le_worker_ne_deplace_rien(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $rendezVous = $this->rendezVous();
        $mission = $this->missionDe($rendezVous);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_on_ne_deplace_pas_la_mission_d_une_autre_societe(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);

        $missionAdverse = Mission::factory()->create([
            'provider_organization_id' => $autreOrg->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$missionAdverse->id}/reschedule", [
                'date' => now()->addWeeks(2)->toDateString(),
            ])
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────────────
    // Non-régression du chemin CLIENT
    // ──────────────────────────────────────────────────────

    public function test_le_chemin_client_reste_intact(): void
    {
        /*
         * LA MOITIÉ DU TRAVAIL SUR UNE MÉTHODE PARTAGÉE. `reschedule()` garde son autorisation, son
         * absence de fenêtre de gel et son contexte d'audit — la reprogrammation prestataire est
         * une méthode À CÔTÉ, pas une modification de celle-ci.
         */
        $client = User::factory()->create();

        $rendezVous = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'date' => now()->addWeek()->toDateString(),
            'scheduled_date' => now()->addWeek()->toDateString(),
            'heure' => '09:00:00',
        ]);

        $nouvelleDate = now()->addWeeks(3);

        app(BookingRescheduleService::class)->reschedule($client, $rendezVous, $nouvelleDate, '10:00');

        $this->assertSame($nouvelleDate->toDateString(), $rendezVous->fresh()->scheduled_date->toDateString());

        $this->assertDatabaseHas('booking_reschedule_history', [
            'booking_id' => $rendezVous->id,
            'actor_context' => BookingRescheduleService::CONTEXTE_CLIENT,
        ]);
    }
}
