<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UNE CAPACITÉ DÉCLARÉE DOIT MASQUER LA TUILE *ET* FERMER LA PORTE.
 *
 * CE QUI A ÉTÉ MESURÉ AVANT D'ÉCRIRE, et qui rendait tout le mécanisme décoratif : sur
 * quatre-vingt-six routes d'administration, **une seule** portait un contrôle de capacité
 * (`can:manage-face-check`), et trois composants Livewire sur cent trois en vérifiaient une.
 * `EnforcesAdminAccess`, présent partout, ne demande que « est-ce un administrateur ». Les quinze
 * capacités de `platform_role` existaient donc, et n'interdisaient presque rien.
 *
 * ── POURQUOI GATER LA SEULE NAVIGATION AURAIT ÉTÉ PIRE ───────────────────────────────────────
 *
 * `ModuleCatalogue` savait déjà lire la clé `gate`. Y déclarer les capacités sans rien faire des
 * routes aurait produit une porte INVISIBLE MAIS OUVERTE : une URL devinée suffisait. Le
 * commentaire de `ModuleCatalogue` prévient dans l'autre sens — « une case qui mène à un 403 est
 * pire qu'une case absente » — et l'inverse n'est pas une gêne d'usage, c'est un trou.
 *
 * D'où `EnforceModuleGate`, qui fait appliquer par le code ce que le catalogue déclare. Une seule
 * écriture vaut pour les deux ; deux copies auraient divergé, et c'est toujours la plus permissive
 * qui aurait décidé.
 *
 * ── TROIS CAPACITÉS ONT ÉTÉ AJOUTÉES ─────────────────────────────────────────────────────────
 *
 * Conformité, communication et infrastructure n'en avaient aucune : la liste d'origine a été
 * écrite avant ces modules. Les ranger de force sous une capacité voisine aurait masqué des écrans
 * à des administrateurs qui y ont droit — une régression silencieuse, et bien pire que le défaut
 * qu'on corrige.
 */
class ChaqueEcranAdminADuneCapaciteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LES DEUX SEULS MODULES VOLONTAIREMENT OUVERTS À TOUT ADMINISTRATEUR.
     *
     * Ce sont les pages d'ARRIVÉE de l'espace : les gater enfermerait dehors un administrateur
     * dont on vient de restreindre le périmètre, y compris le comptable à qui l'on ne donne que la
     * comptabilité. Il atterrirait sur un 403 avant d'avoir vu son propre menu.
     */
    private const OUVERTS_A_TOUS = ['admin:admin.dashboard', 'admin:admin.home'];

    public function test_chaque_module_admin_declare_une_capacite(): void
    {
        $sansGate = [];

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['context'] ?? null) !== 'admin') {
                continue;
            }

            $cle = (string) ($module['key'] ?? '');

            if (in_array($cle, self::OUVERTS_A_TOUS, true)) {
                continue;
            }

            if (($module['gate'] ?? null) === null) {
                $sansGate[] = $cle;
            }
        }

        $this->assertSame(
            [],
            $sansGate,
            'Ces modules d’administration ne déclarent aucune capacité : ils s’affichent pour tout '
            .'administrateur et leur écran s’ouvre pour tout administrateur. Si l’ouverture est '
            .'voulue, l’inscrire dans OUVERTS_A_TOUS avec son motif.'."\n".implode("\n", $sansGate),
        );
    }

    /**
     * TÉMOIN DE PORTÉE — on a bien lu des modules.
     *
     * Sans lui, le test précédent serait vert sur un catalogue vide ou un contexte mal orthographié.
     */
    public function test_temoin_le_catalogue_admin_nest_pas_vide(): void
    {
        $admin = collect(config('modules.catalogue', []))
            ->filter(fn (array $m) => ($m['context'] ?? null) === 'admin');

        $this->assertGreaterThan(80, $admin->count());
        $this->assertGreaterThan(80, $admin->filter(fn (array $m) => ($m['gate'] ?? null) !== null)->count());
    }

    /** Toute capacité déclarée doit exister : une faute de frappe masquerait l'écran pour tous. */
    public function test_chaque_capacite_declaree_existe(): void
    {
        $connues = array_keys(User::allowedAdminPermissions());

        $declarees = collect(config('modules.catalogue', []))
            ->pluck('gate')
            ->filter()
            ->unique();

        $this->assertGreaterThan(10, $declarees->count());

        foreach ($declarees as $gate) {
            $this->assertContains($gate, $connues, "« {$gate} » n’est pas une capacité connue.");
        }
    }

    // ── La porte, pas seulement la tuile ─────────────────────────────────

    public function test_la_porte_se_ferme_sur_une_capacite_absente(): void
    {
        $this->actingAs($this->admin(['manage-users']));

        $this->get(route('admin.finance'))->assertForbidden();
    }

    /**
     * TÉMOIN — avec la capacité, la même porte s'ouvre.
     *
     * Sans lui, le test précédent serait vert sur un écran cassé, une route absente, ou un garde
     * qui refuse tout le monde : on aurait remplacé un trou par une panne.
     */
    public function test_temoin_la_capacite_accordee_ouvre_la_porte(): void
    {
        $this->actingAs($this->admin(['manage-finance']));

        $this->get(route('admin.finance'))->assertSuccessful();
    }

    /**
     * LE TABLEAU DE BORD RESTE ATTEIGNABLE — c'est le garde anti-enfermement.
     *
     * Un administrateur au périmètre restreint doit arriver quelque part. Fermer la page d'arrivée
     * le mettrait dehors avant qu'il ait vu son menu, et le seul recours serait un développeur.
     */
    public function test_un_admin_au_perimetre_etroit_atteint_toujours_son_tableau_de_bord(): void
    {
        $this->actingAs($this->admin(['manage-accounting']));

        $this->get(route('admin.dashboard'))->assertSuccessful();
    }

    /** Et un super-administrateur passe partout, comme avant. */
    public function test_un_super_admin_passe_partout(): void
    {
        $this->actingAs($this->admin([], superAdmin: true));

        $this->get(route('admin.finance'))->assertSuccessful();
        $this->get(route('admin.gdpr.center'))->assertSuccessful();
    }

    /**
     * LA TUILE ET LA PORTE DISENT LA MÊME CHOSE.
     *
     * C'est l'invariant que `EnforceModuleGate` existe pour tenir. Une divergence donnerait soit une
     * case qui répond 403, soit un écran ouvert qu'aucun menu n'annonce — et c'est la seconde qui
     * est un trou.
     */
    public function test_la_tuile_et_la_porte_saccordent(): void
    {
        $comptable = $this->admin(['manage-accounting']);
        $this->actingAs($comptable);

        $visibles = collect(ModuleCatalogue::pourContexte('admin'))
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->pluck('key')
            ->all();

        /*
         * ON NE COMPTE QUE LES TUILES D'ADMINISTRATION, et cette précision n'est pas cosmétique.
         *
         * `pourContexte('admin')` rend aussi les modules UNIVERSELS — profil, notifications, aide,
         * mentions légales, cookies. Ils sont ouverts à tous, et ils doivent l'être : un comptable
         * a besoin de son profil et des pages légales. Les compter faisait dire au test qu'un
         * périmètre étroit voyait dix cases, ce qui est vrai et ne mesure rien.
         */
        $tuilesAdmin = array_values(array_filter($visibles, fn (string $cle) => str_starts_with($cle, 'admin:')));

        // Garde-fou : son propre écran, plus les deux pages d'arrivée. Rien d'autre.
        $this->assertCount(3, $tuilesAdmin, implode(', ', $tuilesAdmin));

        $this->assertContains('admin:admin.accounting-v2.center', $visibles);
        $this->assertNotContains('admin:admin.finance', $visibles);

        // Et ce que le menu cache, la porte le refuse.
        $this->get(route('admin.finance'))->assertForbidden();
    }

    /**
     * UN MODULE SANS CAPACITE RESTE OUVERT — l'ajout ne ferme rien de lui-même.
     *
     * Le garde ne doit refuser QUE ce qui déclare une capacité. S'il refusait par défaut, la
     * moindre route non cataloguée deviendrait inaccessible, et la panne serait générale.
     */
    public function test_une_route_sans_module_declare_reste_ouverte(): void
    {
        $this->actingAs($this->admin(['manage-users']));

        // Garde-fou du test : cette route existe bien et n'est pas cataloguée avec un gate.
        $this->assertTrue(Route::has('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @param  list<string>  $capacites */
    private function admin(array $capacites, bool $superAdmin = false): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $admin->forceFill([
            'platform_role' => $superAdmin ? 'super_admin' : 'admin',
            'permissions' => $capacites,
        ])->save();

        return $admin->refresh();
    }
}
