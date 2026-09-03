<?php

namespace Tests\Feature\Commission;

use App\Livewire\Admin\CentreDesCommissions;
use App\Models\CommissionRule;
use App\Models\CommissionRuleRevision;
use App\Models\Trade;
use App\Models\User;
use App\Services\Commission\ContexteDeCommission;
use App\Services\Commission\GestionDesCommissions;
use App\Services\Commission\ResolveurDeCommission;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CENTRE DES COMMISSIONS — réservé au titulaire du siège.
 *
 * Un taux décide de ce que gagnent des milliers de prestataires. Ce n'est pas une capacité qu'on
 * accorde à un administrateur : c'est la propriété de la plateforme. Chaque refus porte son
 * témoin — un garde qui passerait au vert parce que l'écran est cassé ne prouverait rien.
 */
class LeCentreDesCommissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['brio.platform_fee_percent' => 15, 'brio.minimum_commission_cents' => 200]);
        app(ResolveurDeCommission::class)->oublierLeCache();
    }

    // ── La porte ───────────────────────────────────────────────────────────

    public function test_le_titulaire_du_siege_entre(): void
    {
        $this->actingAs($this->titulaire())
            ->get(route('admin.commissions'))
            ->assertOk()
            ->assertSee('Centre des commissions');
    }

    /** UN ADMINISTRATEUR COMPLET RESTE DEHORS : ce n'est pas une capacité, c'est la propriété. */
    public function test_un_administrateur_meme_complet_reste_dehors(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);

        Livewire::actingAs($admin)->test(CentreDesCommissions::class)->assertForbidden();
    }

    /** ET LE SERVICE LE REFUSE AUSSI — pas seulement l'écran. */
    public function test_le_service_refuse_un_administrateur_ordinaire(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);

        $this->expectException(DomainException::class);

        app(GestionDesCommissions::class)->creer($admin, ['label' => 'X', 'module' => 'prestation', 'percent' => 5]);
    }

    // ── Régler ─────────────────────────────────────────────────────────────

    public function test_le_titulaire_regle_un_taux_par_metier(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);

        Livewire::actingAs($this->titulaire())
            ->test(CentreDesCommissions::class)
            ->set('libelle', 'Course à 8 %')
            ->set('module', CommissionRule::MODULE_PRESTATION)
            ->set('metier', $course->id)
            ->set('pourcentage', '8')
            ->call('enregistrerLaRegle')
            ->assertSet('erreur', null);

        $regle = CommissionRule::query()->firstOrFail();

        $this->assertSame('Course à 8 %', $regle->label);
        $this->assertEqualsWithDelta(8.0, $regle->percent, 0.001);
        $this->assertSame($course->id, $regle->trade_id);
    }

    /** UN TAUX HORS BORNES EST REFUSÉ : négatif paierait deux fois, au-dessus de cent ferait devoir. */
    public function test_un_taux_hors_bornes_est_refuse(): void
    {
        $this->expectException(DomainException::class);

        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Absurde', 'module' => 'prestation', 'percent' => 140,
        ]);
    }

    /** UNE FENÊTRE À L'ENVERS NE S'OUVRE JAMAIS, et rien ne le dirait. */
    public function test_une_fenetre_a_l_envers_est_refusee(): void
    {
        $this->expectException(DomainException::class);

        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Impossible', 'module' => 'prestation', 'percent' => 5,
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->toDateString(),
        ]);
    }

    /** TÉMOIN — la même règle dans le bon sens passe. */
    public function test_temoin_la_meme_fenetre_dans_le_bon_sens_passe(): void
    {
        $regle = app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Campagne', 'module' => 'prestation', 'percent' => 5,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
        ]);

        $this->assertNotNull($regle->id);
    }

    // ── L'historique ───────────────────────────────────────────────────────

    /** UN CHANGEMENT DE TAUX QU'ON NE PEUT PAS DATER NE SE CONTESTE PAS. */
    public function test_chaque_changement_laisse_sa_trace(): void
    {
        $titulaire = $this->titulaire();
        $service = app(GestionDesCommissions::class);

        $regle = $service->creer($titulaire, ['label' => 'Course', 'module' => 'prestation', 'percent' => 8]);
        $service->modifier($titulaire, $regle, ['label' => 'Course', 'module' => 'prestation', 'percent' => 12]);

        $traces = CommissionRuleRevision::query()->orderBy('id')->get();

        $this->assertCount(2, $traces);
        $this->assertSame('created', $traces[0]->action);
        $this->assertEqualsWithDelta(8.0, $traces[1]->percent_before, 0.001);
        $this->assertEqualsWithDelta(12.0, $traces[1]->percent_after, 0.001);
        $this->assertSame($titulaire->id, $traces[1]->actor_id);
    }

    /** LA TRACE SURVIT À LA RÈGLE : sinon supprimer effacerait la preuve de ce qui a été facturé. */
    public function test_la_trace_survit_a_la_suppression(): void
    {
        $titulaire = $this->titulaire();
        $service = app(GestionDesCommissions::class);

        $regle = $service->creer($titulaire, ['label' => 'Course', 'module' => 'prestation', 'percent' => 8]);
        $service->supprimer($titulaire, $regle);

        $this->assertSame(0, CommissionRule::query()->count());
        $this->assertSame(2, CommissionRuleRevision::query()->count());
        $this->assertSame('Course', data_get(CommissionRuleRevision::query()->latest('id')->first()?->snapshot, 'label'));
    }

    // ── Le cache ───────────────────────────────────────────────────────────

    /** UN TAUX RÉGLÉ S'APPLIQUE AU DEVIS SUIVANT, pas dans cinq minutes. */
    public function test_un_taux_regle_s_applique_immediatement(): void
    {
        $resolveur = app(ResolveurDeCommission::class);

        // On chauffe le cache avec l'état d'avant.
        $this->assertEqualsWithDelta(0.15, $resolveur->pour(
            ContexteDeCommission::prestation(),
        )->taux, 0.0001);

        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Nouveau', 'module' => 'prestation', 'percent' => 6,
        ]);

        $this->assertEqualsWithDelta(0.06, $resolveur->pour(
            ContexteDeCommission::prestation(),
        )->taux, 0.0001);
    }

    // ── Le simulateur ──────────────────────────────────────────────────────

    /** LE SIMULATEUR MONTRE CE QUI GAGNE, ET CE QUE ÇA MASQUE. */
    public function test_le_simulateur_montre_la_regle_gagnante_et_les_masquees(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);

        CommissionRule::create(['label' => 'Général', 'module' => 'prestation', 'percent' => 15]);
        CommissionRule::create(['label' => 'Course', 'module' => 'prestation', 'trade_id' => $course->id, 'percent' => 8]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $simulation = Livewire::actingAs($this->titulaire())
            ->test(CentreDesCommissions::class)
            ->set('simMetier', $course->id)
            ->set('simMontantEuros', 100)
            ->instance()->simulation;

        $this->assertSame('Course', $simulation['taux']->origine);
        $this->assertCount(2, $simulation['applicables']);
        $this->assertSame(800, $simulation['partage']['platform_fee_cents']);
        $this->assertSame(9200, $simulation['partage']['provider_payout_cents']);
    }

    /** LE CONSEILLER NE PARLE PAS DANS LE VIDE : sans données, il le dit. */
    public function test_le_conseiller_dit_quand_il_ne_sait_pas(): void
    {
        $conseils = Livewire::actingAs($this->titulaire())
            ->test(CentreDesCommissions::class)
            ->instance()->conseils;

        $this->assertNotEmpty($conseils);
        $this->assertStringContainsString('devinette', $conseils[0]['constat']);
    }

    private function titulaire(): User
    {
        return $this->prendreLeSiege(['role' => 'admin']);
    }
}
