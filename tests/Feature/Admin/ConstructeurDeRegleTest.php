<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\ConstructeurDeRegle;
use App\Models\AutomationRule;
use App\Models\User;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CONSTRUCTEUR — création et modification d'une règle, sans ses conditions (tâche suivante).
 *
 * Le point qui compte : changer d'entité doit remettre à zéro le déclencheur et les actions qui
 * ne lui conviennent plus, sinon la règle enregistrée est silencieusement inerte (RuleRunner la
 * refuse ligne par ligne, le drain la filtre sur `entite` sans jamais la montrer à l'admin).
 */
class ConstructeurDeRegleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Action qui supporte UNIQUEMENT 'booking' — meme technique que CatalogueTest (tache 1) :
        // les deux actions reelles (journaliser, notifier.admins) supportent les trois entites,
        // donc rien ne prouverait un vrai filtre sans une action volontairement restreinte.
        app(ActionRegistre::class)->enregistrer(new class implements Action
        {
            public function cle(): string
            {
                return 'action-booking-only';
            }

            public function libelle(): string
            {
                return 'Action pour booking uniquement';
            }

            public function entitesSupportees(): array
            {
                return ['booking'];
            }

            public function champs(): array
            {
                return ['montant' => 'nombre'];
            }

            public function toucheAuDomaine(): bool
            {
                return false;
            }

            public function executer(Model $entite, array $parametres): ActionResult
            {
                return ActionResult::reussie();
            }
        });

        // Declencheur qui vise UNIQUEMENT 'booking' — les cinq declencheurs reels visent tous
        // 'alerte' : sans celui-ci, filtrer ou non donnerait le meme resultat pour 'booking'.
        app(DeclencheurRegistre::class)->enregistrer(new class implements Declencheur
        {
            public function cle(): string
            {
                return 'declencheur-booking-only';
            }

            public function evenement(): string
            {
                return \stdClass::class;
            }

            public function entite(): string
            {
                return 'booking';
            }

            public function sApplique(object $evenement): bool
            {
                return true;
            }

            public function identifiant(object $evenement): ?int
            {
                return 1;
            }

            public function libelle(): string
            {
                return 'Déclencheur pour booking uniquement';
            }
        });
    }

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    /**
     * LA GARDE DE COMPOSANT — sans `EnforcesAdminAccess`, n'importe quel compte authentifié
     * pourrait monter ce composant et appeler `enregistrer()` par /livewire/update : aucune route
     * n'intervient sur ce chemin, contrairement au chargement initial de la page.
     */
    public function test_un_non_administrateur_est_bloque_au_niveau_du_composant(): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test(ConstructeurDeRegle::class)->assertForbidden();
    }

    /** LE TEMOIN — une règle valide s'enregistre, avec tous ses champs, et naît en brouillon. */
    public function test_enregistrer_une_regle_valide_pose_tous_les_champs_et_nait_en_brouillon(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Paiements en échec')
            ->set('description', 'Notifie les admins')
            ->set('entite', 'alerte')
            ->set('declencheur', 'alerte.payment_capture_failed')
            ->set('actions', [['cle' => 'notifier.admins', 'parametres' => ['message' => 'Alerte']]])
            ->set('politiqueReprise', 'une_fois_par_jour')
            ->set('quotaParPassage', 25)
            ->set('plafondJournalier', 250)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle = AutomationRule::query()->where('nom', 'Paiements en échec')->firstOrFail();

        $this->assertSame('Notifie les admins', $regle->description);
        $this->assertSame('alerte', $regle->entite);
        $this->assertSame('alerte.payment_capture_failed', $regle->declencheur);
        $this->assertNull($regle->cadence);
        $this->assertSame([['cle' => 'notifier.admins', 'parametres' => ['message' => 'Alerte']]], $regle->actions);
        $this->assertSame('une_fois_par_jour', $regle->politique_reprise);
        $this->assertSame(25, $regle->quota_par_passage);
        $this->assertSame(250, $regle->plafond_journalier);
        $this->assertSame(AutomationRule::ETAT_BROUILLON, $regle->etat);
        $this->assertSame([], $regle->conditions);
    }

    /** Une règle sur cadence pose la cadence choisie, jamais un déclencheur d'événement. */
    public function test_une_regle_sur_cadence_enregistre_sa_cadence(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Balayage périodique')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('cadence', 'heure')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle = AutomationRule::query()->where('nom', 'Balayage périodique')->firstOrFail();

        $this->assertSame('cadence', $regle->declencheur);
        $this->assertSame('heure', $regle->cadence);
    }

    /** LA GARDE — sans cadence choisie, un déclencheur `cadence` est refusé. */
    public function test_la_cadence_est_requise_quand_le_declencheur_vaut_cadence(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Sans cadence')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('cadence', '')
            ->call('enregistrer')
            ->assertHasErrors('cadence');

        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Sans cadence']);
    }

    /** LA GARDE — le catalogue filtre par entité : proposer 'booking' à une règle 'alerte' la rendrait inerte. */
    public function test_l_entite_filtre_les_actions_et_les_declencheurs_proposes(): void
    {
        // Une ligne d'action doit exister pour que son <select> — et ses options filtrées —
        // apparaisse : sans elle, l'etat vide ne rend AUCUNE option, quelle que soit l'entite.
        $pourBooking = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('ajouterAction');

        $pourBooking->assertSee('action-booking-only', escape: false)
            ->assertSee('declencheur-booking-only', escape: false);

        $pourAlerte = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'alerte')
            ->call('ajouterAction');

        // TEMOIN DE LA GARDE : pour 'alerte', les options scopees a 'booking' n'apparaissent pas.
        $pourAlerte->assertDontSee('action-booking-only', escape: false)
            ->assertDontSee('declencheur-booking-only', escape: false);
    }

    /**
     * LE POINT QUI COMPTE — changer l'entité vide le déclencheur et les actions devenus
     * invalides. Sans ce nettoyage, la règle enregistrée est silencieusement inerte : le drain
     * la filtre sur `entite` (elle ne tourne jamais) et `RuleRunner::poser()` refuse toute action
     * que l'entité ne supporte pas (ligne `echouee`, jamais montrée à l'admin).
     */
    public function test_changer_d_entite_vide_le_declencheur_et_les_actions_devenus_invalides(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('declencheur', 'declencheur-booking-only')
            ->set('actions', [
                ['cle' => 'action-booking-only', 'parametres' => []],
                ['cle' => 'journaliser', 'parametres' => ['message' => 'vu']],
            ]);

        $composant->assertSet('declencheur', 'declencheur-booking-only')
            ->assertSet('actions', [
                ['cle' => 'action-booking-only', 'parametres' => []],
                ['cle' => 'journaliser', 'parametres' => ['message' => 'vu']],
            ]);

        // ENTITE CHANGEE — le declencheur booking-only et l'action booking-only ne conviennent
        // plus a 'alerte'. `journaliser` reste : il supporte les trois entites.
        $composant->set('entite', 'alerte');

        $composant->assertSet('declencheur', '')
            ->assertSet('actions', [
                ['cle' => 'journaliser', 'parametres' => ['message' => 'vu']],
            ]);
    }

    /** TEMOIN — un déclencheur `cadence` n'est PAS vidé par un changement d'entité : il n'est pas scopé. */
    public function test_changer_d_entite_ne_vide_pas_un_declencheur_cadence(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('cadence', 'jour')
            ->set('entite', 'mission');

        $composant->assertSet('declencheur', 'cadence')
            ->assertSet('cadence', 'jour');
    }

    /**
     * DEFENSE EN PROFONDEUR — un déclencheur ou une action incohérents avec l'entité soumise
     * sont refusés à l'enregistrement, pas seulement nettoyés côté client. Sans cette garde
     * serveur, un `$set` direct (hors formulaire) pourrait enregistrer la règle inerte que le
     * nettoyage de `updated()` est censé empêcher.
     */
    public function test_un_declencheur_incoherent_avec_l_entite_est_refuse_a_l_enregistrement(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Incohérente')
            ->set('entite', 'alerte')
            ->set('declencheur', 'declencheur-booking-only')
            ->call('enregistrer')
            ->assertHasErrors('declencheur');

        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Incohérente']);
    }

    public function test_une_action_incoherente_avec_l_entite_est_refusee_a_l_enregistrement(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Action incohérente')
            ->set('entite', 'alerte')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'action-booking-only', 'parametres' => []]])
            ->call('enregistrer')
            ->assertHasErrors('actions.0.cle');

        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Action incohérente']);
    }

    /** LES BORNES NUMERIQUES — un quota ou un plafond a zero (ou negatif) sont refuses. */
    public function test_les_bornes_numeriques_sont_validees(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Bornes')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('quotaParPassage', 0)
            ->set('plafondJournalier', -1)
            ->call('enregistrer')
            ->assertHasErrors(['quotaParPassage', 'plafondJournalier']);

        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Bornes']);
    }

    /** TEMOIN — des bornes dans la plage attendue passent, sans lui le test ci-dessus ne prouverait rien. */
    public function test_temoin_des_bornes_valides_sont_acceptees(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Bornes valides')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('quotaParPassage', 10)
            ->set('plafondJournalier', 100)
            ->call('enregistrer')
            ->assertHasNoErrors(['quotaParPassage', 'plafondJournalier']);

        $this->assertDatabaseHas('automation_rules', ['nom' => 'Bornes valides']);
    }

    /** Les paramètres déclarés par `champs()` de l'action choisie apparaissent au formulaire. */
    public function test_les_parametres_d_une_action_selectionnee_apparaissent_au_formulaire(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('actions', [['cle' => 'action-booking-only', 'parametres' => []]])
            ->assertSee('montant');
    }

    /** Modifier une règle existante précharge tous ses champs dans le constructeur. */
    public function test_modifier_une_regle_existante_precharge_ses_champs(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'Existante',
            'description' => 'Une description',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'jour',
            'conditions' => [],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
            'politique_reprise' => 'chaque_passage',
            'quota_par_passage' => 12,
            'plafond_journalier' => 120,
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->assertSet('nom', 'Existante')
            ->assertSet('description', 'Une description')
            ->assertSet('entite', 'booking')
            ->assertSet('declencheur', 'cadence')
            ->assertSet('cadence', 'jour')
            ->assertSet('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->assertSet('politiqueReprise', 'chaque_passage')
            ->assertSet('quotaParPassage', 12)
            ->assertSet('plafondJournalier', 120);
    }

    /**
     * Modifier une règle EXISTANTE met à jour ses champs sans jamais toucher à son état ni à
     * ses conditions — ce constructeur ne les possède pas (armement : liste ; conditions :
     * tâche suivante).
     */
    public function test_modifier_une_regle_existante_met_a_jour_sans_toucher_a_l_etat_ni_aux_conditions(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'À modifier',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'heure',
            'etat' => AutomationRule::ETAT_ARMEE,
            'conditions' => ['champ' => 'statut'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->set('nom', 'Renommée')
            ->set('quotaParPassage', 99)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle->refresh();

        $this->assertSame('Renommée', $regle->nom);
        $this->assertSame(99, $regle->quota_par_passage);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->etat, "L'état n'appartient pas à ce constructeur.");
        $this->assertSame(['champ' => 'statut'], $regle->conditions, 'Les conditions n\'appartiennent pas à ce constructeur.');
    }

    /**
     * LA GARDE `#[Locked]` — sans elle, le navigateur pourrait retourner `regleId` par `$set`
     * et faire `enregistrer()` mettre à jour une AUTRE règle que celle chargée au montage.
     */
    public function test_la_propriete_regle_id_est_verrouillee(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'Verrouillée',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'conditions' => [],
            'actions' => [],
        ]);
        $autre = AutomationRule::create([
            'nom' => 'Une autre',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'conditions' => [],
            'actions' => [],
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->set('regleId', $autre->id);
    }
}
