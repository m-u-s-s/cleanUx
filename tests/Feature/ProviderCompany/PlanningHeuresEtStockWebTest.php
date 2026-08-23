<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\InventoryCenter;
use App\Livewire\ProviderCompany\TimesheetCenter;
use App\Livewire\ProviderCompany\WorkforcePlanning;
use App\Models\InventoryItem;
use App\Models\LeaveRequest;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** PHASE 2 — LES SURFACES WEB ET API DU PLANNING (E19), DES HEURES (E20), DES ABSENCES (E21), DE LA RENTABILITÉ (E22) ET DES CONSOMMABLES (E23). */
class PlanningHeuresEtStockWebTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
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

    // ─── Les portes ──────────────────────────────────────────────────────────

    #[Test]
    public function les_trois_ecrans_web_repondent(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        foreach (['planning', 'timesheets', 'inventory'] as $ecran) {
            $this->actingAs($patron)
                ->get(route("provider-company.{$ecran}"))
                ->assertOk();
        }
    }

    #[Test]
    public function les_trois_modules_figurent_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'provider-company')
            ->pluck('route')
            ->all();

        // Un écran absent du répertoire est un écran que personne ne trouve : la route existe, et
        // aucun lien n'y mène.
        $this->assertContains('provider-company.planning', $entrees);
        $this->assertContains('provider-company.timesheets', $entrees);
        $this->assertContains('provider-company.inventory', $entrees);
    }

    // ─── E19 : le planning ───────────────────────────────────────────────────

    #[Test]
    public function creer_un_creneau_ne_le_publie_pas(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->set('shiftUserId', $ouvrier->id)
            ->set('shiftDebut', Carbon::now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'))
            ->set('shiftFin', Carbon::now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i'))
            ->call('ajouterUnCreneau')
            ->assertHasNoErrors();

        // ON NE PUBLIE PAS EN CRÉANT.
        $this->assertDatabaseHas('shifts', [
            'organization_account_id' => $this->org->id,
            'user_id' => $ouvrier->id,
            'status' => Shift::STATUS_PLANNED,
        ]);
    }

    #[Test]
    public function publier_est_le_geste_qui_rend_assignable(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        Shift::factory()->planifie()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $ouvrier->id,
            'starts_at' => Carbon::now()->startOfWeek()->addDays(2)->setTime(8, 0),
            'ends_at' => Carbon::now()->startOfWeek()->addDays(2)->setTime(17, 0),
        ]);

        Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->call('publierLaSemaine');

        $this->assertDatabaseHas('shifts', [
            'user_id' => $ouvrier->id,
            'status' => Shift::STATUS_PUBLISHED,
        ]);
    }

    #[Test]
    public function un_executant_ne_planifie_personne(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        // Livewire ne rejoue jamais `mount()` : la garde doit vivre DANS l'action.
        Livewire::actingAs($ouvrier)
            ->test(WorkforcePlanning::class)
            ->set('shiftUserId', $ouvrier->id)
            ->set('shiftDebut', Carbon::now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'))
            ->set('shiftFin', Carbon::now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i'))
            ->call('ajouterUnCreneau')
            ->assertForbidden();
    }

    #[Test]
    public function on_ne_planifie_pas_l_employe_d_une_autre_societe(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);
        $etranger = $this->membre(OrganizationRole::WORKER, $autreSociete);

        Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->set('shiftUserId', $etranger->id)
            ->set('shiftDebut', Carbon::now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'))
            ->set('shiftFin', Carbon::now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i'))
            ->call('ajouterUnCreneau');

        // Sinon quelqu'un verrait apparaître un créneau chez un employeur qui n'est pas le sien.
        $this->assertDatabaseMissing('shifts', ['user_id' => $etranger->id]);
    }

    #[Test]
    public function sans_team_view_on_ne_voit_que_son_propre_planning(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        $collegue = $this->membre(OrganizationRole::WORKER);

        $jour = Carbon::now()->startOfWeek()->addDays(2);

        Shift::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $ouvrier->id,
            'starts_at' => $jour->copy()->setTime(8, 0),
            'ends_at' => $jour->copy()->setTime(17, 0),
        ]);

        Shift::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $collegue->id,
            'starts_at' => $jour->copy()->setTime(8, 0),
            'ends_at' => $jour->copy()->setTime(17, 0),
        ]);

        // L'écran est OUVERT — poser son congé est un geste de salarié — mais le planning des
        // collègues est une information d'exploitation, pas un annuaire.
        Livewire::actingAs($ouvrier)
            ->test(WorkforcePlanning::class)
            ->assertOk()
            ->assertViewHas('creneaux', fn ($creneaux) => $creneaux->count() === 1
                && (int) $creneaux->first()->user_id === $ouvrier->id);
    }

    // ─── E21 : les absences ──────────────────────────────────────────────────

    #[Test]
    public function chacun_pose_sa_propre_absence_et_un_responsable_tranche(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        Livewire::actingAs($ouvrier)
            ->test(WorkforcePlanning::class)
            ->set('congeDebut', Carbon::now()->addDays(10)->toDateString())
            ->set('congeFin', Carbon::now()->addDays(14)->toDateString())
            ->call('poserUneAbsence')
            ->assertHasNoErrors();

        $demande = LeaveRequest::query()->where('user_id', $ouvrier->id)->firstOrFail();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $demande->status);

        Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->call('statuerSurLAbsence', $demande->id, true);

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $demande->fresh()->status);
    }

    #[Test]
    public function on_ne_s_approuve_pas_soi_meme(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $demande = LeaveRequest::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $patron->id,
        ]);

        $composant = Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->call('statuerSurLAbsence', $demande->id, true);

        // Le planning se viderait sans que personne n'ait accepté d'assumer le trou.
        $this->assertSame(LeaveRequest::STATUS_PENDING, $demande->fresh()->status);
        $composant->assertSet('refus', 'Une absence ne s’approuve pas soi-même.');
    }

    #[Test]
    public function l_absence_d_une_autre_societe_reste_hors_de_portee(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);
        $etranger = $this->membre(OrganizationRole::WORKER, $autreSociete);

        $demande = LeaveRequest::factory()->create([
            'organization_account_id' => $autreSociete->id,
            'user_id' => $etranger->id,
        ]);

        Livewire::actingAs($patron)
            ->test(WorkforcePlanning::class)
            ->call('statuerSurLAbsence', $demande->id, true);

        // Le scoping fait partie de la REQUÊTE : la ligne n'est jamais chargée.
        $this->assertSame(LeaveRequest::STATUS_PENDING, $demande->fresh()->status);
    }

    // ─── E20 / E22 : les heures et la marge ──────────────────────────────────

    #[Test]
    public function une_correction_s_approuve_depuis_l_ecran(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        $entry = TimeEntry::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $ouvrier->id,
            'worked_minutes' => 180,
            'status' => TimeEntry::STATUS_PENDING_APPROVAL,
        ]);

        Livewire::actingAs($patron)
            ->test(TimesheetCenter::class)
            ->call('statuer', $entry->id, true);

        $this->assertSame(TimeEntry::STATUS_APPROVED, $entry->fresh()->status);
    }

    #[Test]
    public function un_executant_n_approuve_pas_les_heures(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        $collegue = $this->membre(OrganizationRole::WORKER);

        $entry = TimeEntry::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $collegue->id,
            'status' => TimeEntry::STATUS_PENDING_APPROVAL,
        ]);

        Livewire::actingAs($ouvrier)
            ->test(TimesheetCenter::class)
            ->call('statuer', $entry->id, true)
            ->assertForbidden();

        $this->assertSame(TimeEntry::STATUS_PENDING_APPROVAL, $entry->fresh()->status);
    }

    #[Test]
    public function la_marge_ne_s_affiche_pas_sans_analytics_view(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        // MÊME RÈGLE QUE L'API, DÉLIBÉRÉMENT.
        Livewire::actingAs($ouvrier)
            ->test(TimesheetCenter::class)
            ->assertOk()
            ->assertSet('refus', null)
            ->assertViewHas('peutVoirLaMarge', false)
            ->assertViewHas('rentabilite', fn ($lignes) => $lignes->isEmpty());
    }

    // ─── E23 : les consommables ──────────────────────────────────────────────

    #[Test]
    public function un_article_nait_a_zero_et_monte_par_une_reception(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $composant = Livewire::actingAs($patron)
            ->test(InventoryCenter::class)
            ->set('nom', 'Sacs poubelle 100 L')
            ->set('unite', 'carton')
            ->set('seuil', 5)
            ->call('creerLArticle')
            ->assertHasNoErrors();

        $article = InventoryItem::query()->where('name', 'Sacs poubelle 100 L')->firstOrFail();

        // Le compteur est le RÉSULTAT des mouvements : le saisir à la création en ferait une valeur
        // écrite, sans mouvement qui l'explique.
        $this->assertSame(0, $article->quantity);

        $composant
            ->set('quantite', 12)
            ->call('receptionner', $article->id);

        $this->assertSame(12, $article->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $article->id,
            'type' => 'reception',
            'quantity' => 12,
        ]);
    }

    #[Test]
    public function le_stock_ne_descend_pas_sous_zero_et_on_le_dit(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $article = InventoryItem::factory()->create([
            'organization_account_id' => $this->org->id,
            'quantity' => 3,
            'unit' => 'carton',
        ]);

        $composant = Livewire::actingAs($patron)
            ->test(InventoryCenter::class)
            ->set('quantite', 10)
            ->call('consommer', $article->id);

        // Un compteur négatif que personne ne saurait expliquer : on refuse et on le dit.
        $this->assertSame(3, $article->fresh()->quantity);
        $composant->assertSet('refus', 'Il ne reste que 3 carton en stock.');
    }

    #[Test]
    public function voir_le_stock_n_est_pas_le_bouger(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        $article = InventoryItem::factory()->create([
            'organization_account_id' => $this->org->id,
            'quantity' => 10,
        ]);

        // `inventory.view` va jusqu'aux exécutants — savoir ce qui reste avant de partir n'est pas
        // commander.
        Livewire::actingAs($ouvrier)
            ->test(InventoryCenter::class)
            ->assertOk()
            ->set('quantite', 2)
            ->call('consommer', $article->id)
            ->assertForbidden();

        $this->assertSame(10, $article->fresh()->quantity);
    }

    // ─── L'API native ────────────────────────────────────────────────────────

    #[Test]
    public function l_api_sert_les_cinq_modules(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/shifts')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson('/api/provider/company/leaves')->assertOk()->assertJsonPath('meta.can_manage', true);
        $this->getJson('/api/provider/company/timesheets')->assertOk()->assertJsonStructure(['data', 'pending']);
        $this->getJson('/api/provider/company/profitability')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson('/api/provider/company/inventory')->assertOk()->assertJsonPath('meta.can_manage', true);
    }

    #[Test]
    public function l_api_ne_montre_pas_les_absences_des_collegues_a_un_executant(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        $collegue = $this->membre(OrganizationRole::WORKER);

        LeaveRequest::factory()->create([
            'organization_account_id' => $this->org->id,
            'user_id' => $collegue->id,
            'starts_on' => Carbon::now()->addDays(3)->toDateString(),
            'ends_on' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($ouvrier, ['*']);

        // UNE ABSENCE DIT LA MALADIE, LA GARDE D'ENFANT, L'ACCOMPAGNEMENT D'UN PROCHE.
        $this->getJson('/api/provider/company/leaves')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.can_manage', false);
    }

    #[Test]
    public function la_marge_n_est_pas_une_donnee_d_equipe(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        Sanctum::actingAs($ouvrier, ['*']);

        // Elle dit ce que coûte chaque personne : un exécutant n'a pas à lire le prix de ses propres
        // heures dans un écran d'exploitation.
        $this->getJson('/api/provider/company/profitability')->assertForbidden();
    }

    #[Test]
    public function un_refus_du_domaine_sort_en_422_pas_en_500(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        Sanctum::actingAs($patron, ['*']);

        $article = InventoryItem::factory()->create([
            'organization_account_id' => $this->org->id,
            'quantity' => 3,
            'unit' => 'carton',
        ]);

        // « Il ne reste que trois cartons » est une réponse, pas une panne : la remplacer par « une
        // erreur est survenue » obligerait à rappeler le bureau.
        $this->postJson("/api/provider/company/inventory/{$article->id}/movements", [
            'type' => 'consumption',
            'quantity' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Il ne reste que 3 carton en stock.');
    }

    #[Test]
    public function l_api_refuse_le_stock_d_une_autre_societe(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);
        $article = InventoryItem::factory()->create([
            'organization_account_id' => $autreSociete->id,
            'quantity' => 50,
        ]);

        Sanctum::actingAs($patron, ['*']);

        // 404 et non 403 : la différence dirait à un appelant qu'une ressource existe ailleurs.
        $this->postJson("/api/provider/company/inventory/{$article->id}/movements", [
            'type' => 'consumption',
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertSame(50, $article->fresh()->quantity);
    }
}
