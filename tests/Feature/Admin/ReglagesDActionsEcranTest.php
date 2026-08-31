<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\ReglagesDActionsEcran;
use App\Models\User;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\ReglagesDActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ECRAN DES REGLAGES D'ACTIONS — le seul endroit du produit ou un administrateur donne au
 * moteur le droit d'agir seul. `toucheAuDomaine()` ne decide PAS de l'autonomie : il decide si
 * la bascule VERS l'autonomie exige une confirmation renforcee avant de prendre effet.
 */
class ReglagesDActionsEcranTest extends TestCase
{
    use RefreshDatabase;

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    /** Le meme administrateur, MOINS la capacite du module — le seul ecart mesure. */
    private function adminSansLaPermission(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => [],
        ]);
    }

    /** Une action qui ECRIT dans le domaine — sa bascule vers l'autonomie exige confirmation. */
    private function enregistrerActionDeDomaine(string $cle = 'action.de.domaine'): Action
    {
        $action = new class($cle) implements Action
        {
            public function __construct(private readonly string $cle) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function libelle(): string
            {
                return 'Action qui touche au domaine';
            }

            public function entitesSupportees(): array
            {
                return ['booking'];
            }

            public function champs(): array
            {
                return [];
            }

            public function toucheAuDomaine(): bool
            {
                return true;
            }

            public function executer(Model $entite, array $parametres): ActionResult
            {
                return ActionResult::reussie();
            }
        };

        app(ActionRegistre::class)->enregistrer($action);

        return $action;
    }

    /** TEMOIN DE FORME — meme contrat, `toucheAuDomaine()` rend false : bascule directe attendue. */
    private function enregistrerActionSansDomaine(string $cle = 'action.sans.domaine'): Action
    {
        $action = new class($cle) implements Action
        {
            public function __construct(private readonly string $cle) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function libelle(): string
            {
                return 'Action qui ne touche pas au domaine';
            }

            public function entitesSupportees(): array
            {
                return ['booking'];
            }

            public function champs(): array
            {
                return [];
            }

            public function toucheAuDomaine(): bool
            {
                return false;
            }

            public function executer(Model $entite, array $parametres): ActionResult
            {
                return ActionResult::reussie();
            }
        };

        app(ActionRegistre::class)->enregistrer($action);

        return $action;
    }

    // ── L'ecran est joignable ─────────────────────────────────────────────

    public function test_la_route_sert_le_composant_et_non_le_gabarit_de_repli(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation.reglages'))
            ->assertOk()
            ->assertSeeLivewire(ReglagesDActionsEcran::class);
    }

    /** LA LISTE MENE A L'ECRAN : une route qu'aucun ecran ne lie reste injoignable. */
    public function test_la_liste_porte_le_lien_vers_l_ecran_des_reglages(): void
    {
        $html = $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('admin.automation.reglages', absolute: false), $html);
    }

    public function test_un_non_administrateur_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.automation.reglages'))
            ->assertForbidden();
    }

    public function test_un_administrateur_sans_la_permission_du_module_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs($this->adminSansLaPermission())
            ->get(route('admin.automation.reglages'))
            ->assertForbidden();
    }

    // ── L'ecran montre toutes les actions du registre ────────────────────

    public function test_l_ecran_montre_toutes_les_actions_du_registre(): void
    {
        $action = $this->enregistrerActionDeDomaine('action.affichee');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->assertSee($action->libelle())
            ->assertSee('journaliser')
            ->assertSee('notifier.admins');
    }

    // ── Basculer marche dans les deux sens ────────────────────────────────

    public function test_basculer_vers_autonome_puis_vers_a_valider_fonctionne_dans_les_deux_sens(): void
    {
        $this->enregistrerActionSansDomaine('action.reversible');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.reversible', true);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.reversible'));

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.reversible', false);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.reversible'));
    }

    /** UNE CLE INCONNUE DU REGISTRE EST REFUSEE — jamais une confirmation ouverte sur du vide. */
    public function test_basculer_une_action_inconnue_du_registre_est_refusee(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.qui.n.existe.pas', true)
            ->assertNotFound();
    }

    // ── La confirmation renforcee ──────────────────────────────────────────

    /**
     * LE CONTREPOIDS DE L'ECRAN — une action qui touche au domaine n'atteint PAS l'autonomie
     * par le seul appel de `basculer()` : la confirmation renforcee doit encore avoir lieu.
     */
    public function test_une_action_qui_touche_au_domaine_exige_la_confirmation_renforcee(): void
    {
        $this->enregistrerActionDeDomaine('action.domaine.non.confirmee');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.domaine.non.confirmee', true)
            ->assertSet('actionEnConfirmation', 'action.domaine.non.confirmee');

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.domaine.non.confirmee'));
    }

    /** LA CONFIRMATION ACHEVE CE QUE `basculer()` A OUVERT — l'autonomie prend effet ensuite. */
    public function test_confirmer_apres_la_confirmation_renforcee_rend_l_action_autonome(): void
    {
        $this->enregistrerActionDeDomaine('action.domaine.confirmee');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.domaine.confirmee', true)
            ->call('confirmerAutonomie')
            ->assertSet('actionEnConfirmation', null);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.domaine.confirmee'));
    }

    /** ANNULER LA CONFIRMATION NE BASCULE RIEN — l'action reste a valider. */
    public function test_annuler_la_confirmation_ne_bascule_pas(): void
    {
        $this->enregistrerActionDeDomaine('action.domaine.annulee');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.domaine.annulee', true)
            ->call('annulerConfirmation')
            ->assertSet('actionEnConfirmation', null);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.domaine.annulee'));
    }

    /**
     * UNE BASCULE DIRECTE REFERME UNE CONFIRMATION RESTEE OUVERTE SUR UNE AUTRE ACTION — sans
     * quoi le panneau affiche encore « confirmer l'autonomie de A » alors que B vient d'etre
     * basculee, et une confirmation tardive armerait la mauvaise action.
     */
    public function test_une_bascule_directe_referme_une_confirmation_ouverte_sur_une_autre_action(): void
    {
        $this->enregistrerActionDeDomaine('action.domaine.en.attente');
        $this->enregistrerActionSansDomaine('action.sans.domaine.autre');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.domaine.en.attente', true)
            ->assertSet('actionEnConfirmation', 'action.domaine.en.attente')
            ->call('basculer', 'action.sans.domaine.autre', true)
            ->assertSet('actionEnConfirmation', null);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.domaine.en.attente'));
        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.sans.domaine.autre'));
    }

    /**
     * CONFIRMER SANS RIEN EN ATTENTE NE POSE RIEN, ET NE LEVE RIEN — `assertOk()` est la partie
     * qui manquait : sans elle, l'etat (deja null avant l'appel) et le compte (deja a zero avant
     * l'appel) restent identiques que la garde existe ou non, et ne prouvent donc rien seuls.
     */
    public function test_confirmer_sans_confirmation_en_attente_ne_pose_aucun_reglage(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('confirmerAutonomie')
            ->assertOk()
            ->assertSet('actionEnConfirmation', null);

        $this->assertDatabaseCount('automation_action_settings', 0);
    }

    /**
     * L'ACTION A PU DISPARAITRE DU REGISTRE ENTRE LES DEUX APPELS (deploiement en cours) — le
     * registre est un singleton du conteneur, le remplacer simule exactement ce retrait. Le
     * meme controle explicite que « rien en attente » s'applique : ni exception, ni reglage pose
     * pour une cle qui n'existe plus. `assertDatabaseMissing` est la partie qui manquait :
     * `estAutonome()` rend deja `false` pour une cle hors registre MEME s'il existe une ligne en
     * base pour elle — sans ce controle direct, une ligne fantome resterait invisible au test.
     */
    public function test_confirmer_une_action_retiree_du_registre_entre_les_deux_appels_ne_pose_rien(): void
    {
        $this->enregistrerActionDeDomaine('action.qui.disparait');

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.qui.disparait', true)
            ->assertSet('actionEnConfirmation', 'action.qui.disparait');

        // Simule le retrait : un registre neuf, sans l'action en attente de confirmation.
        app()->instance(ActionRegistre::class, new ActionRegistre);

        $composant->call('confirmerAutonomie')
            ->assertOk()
            ->assertSet('actionEnConfirmation', null);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.qui.disparait'));
        $this->assertDatabaseMissing('automation_action_settings', ['action_cle' => 'action.qui.disparait']);
    }

    /**
     * TEMOIN — meme forme, `toucheAuDomaine()` rend false : la bascule prend effet par le SEUL
     * appel de `basculer()`. Sans ce temoin, les trois tests precedents pourraient mesurer un
     * `basculer()` qui ne bascule jamais rien, pas une garde de confirmation.
     */
    public function test_temoin_une_action_qui_ne_touche_pas_au_domaine_bascule_directement(): void
    {
        $this->enregistrerActionSansDomaine('action.sans.domaine.temoin');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.sans.domaine.temoin', true)
            ->assertSet('actionEnConfirmation', null);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.sans.domaine.temoin'));
    }

    /**
     * REPASSER A VALIDER NE DEMANDE JAMAIS DE CONFIRMATION, meme pour une action de domaine :
     * seule la bascule VERS l'autonomie est visee par la garde.
     */
    public function test_repasser_a_valider_une_action_de_domaine_deja_autonome_ne_demande_pas_de_confirmation(): void
    {
        $admin = $this->adminGlobal();
        $this->enregistrerActionDeDomaine('action.domaine.desarmee');
        app(ReglagesDActions::class)->basculer('action.domaine.desarmee', true, $admin);

        Livewire::actingAs($admin)
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.domaine.desarmee', false)
            ->assertSet('actionEnConfirmation', null);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.domaine.desarmee'));
    }

    // ── La garde `#[Locked]` ────────────────────────────────────────────

    public function test_la_propriete_action_en_confirmation_est_verrouillee(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->set('actionEnConfirmation', 'action.quelconque');
    }

    // ── La capacite garde AUSSI le chemin d'action Livewire ──────────────

    /**
     * `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : sans capacite verifiee sur le
     * composant, un administrateur sans `manage-automation` basculerait un reglage en appelant
     * sa methode directement.
     */
    public function test_sans_la_permission_l_ecran_refuse_le_montage_et_la_bascule(): void
    {
        $this->enregistrerActionSansDomaine('action.gardee');

        Livewire::actingAs($this->adminSansLaPermission())
            ->test(ReglagesDActionsEcran::class)
            ->assertForbidden();

        // L'INSTANTANE EST VALIDE, ET IL NE SUFFIT PAS : monte par un administrateur habilite,
        // il est ensuite rejoue par un autre qui ne l'est pas — exactement `/livewire/update`.
        $composant = Livewire::actingAs($this->adminGlobal())->test(ReglagesDActionsEcran::class);

        Livewire::actingAs($this->adminSansLaPermission());

        $composant->call('basculer', 'action.gardee', true)->assertForbidden();

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.gardee'));
    }

    /** TEMOIN — avec la capacite, le meme appel aboutit. */
    public function test_temoin_avec_la_permission_l_ecran_bascule(): void
    {
        $this->enregistrerActionSansDomaine('action.autorisee');

        Livewire::actingAs($this->adminGlobal())
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'action.autorisee', true);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.autorisee'));
    }
}
