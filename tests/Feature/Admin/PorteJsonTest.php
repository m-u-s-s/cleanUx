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
 * LA PORTE JSON — tâche 7. Un expert colle un arbre à la main : `ValidateurDArbre` fait autorité,
 * et un arbre bon remplit le MÊME `$conditions` que le constructeur visuel (pas un second format).
 */
class PorteJsonTest extends TestCase
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

    /** Même contournement que `ArbreDeConditionsTest` : nos messages contiennent un `:`. */
    private function assertErreurJsonExacte(Testable $composant, string $message): void
    {
        $composant->assertHasErrors('conditionsJson');
        $this->assertSame([$message], $composant->errors()->get('conditionsJson'));
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

    public function test_un_json_malforme_est_refuse_avec_son_message(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', '{"field": "statut", "op": }')
            ->call('appliquerJson');

        $composant->assertHasErrors('conditionsJson');
        $messages = $composant->errors()->get('conditionsJson');
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith('JSON invalide : ', $messages[0]);
        $composant->assertSet('conditions', []);
    }

    /** Un scalaire JSON (nombre, chaîne, null) n'est pas un arbre — refusé avant même `ValidateurDArbre`. */
    public function test_un_json_qui_nest_pas_un_objet_est_refuse(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', '42')
            ->call('appliquerJson');

        $this->assertErreurJsonExacte($composant, 'Un arbre de conditions doit être {field, op, value} ou {and|or|not: ...}.');
        $composant->assertSet('conditions', []);
    }

    /** LA GARDE — sans entité choisie, la porte refuse proprement au lieu de planter sur un descripteur nul. */
    public function test_sans_entite_choisie_la_porte_refuse_sans_planter(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('conditionsJson', json_encode(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']))
            ->call('appliquerJson');

        $this->assertErreurJsonExacte(
            $composant,
            "Choisissez d'abord une entité : les conditions se lisent contre ses champs."
        );
        $composant->assertSet('conditions', []);
    }

    /**
     * DEUX CAUSES, DEUX MESSAGES.
     *
     * `entite` est une propriete publique : le navigateur peut y poser n'importe quoi. « Choisissez
     * d'abord une entite » enverrait alors l'administrateur choisir ce qu'il a deja choisi.
     */
    public function test_une_entite_inconnue_le_dit_au_lieu_d_en_reclamer_une(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'entite_qui_n_existe_pas')
            ->set('conditionsJson', json_encode(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']))
            ->call('appliquerJson');

        $this->assertErreurJsonExacte(
            $composant,
            'Entité inconnue : « entite_qui_n_existe_pas ». Choisissez-en une dans la liste.'
        );
        $composant->assertSet('conditions', []);
    }

    /** Un arbre invalide liste ses erreurs — la porte n'invente rien, elle relaie `ValidateurDArbre`. */
    public function test_un_arbre_invalide_liste_ses_erreurs(): void
    {
        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode(['field' => 'niveau', 'op' => 'eq', 'value' => 'critique']))
            ->call('appliquerJson');

        $this->assertErreurJsonExacte($composant, "racine : champ inconnu 'niveau'.");
        $composant->assertSet('conditions', []);
    }

    public function test_un_arbre_valide_rempli_le_constructeur(): void
    {
        $arbre = ['and' => [['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']]];

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode($arbre))
            ->call('appliquerJson');

        $composant->assertHasNoErrors('conditionsJson');
        $composant->assertSet('conditions', $arbre);
    }

    /** LA MEME MARCHE — un arbre trop profond collé par la porte est refusé, jamais rendu. */
    public function test_un_arbre_trop_profond_colle_par_la_porte_est_refuse_et_ne_remplit_rien(): void
    {
        $arbre = $this->arbreDeProfondeur(RuleTreeEvaluator::PROFONDEUR_MAX + 1);

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode($arbre))
            ->call('appliquerJson');

        $this->assertErreurJsonExacte($composant, 'Arbre trop profond : '.RuleTreeEvaluator::PROFONDEUR_MAX.' niveaux au plus.');
        $composant->assertSet('conditions', []);
    }

    /** TÉMOIN — la profondeur EXACTE de la borne passe par la porte, sans lui le refus ci-dessus ne prouve rien. */
    public function test_temoin_un_arbre_a_la_profondeur_maximale_colle_par_la_porte_est_accepte(): void
    {
        $arbre = $this->arbreDeProfondeur(RuleTreeEvaluator::PROFONDEUR_MAX);

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode($arbre))
            ->call('appliquerJson');

        $composant->assertHasNoErrors('conditionsJson');
        $composant->assertSet('conditions', $arbre);
    }

    /**
     * LE TEST QUI COMPTE — l'arbre produit par le constructeur visuel, relu par la porte JSON,
     * redonne le MÊME arbre : même structure, même ordre, mêmes valeurs. `assertSame` (pas
     * `assertEqualsCanonicalizing`) pour que tout réordonnancement fasse tomber le test.
     */
    public function test_le_point_qui_compte_laller_retour_visuel_vers_porte_json_redonne_le_meme_arbre(): void
    {
        $original = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
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
            ->set('conditions.and.1.or.1.value', 'Paris')
            ->call('ajouterEnfant', 'and', 'not')
            ->call('definirNoeud', 'and.2.not', 'feuille')
            ->set('conditions.and.2.not.field', 'code_postal')
            ->set('conditions.and.2.not.op', 'eq')
            ->set('conditions.and.2.not.value', '75000')
            ->get('conditions');

        $porte = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode($original))
            ->call('appliquerJson');

        $porte->assertHasNoErrors('conditionsJson');
        $this->assertSame(
            $original,
            $porte->get('conditions'),
            'Aller-retour visuel -> JSON : structure, ordre et valeurs doivent rester identiques.'
        );
    }

    /**
     * LE TÉMOIN — un arbre valide collé par la porte s'enregistre ET sélectionne les bonnes
     * entités une fois repassé à `RuleTreeEvaluator`. Mêmes données que le témoin du constructeur
     * visuel (`ArbreDeConditionsTest`) : la porte doit produire un arbre qui marche pareil.
     */
    public function test_le_temoin_un_arbre_valide_colle_par_la_porte_selectionne_les_bonnes_entites(): void
    {
        $confirmeLyon = Booking::factory()->create();
        $confirmeLyon->forceFill(['status' => 'confirme', 'ville' => 'Lyon'])->save();
        $confirmeParis = Booking::factory()->create();
        $confirmeParis->forceFill(['status' => 'confirme', 'ville' => 'Paris'])->save();
        $confirmeMarseille = Booking::factory()->create();
        $confirmeMarseille->forceFill(['status' => 'confirme', 'ville' => 'Marseille'])->save();
        $refuseLyon = Booking::factory()->create();
        $refuseLyon->forceFill(['status' => 'refuse', 'ville' => 'Lyon'])->save();

        $arbre = [
            'and' => [
                ['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'],
                ['or' => [
                    ['field' => 'ville', 'op' => 'eq', 'value' => 'Lyon'],
                    ['field' => 'ville', 'op' => 'eq', 'value' => 'Paris'],
                ]],
            ],
        ];

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Porte json confirmees a lyon ou paris')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]])
            ->set('conditionsJson', json_encode($arbre))
            ->call('appliquerJson');

        $composant->assertHasNoErrors('conditionsJson')->assertSet('conditions', $arbre);

        $composant->call('enregistrer')->assertHasNoErrors();

        $regle = AutomationRule::query()->where('nom', 'Porte json confirmees a lyon ou paris')->firstOrFail();

        $requete = Booking::query();
        app(RuleTreeEvaluator::class)->apply($requete, $regle->conditions, app(BookingDescriptor::class));
        $ids = $requete->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$confirmeLyon->id, $confirmeParis->id], $ids);
        $this->assertNotContains($confirmeMarseille->id, $ids, 'Une ville hors liste ne doit pas correspondre.');
        $this->assertNotContains($refuseLyon->id, $ids, 'Un statut refuse est exclu par le ET.');
    }

    /**
     * LA CONTRAINTE DE CONCEPTION — ce champ n'exécute rien : une valeur à syntaxe SQL reste une
     * simple donnée liée en paramètre, jamais interprétée. La table survit, rien ne correspond.
     */
    public function test_le_champ_nexecute_rien_une_valeur_a_syntaxe_sql_reste_une_simple_donnee(): void
    {
        $lyon = Booking::factory()->create();
        $lyon->forceFill(['ville' => 'Lyon'])->save();

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->set('entite', 'booking')
            ->set('conditionsJson', json_encode(['field' => 'ville', 'op' => 'eq', 'value' => "'; DROP TABLE bookings; --"]))
            ->call('appliquerJson');

        $composant->assertHasNoErrors('conditionsJson');
        $composant->assertSet('conditions', ['field' => 'ville', 'op' => 'eq', 'value' => "'; DROP TABLE bookings; --"]);

        $requete = Booking::query();
        app(RuleTreeEvaluator::class)->apply($requete, $composant->get('conditions'), app(BookingDescriptor::class));

        $this->assertSame([], $requete->pluck('id')->all(), 'La chaîne est une donnée, jamais exécutée : rien ne correspond.');
        $this->assertDatabaseHas('bookings', ['id' => $lyon->id]);
    }

    /**
     * LE POINT QUI COMPTE — les conditions posées via la porte JSON changent ce que la règle FAIT,
     * au même titre que celles posées à la souris : elles rétrogradent une règle armée.
     */
    public function test_modifier_les_conditions_via_la_porte_json_retrograde_une_regle_armee(): void
    {
        $regle = AutomationRule::create([
            'nom' => 'Armee porte json',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'heure',
            'etat' => AutomationRule::ETAT_ARMEE,
            'conditions' => [],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->set('conditionsJson', json_encode(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme']))
            ->call('appliquerJson')
            ->assertHasNoErrors('conditionsJson')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle->refresh();

        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->etat);
        $this->assertSame(['field' => 'statut', 'op' => 'eq', 'value' => 'confirme'], $regle->conditions);
    }
}
