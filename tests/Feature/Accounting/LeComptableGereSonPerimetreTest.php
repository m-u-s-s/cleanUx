<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Admin\AccountingV2\AccountingCenter;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Models\Parametre;
use App\Models\User;
use App\Services\AccountingV2\ReglagesComptables;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** UN COMPTABLE DOIT POUVOIR TENIR SES LIVRES SANS DÉVELOPPEUR. */
class LeComptableGereSonPerimetreTest extends TestCase
{
    use RefreshDatabase;

    // ── La porte ─────────────────────────────────────────────────────────

    public function test_le_comptable_voit_la_tuile_comptabilite(): void
    {
        $this->actingAs($this->comptable());

        $this->assertTrue($this->voitLeModule('admin:admin.accounting-v2.center'));
    }

    /** TÉMOIN INVERSE — un administrateur d'exploitation ne la voit pas. */
    public function test_temoin_un_admin_sans_la_capacite_ne_voit_pas_la_tuile(): void
    {
        $this->actingAs($this->admin(['manage-finance']));

        $this->assertFalse($this->voitLeModule('admin:admin.accounting-v2.center'));
    }

    /** ET L'ÉCRAN LUI-MÊME EST FERMÉ — pas seulement la tuile. */
    public function test_lecran_refuse_un_admin_sans_la_capacite(): void
    {
        $this->actingAs($this->admin(['manage-finance']));

        Livewire::test(AccountingCenter::class)->assertForbidden();
    }

