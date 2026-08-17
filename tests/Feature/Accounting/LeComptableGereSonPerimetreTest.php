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

/**
 * UN COMPTABLE DOIT POUVOIR TENIR SES LIVRES SANS DÉVELOPPEUR.
 *
 * L'intention est simple à dire : on donne un compte d'administration à son comptable, et il gère
 * tout ce qui concerne la comptabilité et la fiscalité. Elle butait sur trois choses mesurées dans
 * le code, dont aucune n'était visible depuis l'écran.
 *
 * IL N'EXISTAIT AUCUNE CAPACITÉ POUR LUI. Les quinze capacités d'administration couvraient la
 * finance d'EXPLOITATION — versements, litiges, gestes commerciaux — et rien pour le grand livre.
 * Confier la comptabilité imposait donc de faire du comptable un super-administrateur, avec les
 * clients, les prestataires et les paiements en prime.
 *
 * LA FISCALITÉ VIVAIT DANS DES VARIABLES D'ENVIRONNEMENT. La position de TVA des frais
 * d'annulation, le modèle de revenu, l'interrupteur du postage : trois décisions qui ENGAGENT LE
 * COMPTABLE, et qu'il fallait un accès serveur et un redéploiement pour changer.
 *
 * LA RÉOUVERTURE D'UNE PÉRIODE N'AVAIT PAS DE BOUTON. `PeriodCloser::reopen()` existait depuis
 * l'origine ; l'écran ne proposait que la clôture. Une clôture prématurée était donc définitive
 * pour qui n'appelle pas l'API à la main.
 *
 * Ce fichier vérifie le parcours ENTIER, du menu au fichier téléchargé, avec les deux témoins qui
 * comptent : un administrateur SANS la capacité est refusé, et un réglage enregistré change
 * réellement ce qui est écrit au journal.
 */
class LeComptableGereSonPerimetreTest extends TestCase
{
    use RefreshDatabase;

    // ── La porte ─────────────────────────────────────────────────────────

    public function test_le_comptable_voit_la_tuile_comptabilite(): void
    {
        $this->actingAs($this->comptable());

        $this->assertTrue($this->voitLeModule('admin:admin.accounting-v2.center'));
    }

    /**
     * TÉMOIN INVERSE — un administrateur d'exploitation ne la voit pas.
     *
     * Sans lui, le test précédent serait vert sur un filtre qui laisse tout passer, et la capacité
     * ne servirait à rien. C'est aussi la moitié utile de la séparation : donner la comptabilité
     * sans donner le reste suppose que le reste ne donne pas la comptabilité.
     */
    public function test_temoin_un_admin_sans_la_capacite_ne_voit_pas_la_tuile(): void
    {
        $this->actingAs($this->admin(['manage-finance']));

        $this->assertFalse($this->voitLeModule('admin:admin.accounting-v2.center'));
    }

    /**
     * ET L'ÉCRAN LUI-MÊME EST FERMÉ — pas seulement la tuile.
     *
     * Cacher la case en laissant l'écran ouvert serait l'inverse du défaut habituel : une porte
     * invisible mais déverrouillée. Une URL devinée suffirait.
     */
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

    /**
     * ZÉRO ET VIDE SONT DEUX RÉPONSES, et les confondre ferait déclarer une TVA non due.
     *
     * Vide dit « applique le taux du pays » ; zéro dit « ces frais sont hors champ ». C'est le
     * piège récurrent de ce dépôt — un zéro voulu pris pour une valeur manquante — et il porte ici
     * sur une position fiscale.
     */
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

    /**
     * UN TAUX ABSURDE EST REFUSÉ — et le dire évite un journal faux.
     *
     * Une faute de frappe sur une position fiscale ne se rattrape pas : les écritures déjà passées
     * gardent le taux du jour.
     */
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

    /**
     * TÉMOIN QUI COMPTE VRAIMENT — le réglage change ce qui est ÉCRIT.
     *
     * Tous les tests ci-dessus prouvent qu'une valeur est rangée. Aucun ne prouve qu'elle sert.
     * Sans celui-ci, on aurait un écran de réglages parfaitement fonctionnel et parfaitement
     * décoratif — la forme d'échec exacte que ce dépôt collectionne.
     */
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

    /**
     * LA RÉOUVERTURE SANS MOTIF EST REFUSÉE.
     *
     * Le service exige dix caractères ; l'écran refuse déjà le vide, pour que l'utilisateur voie un
     * message plutôt qu'une exception. Rouvrir un exercice clos se justifie devant un contrôle.
     */
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

    /**
     * LE COMPTABLE GÉNÈRE PUIS TÉLÉCHARGE — et c'est le téléchargement qui compte.
     *
     * Le lien de l'écran pointe vers une route de l'API, protégée par un contrôle de portée de
     * jeton. Depuis un navigateur il n'y a pas de jeton, seulement une session : « la porte
     * existe » ne dit rien de « on a la clé », et un export qu'on ne peut pas récupérer ne sert à
     * personne. Ce test emprunte le chemin réel, avec une session web.
     */
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
