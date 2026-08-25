<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Livewire\ClientCompany\SiteManager;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\OrganizationSiteBudget;
use App\Models\User;
use App\Services\Enterprise\ClientAccountingExportService;
use App\Services\Enterprise\InternalApprovalService;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Services\Enterprise\ServiceLevelService;
use App\Services\Enterprise\SiteBudgetService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** PHASE 4 — BUDGETS (E7), APPROBATIONS (E8), NIVEAU DE SERVICE (E9), ACCÈS PAR LOCAL (E10) ET EXPORTS COMPTABLES (E11). */
class PilotageEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * LE TEMPS EST FIGE AU 5 DU MOIS, ET C'EST INDISPENSABLE.
         *
         * La fabrique de budget pose `period_start` au PREMIER du mois, et la periode se ferme
         * le dernier jour. Les tests d'engagement reservent a `now()->addWeek()` : passe le 24,
         * cette semaine tombe le mois SUIVANT, la reservation sort de la fenetre et le budget
         * compte zero. Ces tests echouaient donc les six derniers jours de chaque mois — sans
         * qu'aucun code ait bouge.
         */
        Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays(4)->setTime(9, 0));

        Notification::fake();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->client()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function local(?string $nom = null): OrganizationSite
    {
        return OrganizationSite::factory()->create([
            'organization_account_id' => $this->org->id,
            'name' => $nom ?? 'Siège',
        ]);
    }

    // ─── Les portes ──────────────────────────────────────────────────────────

    #[Test]
    public function l_ecran_de_pilotage_repond(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($patron)
            ->get(route('client-company.governance'))
            ->assertOk();
    }

    #[Test]
    public function le_pilotage_figure_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'client-company')
            ->pluck('route')
            ->all();

        $this->assertContains('client-company.governance', $entrees);
    }

    // ─── E7 : les budgets ────────────────────────────────────────────────────

    #[Test]
    public function le_budget_compte_l_engage_pas_le_facture(): void
    {
        $budget = OrganizationSiteBudget::factory()->create([
            'organization_account_id' => $this->org->id,
            'limit_cents' => 100000,
        ]);

        Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'devis_estime' => 300.0,
            // La semaine prochaine : elle n'est pas facturée, et elle engage déjà.
            'scheduled_at' => Carbon::now()->addWeek(),
        ]);

        $etat = app(SiteBudgetService::class)->etat($budget);

        // ATTENDRE LA FACTURE ferait découvrir le dépassement quand il est consommé — exactement le défaut que ce module corrige.
        $this->assertSame(30000, $etat['committed_cents']);
        $this->assertSame(30, $etat['usage_percent']);
    }

    #[Test]
    public function une_annulee_n_engage_plus_rien(): void
    {
        $budget = OrganizationSiteBudget::factory()->create([
            'organization_account_id' => $this->org->id,
        ]);

        Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'devis_estime' => 500.0,
            'scheduled_at' => Carbon::now()->addWeek(),
            'status' => 'annule',
        ]);

        $this->assertSame(0, app(SiteBudgetService::class)->etat($budget)['committed_cents']);
    }

    #[Test]
    public function le_plafond_alerte_mais_ne_bloque_pas(): void
    {
        $budget = OrganizationSiteBudget::factory()->create([
            'organization_account_id' => $this->org->id,
            'limit_cents' => 10000,
            'alert_threshold_percent' => 80,
        ]);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'devis_estime' => 200.0,
            'scheduled_at' => Carbon::now()->addWeek(),
        ]);

        app(SiteBudgetService::class)->verifierApresReservation($booking);

        // LA RÉSERVATION EXISTE TOUJOURS.
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => $booking->status]);

        // Et le palier est retenu : sans lui, chaque réservation suivante renverrait le même
        // message, et le quatrième serait ignoré comme les trois premiers.
        $this->assertSame(100, $budget->fresh()->alerted_at_percent);
    }

    #[Test]
    public function l_alerte_ne_se_repete_pas_au_meme_palier(): void
    {
        $budget = OrganizationSiteBudget::factory()->create([
            'organization_account_id' => $this->org->id,
            'limit_cents' => 10000,
        ]);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'devis_estime' => 200.0,
            'scheduled_at' => Carbon::now()->addWeek(),
        ]);

        $service = app(SiteBudgetService::class);
        $service->verifierApresReservation($booking);
        $premier = $budget->fresh()->alerted_at;

        $service->verifierApresReservation($booking);

        // Le même palier ne réalerte pas : le bruit se désactive, et la vraie alerte se perd avec.
        $this->assertEquals($premier, $budget->fresh()->alerted_at);
    }

    // ─── E8 : les approbations ───────────────────────────────────────────────

    #[Test]
    public function approuver_entre_dans_le_dispatch(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $demandeur = $this->membre(OrganizationRole::REQUESTER);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'customer_user_id' => $demandeur->id,
            'status' => InternalApprovalService::STATUT_EN_ATTENTE,
        ]);

        $resultat = app(InternalApprovalService::class)->approuver($booking, $patron);

        // UNE APPROBATION QUI NE DÉCLENCHE RIEN EST UN TAMPON SUR UN FORMULAIRE.
        $this->assertSame('pending', $resultat->status);
        // Et la décision laisse une trace : un contrôle interne sans trace ne sert pas au contrôle
        // interne.
        $this->assertSame($patron->id, data_get($resultat->metadata, 'internal_approval.approved_by'));
    }

    #[Test]
    public function on_n_approuve_pas_sa_propre_demande(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'customer_user_id' => $patron->id,
            'status' => InternalApprovalService::STATUT_EN_ATTENTE,
        ]);

        // Sans cette garde, un demandeur qui possède aussi le droit d'approuver contourne le
        // circuit sans le savoir — et l'entreprise croit avoir un contrôle qu'elle n'a pas.
        $this->expectException(DomainException::class);

        app(InternalApprovalService::class)->approuver($booking, $patron);
    }

    #[Test]
    public function le_refus_conserve_son_motif(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $demandeur = $this->membre(OrganizationRole::REQUESTER);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'customer_user_id' => $demandeur->id,
            'status' => InternalApprovalService::STATUT_EN_ATTENTE,
        ]);

        $resultat = app(InternalApprovalService::class)->refuser($booking, $patron, 'Budget épuisé.');

        // Un refus effacé, c'est la même demande qui revient la semaine suivante sans que personne
        // ne se souvienne pourquoi elle avait été écartée.
        $this->assertSame('Budget épuisé.', data_get($resultat->metadata, 'internal_approval.reason'));
    }

    #[Test]
    public function l_api_refuse_l_approbation_a_qui_n_y_a_pas_droit(): void
    {
        $demandeur = $this->membre(OrganizationRole::REQUESTER);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'status' => InternalApprovalService::STATUT_EN_ATTENTE,
        ]);

        Sanctum::actingAs($demandeur, ['*']);

        $this->postJson("/api/client/company/approvals/{$booking->id}/decision", ['approve' => true])
            ->assertForbidden();
    }

    #[Test]
    public function l_api_refuse_soi_meme_en_422(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $booking = Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'customer_user_id' => $patron->id,
            'status' => InternalApprovalService::STATUT_EN_ATTENTE,
        ]);

        Sanctum::actingAs($patron, ['*']);

        // Une règle métier est une réponse à LIRE, pas une panne.
        $this->postJson("/api/client/company/approvals/{$booking->id}/decision", ['approve' => true])
            ->assertStatus(422);
    }

    // ─── E9 : le niveau de service ───────────────────────────────────────────

    #[Test]
    public function les_missions_sans_arrivee_relevee_sont_comptees_a_part(): void
    {
        $local = $this->local();

        Booking::factory()->create([
            'customer_organization_id' => $this->org->id,
            'organization_site_id' => $local->id,
            'scheduled_at' => Carbon::now()->subDays(2),
            'status' => 'completed',
        ]);

        $resume = app(ServiceLevelService::class)->resume(
            $this->org->id,
            Carbon::now()->subMonth(),
            Carbon::now(),
        );

        // LES COMPTER COMME DES RETARDS punirait un GPS coupé ; comme des arrivées à l'heure, l'inverse.
        $this->assertNull($resume['punctuality_rate']);
        $this->assertSame(1, $resume['without_arrival_data']);
        $this->assertSame(100.0, $resume['completion_rate']);
    }

    // ─── E10 : l'accès par local ─────────────────────────────────────────────

    #[Test]
    public function la_restriction_par_local_filtre_la_liste_des_locaux(): void
    {
        $responsable = $this->membre(OrganizationRole::SITE_MANAGER);

        $sien = $this->local('Agence Nord');
        $autre = $this->local('Agence Sud');

        $membre = OrganizationMember::query()
            ->where('organization_account_id', $this->org->id)
            ->where('user_id', $responsable->id)
            ->firstOrFail();

        app(MemberSiteAccessService::class)->definirLesSites($membre, [$sien->id]);

        // L'ÉCRAN DE RÉGLAGE EXISTAIT DÉJÀ et ne réglait rien : un responsable de site voyait TOUS les locaux de sa société — adresses, codes d'accès et réservations des autres agences comprises.
        Livewire::actingAs($responsable->fresh())
            ->test(SiteManager::class)
            ->assertOk()
            ->assertViewHas('sites', fn ($sites) => $sites->pluck('id')->all() === [$sien->id]);

        $this->assertNotSame($sien->id, $autre->id);
    }

    #[Test]
    public function sans_restriction_declaree_on_voit_tout(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $this->local('Agence Nord');
        $this->local('Agence Sud');

        // `null` NE VEUT PAS DIRE « AUCUN ACCÈS ».
        Livewire::actingAs($patron)
            ->test(SiteManager::class)
            ->assertViewHas('sites', fn ($sites) => $sites->count() === 2);
    }

    // ─── E11 : les exports ───────────────────────────────────────────────────

    #[Test]
    public function l_export_csv_porte_ses_entetes(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $export = app(ClientAccountingExportService::class)->csv(
            $patron,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        // Point-virgule et non virgule : les tableurs francophones ouvrent le CSV avec le
        // séparateur régional, et une virgule empilerait tout dans la première colonne.
        $this->assertStringStartsWith('numero;date_emission;', $export['content']);
        $this->assertStringEndsWith('.csv', $export['filename']);
    }

    #[Test]
    public function l_export_fec_est_tabule(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $export = app(ClientAccountingExportService::class)->fec(
            $patron,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        // LA NORME FEC IMPOSE LA TABULATION.
        $this->assertStringContainsString("JournalCode\tJournalLib", $export['content']);
        $this->assertStringStartsWith('FEC-', $export['filename']);
    }

    #[Test]
    public function l_export_ne_sort_que_les_factures_de_sa_societe(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
        ]);

        FinanceInvoice::factory()->create([
            'organization_account_id' => $autreSociete->id,
            'invoice_number' => 'FACT-CONCURRENT-001',
            'issued_at' => Carbon::now(),
        ]);

        $export = app(ClientAccountingExportService::class)->csv(
            $patron,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        // ON NE RÉUTILISE PAS `ExportManager`, ET C'EST DÉLIBÉRÉ : il exporte le grand livre de la PLATEFORME, sans notion d'organisation.
        $this->assertStringNotContainsString('FACT-CONCURRENT-001', $export['content']);
        $this->assertSame(0, $export['rows']);
    }

    #[Test]
    public function le_pilotage_est_ferme_a_qui_n_a_ni_finance_ni_approbation(): void
    {
        $lecteur = $this->membre(OrganizationRole::VIEWER);

        // Deux portes : la finance regarde les budgets, l'exploitation les approbations. Qui n'a
        // ni l'une ni l'autre n'a rien à y faire.
        $this->actingAs($lecteur)
            ->get(route('client-company.governance'))
            ->assertForbidden();
    }
}