    public function test_le_comptable_ouvre_le_centre(): void
    {
        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)->assertOk();
    }

    // ── La fiscalité ─────────────────────────────────────────────────────

    public function test_le_comptable_enregistre_sa_position_de_tva(): void
    {
        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)
            ->set('tab', 'fiscalite')
            ->set('tvaFraisAnnulation', '6')
            ->set('modeleRevenu', 'agent')
            ->set('postageAutomatique', true)
            ->call('enregistrerLaFiscalite')
            ->assertHasNoErrors();

        $reglages = app(ReglagesComptables::class);

        $this->assertSame(6.0, $reglages->tvaDesFraisDAnnulation());
        $this->assertSame('agent', $reglages->modeleDeRevenu());
        $this->assertTrue($reglages->postageAutomatique());
    }

    /** ZÉRO ET VIDE SONT DEUX RÉPONSES, et les confondre ferait déclarer une TVA non due. */
    public function test_un_taux_a_zero_nest_pas_confondu_avec_un_champ_vide(): void
    {
        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)
            ->set('tab', 'fiscalite')
            ->set('tvaFraisAnnulation', '0')
            ->call('enregistrerLaFiscalite')
            ->assertHasNoErrors();

        $this->assertSame(0.0, app(ReglagesComptables::class)->tvaDesFraisDAnnulation());
    }

    /** Et le champ vidé rend bien la main au taux du pays. */
    public function test_le_champ_vide_rend_la_main_au_taux_du_pays(): void
    {
        Parametre::setValeur(ReglagesComptables::TVA_FRAIS_ANNULATION, '6');

        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)
            ->set('tab', 'fiscalite')
            ->set('tvaFraisAnnulation', '')
            ->call('enregistrerLaFiscalite')
            ->assertHasNoErrors();

        $this->assertNull(app(ReglagesComptables::class)->tvaDesFraisDAnnulation());
    }

    /** UN TAUX ABSURDE EST REFUSÉ — et le dire évite un journal faux. */
    public function test_un_taux_hors_bornes_est_refuse(): void
    {
        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)
            ->set('tab', 'fiscalite')
            ->set('tvaFraisAnnulation', '250')
            ->call('enregistrerLaFiscalite')
            ->assertHasErrors('tvaFraisAnnulation');

        $this->assertNull(app(ReglagesComptables::class)->tvaDesFraisDAnnulation());
    }

    /** TÉMOIN QUI COMPTE VRAIMENT — le réglage change ce qui est ÉCRIT. */
    public function test_le_reglage_pris_par_le_comptable_change_le_postage(): void
    {
        $this->assertFalse(
            app(ReglagesComptables::class)->postageAutomatique(),
            'Garde-fou du test : le postage doit partir coupé, sinon on ne mesure pas la bascule.',
        );

        $this->actingAs($this->comptable());

        Livewire::test(AccountingCenter::class)
            ->set('tab', 'fiscalite')
            ->set('postageAutomatique', true)
            ->call('enregistrerLaFiscalite');

        $this->assertTrue(
            app(ReglagesComptables::class)->postageAutomatique(),
            'L’écran range la valeur mais le module continue de lire la configuration : le '
            .'comptable croit avoir allumé le journal alors que rien ne s’y écrit.',
        );
    }

    // ── Les périodes ─────────────────────────────────────────────────────

    public function test_le_comptable_cloture_puis_rouvre_une_periode(): void
    {
        $this->actingAs($this->comptable());

        $composant = Livewire::test(AccountingCenter::class)
            ->call('closePeriod', 2026, 3);

        $periode = AccountingPeriod::query()->where('period_year', 2026)->where('period_month', 3)->first();

        $this->assertNotNull($periode, 'La clôture n’a créé aucune période : rien à rouvrir.');
        $this->assertTrue((bool) $periode->is_closed);

        $composant->call('reopenPeriod', $periode->id, 'Facture fournisseur reçue après la clôture.');

        $this->assertFalse((bool) $periode->refresh()->is_closed);
    }

    /** LA RÉOUVERTURE SANS MOTIF EST REFUSÉE. */
    public function test_une_reouverture_sans_motif_ne_passe_pas(): void
    {
        $this->actingAs($this->comptable());

        $composant = Livewire::test(AccountingCenter::class)->call('closePeriod', 2026, 4);
        $periode = AccountingPeriod::query()->where('period_year', 2026)->where('period_month', 4)->firstOrFail();

        $composant->call('reopenPeriod', $periode->id, '   ');

        $this->assertTrue(
            (bool) $periode->refresh()->is_closed,
            'Une période s’est rouverte sans qu’aucune raison ne soit consignée.',
        );
    }

    // ── Les exports ──────────────────────────────────────────────────────

    /** LE COMPTABLE GÉNÈRE PUIS TÉLÉCHARGE — et c'est le téléchargement qui compte. */
    public function test_le_comptable_telecharge_un_export_depuis_sa_session(): void
    {
        $comptable = $this->comptable();
        $this->actingAs($comptable);

        Livewire::test(AccountingCenter::class)
            ->set('exportYear', 2026)
            ->set('exportMonth', 5)
            ->set('exportFormat', 'csv')
            ->call('generateExport');

        $export = AccountingExport::query()->latest('id')->first();

        $this->assertNotNull($export, 'Aucun export produit : le téléchargement ne mesurerait rien.');

        $this->getJson("/api/admin/accounting-v2/exports/{$export->id}/download")
            ->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function modulesAdmin(): array
    {
        return ModuleCatalogue::pourContexte('admin')
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->all();
    }

    private function voitLeModule(string $cle): bool
    {
        foreach ($this->modulesAdmin() as $module) {
            if (($module['key'] ?? null) === $cle) {
                return true;
            }
        }

        return false;
    }

    /** Le compte qu'on remet au comptable : la comptabilité, et rien d'autre. */
    private function comptable(): User
    {
        return $this->admin(['manage-accounting']);
    }

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // La colonne est `permissions` ; `admin_permissions` n'existe pas, et y écrire laisserait
        // le compte sans aucune capacité — le test mesurerait alors un refus par absence.
        $admin->forceFill([
            'platform_role' => 'admin',
            'permissions' => $capacites,
        ])->save();

        return $admin->refresh();
    }
}
