<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\ConstructeurDeRegle;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Services\Automation\Descripteurs\BookingDescriptor;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ARBRE DE CONDITIONS — le constructeur visuel ajouté par la tâche 6. La forme réelle, mesurée
 * dans `RuleTreeEvaluator::apply()` : une feuille est {field, op, value}, un composite est
 * {and: [...]}, {or: [...]} ou {not: {...}} — jamais `all`/`any`.
 */
class ArbreDeConditionsTest extends TestCase
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

    /**
     * `assertHasErrors(['champ' => $message])` coupe au premier `:` pour comparer un NOM DE
     * REGLE, pas un message : nos messages en contiennent un, d'où ce contournement direct.
     */
    private function assertErreurConditionsExacte(Testable $composant, string $message): void
    {
        $composant->assertHasErrors('conditions');
        $this->assertSame([$message], $composant->errors()->get('conditions'));
    }

    /** @return array<string, mixed> */
    private function arbreDeProfondeur(int $profondeur): array
    {
        $noeud = ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'];

        for ($i = 1; $i < $profondeur; $i++) {
            $noeud = ['and' => [$noeud]];
        }

        return $noeud;
    }

    /** LE POINT QUI COMPTE — l'arbre construit via les actions de l'écran produit exactement la forme {and, or}. */
    public function test_construire_un_arbre_et_ou_via_lecran_produit_exactement_le_json_attendu(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'and');

        $composant->assertSet('conditions', ['and' => []]);

        $composant->call('ajouterEnfant', 'and', 'feuille')
            ->set('conditions.and.0.field', 'statut')
            ->set('conditions.and.0.op', 'eq')
            ->set('conditions.and.0.value', 'confirme')
            ->call('ajouterEnfant', 'and', 'or')
            ->call('ajouterEnfant', 'and.1.or', 'feuille')
            ->set('conditions.and.1.or.0.field', 'ville')
            ->set('conditions.and.1.or.0.op', 'eq')
            ->set('conditions.and.1.or.0.value', 'Lyon')
            ->call('ajouterEnfant', 'and.1.or', 'feuille')
            ->set('conditions.and.1.or.1.field', 'ville')
            ->set('conditions.and.1.or.1.op', 'eq')
            ->set('conditions.and.1.or.1.value', 'Paris');

        $attendu = [
            'and' => [
                ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'],
                ['or' => [
                    ['field' => 'ville', 'op' => 'eq', 'value' => 'Lyon'],
                    ['field' => 'ville', 'op' => 'eq', 'value' => 'Paris'],
                ]],
            ],
        ];

        $composant->assertSet('conditions', $attendu);
    }

    /** Retirer un nœud le supprime de sa liste ET réindexe — pas un trou laissé à la place. */
    public function test_retirer_un_noeud_le_supprime_et_reindexe_la_liste(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'or')
            ->call('ajouterEnfant', 'or', 'feuille')
            ->set('conditions.or.0.field', 'statut')->set('conditions.or.0.op', 'eq')->set('conditions.or.0.value', 'a')
            ->call('ajouterEnfant', 'or', 'feuille')
            ->set('conditions.or.1.field', 'statut')->set('conditions.or.1.op', 'eq')->set('conditions.or.1.value', 'b')
            ->call('ajouterEnfant', 'or', 'feuille')
            ->set('conditions.or.2.field', 'statut')->set('conditions.or.2.op', 'eq')->set('conditions.or.2.value', 'c');

        $composant->call('retirerNoeud', 'or.1');

        $composant->assertSet('conditions', ['or' => [
            ['field' => 'statut', 'op' => 'eq', 'value' => 'a'],
            ['field' => 'statut', 'op' => 'eq', 'value' => 'c'],
        ]]);
    }

    /** `not` enveloppe un SEUL noeud enfant — pas une liste, contrairement à `and`/`or`. */
    public function test_un_groupe_not_produit_bien_la_forme_not(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'not')
            ->call('definirNoeud', 'not', 'feuille')
            ->set('conditions.not.field', 'statut')
            ->set('conditions.not.op', 'eq')
            ->set('conditions.not.value', 'refuse');

        $composant->assertSet('conditions', ['not' => ['field' => 'statut', 'op' => 'eq', 'value' => 'refuse']]);
    }

    /** Retirer le noeud racine vide tout l'arbre, quel que soit son type. */
    public function test_retirer_la_racine_vide_entierement_larbre(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'feuille')
            ->set('conditions.field', 'statut')->set('conditions.op', 'eq')->set('conditions.value', 'x');

        $composant->call('retirerNoeud', '');

        $composant->assertSet('conditions', []);
    }

    /**
     * LE TÉMOIN — un arbre valide construit à l'écran s'enregistre ET sélectionne bien les bonnes
     * entités une fois repassé à `RuleTreeEvaluator`. Un arbre qui « a l'air bon » mais ne
     * sélectionne rien est le défaut silencieux que ce lot combat.
     */
    public function test_le_temoin_un_arbre_valide_construit_a_lecran_selectionne_les_bonnes_entites(): void
    {
        // La factory ecrase 'ville' depuis le code postal APRES creation (afterCreating) : fixer
        // les deux via un update() a part, une fois la ligne deja en base.
        $confirmeLyon = Booking::factory()->create();
        $confirmeLyon->forceFill(['status' => 'confirme', 'ville' => 'Lyon'])->save();
        $confirmeParis = Booking::factory()->create();
        $confirmeParis->forceFill(['status' => 'confirme', 'ville' => 'Paris'])->save();
        $confirmeMarseille = Booking::factory()->create();
        $confirmeMarseille->forceFill(['status' => 'confirme', 'ville' => 'Marseille'])->save();
        $refuseLyon = Booking::factory()->create();
        $refuseLyon->forceFill(['status' => 'refuse', 'ville' => 'Lyon'])->save();

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Confirmees a Lyon ou Paris')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->call('definirNoeud', '', 'and')
            ->call('ajouterEnfant', 'and', 'feuille')
            ->set('conditions.and.0.field', 'statut')
            ->set('conditions.and.0.op', 'eq')
            ->set('conditions.and.0.value', 'confirme')
            ->call('ajouterEnfant', 'and', 'or')
            ->call('ajouterEnfant', 'and.1.or', 'feuille')
            ->set('conditions.and.1.or.0.field', 'ville')
            ->set('conditions.and.1.or.0.op', 'eq')
            ->set('conditions.and.1.or.0.value', 'Lyon')
            ->call('ajouterEnfant', 'and.1.or', 'feuille')
            ->set('conditions.and.1.or.1.field', 'ville')
            ->set('conditions.and.1.or.1.op', 'eq')
            ->set('conditions.and.1.or.1.value', 'Paris');

        $composant->call('enregistrer')->assertHasNoErrors();

        $regle = AutomationRule::query()->where('nom', 'Confirmees a Lyon ou Paris')->firstOrFail();

        $requete = Booking::query();
        app(RuleTreeEvaluator::class)->apply($requete, $regle->conditions, app(BookingDescriptor::class));
        $ids = $requete->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$confirmeLyon->id, $confirmeParis->id], $ids);
        $this->assertNotContains($confirmeMarseille->id, $ids, 'Une ville hors liste ne doit pas correspondre.');
        $this->assertNotContains($refuseLyon->id, $ids, 'Un statut refuse est exclu par le ET.');
    }

    /** LA GARDE — un champ hors du catalogue de l'entité choisie est refusé, pas silencieusement ignoré. */
    public function test_un_champ_hors_de_lentite_est_refuse_a_lenregistrement(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Champ etranger')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', ['field' => 'niveau', 'op' => 'eq', 'value' => 'critique'])
            ->call('enregistrer');

        $this->assertErreurConditionsExacte($composant, "racine : champ inconnu 'niveau'.");
        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Champ etranger']);
    }

    /** LA GARDE — un opérateur hors de `RuleTreeEvaluator::OPERATEURS_CONNUS` est refusé. */
    public function test_un_operateur_hors_de_la_liste_est_refuse_a_lenregistrement(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Operateur etranger')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', ['field' => 'statut', 'op' => 'regex', 'value' => 'x'])
            ->call('enregistrer');

        $this->assertErreurConditionsExacte($composant, "racine : operateur inconnu 'regex'.");
        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Operateur etranger']);
    }

    /** LA BORNE — au-delà de `PROFONDEUR_MAX`, l'admin lit le message avant l'enregistrement, pas après. */
    public function test_un_arbre_trop_profond_est_refuse_avec_son_message(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Trop profond')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', $this->arbreDeProfondeur(RuleTreeEvaluator::PROFONDEUR_MAX + 1))
            ->call('enregistrer');

        $this->assertErreurConditionsExacte($composant, 'Arbre trop profond : '.RuleTreeEvaluator::PROFONDEUR_MAX.' niveaux au plus.');
        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Trop profond']);
    }

    /** TÉMOIN — la profondeur EXACTE de la borne passe, sinon la borne serait décalée d'un cran. */
    public function test_temoin_un_arbre_a_la_profondeur_maximale_est_accepte(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Profondeur maximale')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', $this->arbreDeProfondeur(RuleTreeEvaluator::PROFONDEUR_MAX))
            ->call('enregistrer')
            ->assertHasNoErrors('conditions');

        $this->assertDatabaseHas('automation_rules', ['nom' => 'Profondeur maximale']);
    }

    /** LA BORNE — au-delà de `NOEUDS_MAX`, même refus lisible avant l'enregistrement. */
    public function test_un_arbre_trop_large_est_refuse_avec_son_message(): void
    {
        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX, ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']);

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Trop large')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', ['and' => $feuilles])
            ->call('enregistrer');

        $this->assertErreurConditionsExacte($composant, 'Arbre trop large : '.RuleTreeEvaluator::NOEUDS_MAX.' noeuds au plus.');
        $this->assertDatabaseMissing('automation_rules', ['nom' => 'Trop large']);
    }

    /** TÉMOIN — la taille EXACTE de la borne passe (1 racine + (MAX-1) feuilles = MAX nœuds). */
    public function test_temoin_un_arbre_de_taille_maximale_est_accepte(): void
    {
        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX - 1, ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Taille maximale')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditions', ['and' => $feuilles])
            ->call('enregistrer')
            ->assertHasNoErrors('conditions');

        $this->assertDatabaseHas('automation_rules', ['nom' => 'Taille maximale']);
    }

    /** Les deux bornes se lisent à l'écran, avant tout enregistrement — pas seulement au refus. */
    public function test_les_bornes_sont_visibles_a_lecran_avant_denregistrer(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->assertSee(RuleTreeEvaluator::PROFONDEUR_MAX.' niveaux')
            ->assertSee(RuleTreeEvaluator::NOEUDS_MAX.' noeuds');
    }

    /** LA GARDE — le champ d'une condition ne propose que le catalogue de l'entité choisie. */
    public function test_le_champ_dune_condition_ne_propose_que_les_champs_de_lentite(): void
    {
        $pourBooking = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'feuille');

        // `value="..."` cible l'OPTION, pas le texte libre : "niveaux" (les bornes) contient
        // "niveau" en sous-chaine et ferait tomber `assertDontSee('niveau')` a tort.
        $pourBooking->assertSee('value="ville"', escape: false)->assertDontSee('value="niveau"', escape: false);

        $pourAlerte = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'alerte')
            ->call('definirNoeud', '', 'feuille');

        $pourAlerte->assertSee('value="niveau"', escape: false)->assertDontSee('value="ville"', escape: false);
    }

    /**
     * LE POINT QUI COMPTE — changer d'entité vide les conditions déjà construites : un champ de
     * l'ancienne entité n'a aucun sens pour la nouvelle, exactement comme le déclencheur et les
     * actions (updated()) le font déjà.
     */
    public function test_changer_dentite_vide_les_conditions_deja_construites(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->call('definirNoeud', '', 'feuille')
            ->set('conditions.field', 'ville')
            ->set('conditions.op', 'eq')
            ->set('conditions.value', 'Lyon');

        $composant->assertSet('conditions', ['field' => 'ville', 'op' => 'eq', 'value' => 'Lyon']);

        $composant->set('entite', 'alerte');

        $composant->assertSet('conditions', []);
    }

    /** TÉMOIN — changer de DÉCLENCHEUR (sans changer d'entité) ne vide pas les conditions : elles n'en dépendent pas. */
    public function test_changer_de_declencheur_ne_vide_pas_les_conditions_deja_construites(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'alerte')
            ->set('declencheur', 'cadence')
            ->call('definirNoeud', '', 'feuille')
            ->set('conditions.field', 'niveau')
            ->set('conditions.op', 'eq')
            ->set('conditions.value', 'critique');

        $composant->set('declencheur', 'alerte.payment_capture_failed');

        $composant->assertSet('conditions', ['field' => 'niveau', 'op' => 'eq', 'value' => 'critique']);
    }

    /**
     * LE POINT QUI COMPTE — les conditions changent ce que la règle FAIT autant que l'entité, le
     * déclencheur ou les actions : les modifier rétrograde donc une règle armée en observation.
     */
    public function test_modifier_les_conditions_dune_regle_armee_la_retrograde_en_observation(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'Armee conditions',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'heure',
            'etat' => AutomationRule::ETAT_ARMEE,
            'conditions' => [],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->set('conditions', ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle->refresh();

        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->etat);
        $this->assertSame(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'], $regle->conditions);
    }

    /** TÉMOIN — renommer une règle armée SANS toucher ses conditions valides ne la rétrograde pas. */
    public function test_renommer_une_regle_armee_sans_toucher_les_conditions_ne_la_retrograde_pas(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'Armee stable',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'heure',
            'etat' => AutomationRule::ETAT_ARMEE,
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->set('nom', 'Armee renommee')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle->refresh();

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->etat, 'Des conditions inchangees ne retrogradent pas la regle.');
        $this->assertSame(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'], $regle->conditions);
    }
}
