<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationActionSetting;
use App\Models\User;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\ReglagesDActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'absence de reglage vaut « a valider ». Jamais l'inverse — voir estAutonome() et tous(). */
class ReglagesDActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_action_sans_ligne_n_est_pas_autonome(): void
    {
        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('journaliser'));
    }

    /** TEMOIN — sans lui, le defaut du test precedent pourrait cacher une bascule qui ne fait rien. */
    public function test_une_action_basculee_vers_autonome_l_est(): void
    {
        $admin = User::factory()->create();

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('journaliser'));
    }

    public function test_la_bascule_ecrit_modifie_par_et_modifie_le(): void
    {
        $admin = User::factory()->create();

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $reglage = AutomationActionSetting::where('action_cle', 'journaliser')->firstOrFail();

        $this->assertSame($admin->id, $reglage->modifie_par);
        $this->assertNotNull($reglage->modifie_le);
    }

    public function test_la_bascule_est_journalisee(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $reglage = AutomationActionSetting::where('action_cle', 'journaliser')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.reglage_autonome',
            'user_id' => $admin->id,
            'target_type' => AutomationActionSetting::class,
            'target_id' => $reglage->id,
        ]);
    }

    public function test_rebasculer_vers_a_valider_fonctionne_et_se_journalise(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        $service = app(ReglagesDActions::class);

        $service->basculer('journaliser', true, $admin);
        $service->basculer('journaliser', false, $admin);

        $this->assertFalse($service->estAutonome('journaliser'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.reglage_a_valider']);
    }

    /** TEMOIN — une ligne normale s'insere sans probleme : le prochain test mesure un vrai refus. */
    public function test_temoin_une_ligne_par_cle_s_insere_normalement(): void
    {
        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => true]);

        $this->assertDatabaseCount('automation_action_settings', 1);
    }

    public function test_une_seconde_ligne_pour_la_meme_cle_est_refusee(): void
    {
        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => true]);

        $this->expectException(QueryException::class);

        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => false]);
    }

    /** LE GARDE-FOU — un reglage laisse par une action retiree du code ne doit pas ressusciter. */
    public function test_tous_ne_rend_que_les_cles_enregistrees_au_registre(): void
    {
        AutomationActionSetting::create(['action_cle' => 'notifier.admins', 'autonome' => true]);
        AutomationActionSetting::create(['action_cle' => 'action_retiree_du_code', 'autonome' => true]);

        $tous = app(ReglagesDActions::class)->tous();

        $this->assertTrue($tous['notifier.admins']);
        $this->assertFalse($tous['journaliser']);

        // TOUTE ACTION AJOUTEE AU CODE ARRIVE « A VALIDER » — nommees, pas deduites du registre :
        // comparer `tous()` a son propre registre reviendrait a comparer l'implementation a elle-meme.
        foreach (['mission.ping_client', 'mission.relancer_la_recherche'] as $neuve) {
            $this->assertArrayHasKey($neuve, $tous, $neuve);
            $this->assertFalse($tous[$neuve], $neuve);
        }

        $this->assertArrayNotHasKey('action_retiree_du_code', $tous);
    }

    /** Meme garde-fou, applique au point de lecture unitaire : la cle inconnue reste « a valider ». */
    public function test_est_autonome_ignore_un_reglage_orphelin_d_une_action_retiree(): void
    {
        AutomationActionSetting::create(['action_cle' => 'action_retiree_du_code', 'autonome' => true]);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action_retiree_du_code'));
    }

    /** LE DEFAUT DE LA COLONNE — l'invariant central : une ligne sans valeur explicite vaut « a valider ». */
    public function test_une_ligne_creee_sans_preciser_autonome_est_a_valider(): void
    {
        $reglage = AutomationActionSetting::create(['action_cle' => 'journaliser']);

        $this->assertFalse($reglage->fresh()->autonome);
    }

    /** TEMOIN — une ligne qui precise autonome=true se relit bien autonome, le defaut ne l'ecrase pas. */
    public function test_temoin_une_ligne_creee_avec_autonome_vrai_se_relit_autonome(): void
    {
        $reglage = AutomationActionSetting::create(['action_cle' => 'notifier.admins', 'autonome' => true]);

        $this->assertTrue($reglage->fresh()->autonome);
    }

    // ── Le reglage fige ce qu'il a confirme ──────────────────────────────

    /** Une action du registre, dont on choisit la nature — c'est elle qui peut changer. */
    private function enregistrerAction(string $cle, bool $toucheAuDomaine): void
    {
        app(ActionRegistre::class)->enregistrer(new class($cle, $toucheAuDomaine) implements Action
        {
            public function __construct(private readonly string $cle, private readonly bool $domaine) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function libelle(): string
            {
                return 'Action de mesure';
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
                return $this->domaine;
            }

            public function executer(Model $entite, array $parametres): ActionResult
            {
                return ActionResult::reussie();
            }
        });
    }

    /**
     * LE CONTREPOIDS 3, EN INVARIANT PLUTOT QU'EN FAIT DU CLIC. Un reglage accorde alors que
     * l'action ne touchait pas au domaine ne doit pas survivre au jour ou elle s'y met : personne
     * n'a jamais franchi la confirmation renforcee POUR CETTE NATURE-LA.
     */
    public function test_une_action_dont_la_nature_change_perd_son_autonomie(): void
    {
        $admin = User::factory()->create();
        $this->enregistrerAction('action.qui.change', toucheAuDomaine: false);
        app(ReglagesDActions::class)->basculer('action.qui.change', true, $admin);

        $this->enregistrerAction('action.qui.change', toucheAuDomaine: true);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.qui.change'));
        $this->assertFalse(app(ReglagesDActions::class)->tous()['action.qui.change']);
    }

    /** TEMOIN — nature inchangee, l'autonomie tient : la garde ne coupe pas tout par principe. */
    public function test_temoin_une_action_dont_la_nature_ne_change_pas_garde_son_autonomie(): void
    {
        $admin = User::factory()->create();
        $this->enregistrerAction('action.stable', toucheAuDomaine: true);
        app(ReglagesDActions::class)->basculer('action.stable', true, $admin);

        $this->enregistrerAction('action.stable', toucheAuDomaine: true);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.stable'));
        $this->assertTrue(app(ReglagesDActions::class)->tous()['action.stable']);
    }

    /**
     * LE SECOND CHEMIN : les gardes MASQUENT un reglage orphelin, elles ne le suppriment pas.
     * Une cle retiree du registre puis reintroduite avec une AUTRE nature ne doit pas
     * ressusciter l'autonomie accordee a l'ancienne.
     */
    public function test_une_cle_reintroduite_avec_une_autre_nature_ne_ressuscite_pas_son_autonomie(): void
    {
        $admin = User::factory()->create();
        $this->enregistrerAction('action.ressuscitee', toucheAuDomaine: false);
        app(ReglagesDActions::class)->basculer('action.ressuscitee', true, $admin);

        // Le retrait du code : un registre neuf, sans la cle. La ligne, elle, reste en base.
        app()->instance(ActionRegistre::class, new ActionRegistre);
        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.ressuscitee'));

        $this->enregistrerAction('action.ressuscitee', toucheAuDomaine: true);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action.ressuscitee'));
        $this->assertDatabaseHas('automation_action_settings', [
            'action_cle' => 'action.ressuscitee',
            'autonome' => true,
        ]);
    }

    /** TEMOIN — la meme cle reintroduite avec la MEME nature retrouve bien son autonomie. */
    public function test_temoin_une_cle_reintroduite_avec_la_meme_nature_retrouve_son_autonomie(): void
    {
        $admin = User::factory()->create();
        $this->enregistrerAction('action.revenue', toucheAuDomaine: true);
        app(ReglagesDActions::class)->basculer('action.revenue', true, $admin);

        app()->instance(ActionRegistre::class, new ActionRegistre);
        $this->enregistrerAction('action.revenue', toucheAuDomaine: true);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('action.revenue'));
    }

    /** La bascule fige la nature du moment : c'est elle que la relecture compare. */
    public function test_la_bascule_fige_la_nature_de_l_action_au_moment_du_reglage(): void
    {
        $admin = User::factory()->create();
        $this->enregistrerAction('action.figee', toucheAuDomaine: true);

        app(ReglagesDActions::class)->basculer('action.figee', true, $admin);

        $this->assertDatabaseHas('automation_action_settings', [
            'action_cle' => 'action.figee',
            'domaine_au_moment_du_reglage' => true,
        ]);
    }
}
