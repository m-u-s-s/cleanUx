<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\Trade;
use App\Models\TradeFormRevision;
use App\Models\User;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\OrderEngine\QuestionnairePortability;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Publier une version, et déplacer un questionnaire.
 *
 * La révision est ce qui rend un devis REJOUABLE, pas seulement lisible : sans elle, on saurait ce
 * que le client a répondu mais plus jamais ce qu'on lui avait demandé ni comment son prix avait
 * été calculé.
 *
 * La portabilité, elle, tient à un seul point : les conditions se remappent par CODE. Les copier
 * par identifiant les ferait pointer vers les questions du métier d'origine — sans erreur, sans
 * rien signaler, jusqu'à ce qu'un client voie une question surgir sans raison.
 */
class PublicationAndPortabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    // ─── Publication ─────────────────────────────────────────────────────────────────────────

    public function test_publishing_freezes_a_numbered_version(): void
    {
        $trade = $this->peinture();

        $first = app(TradeFormPublisher::class)->publish($trade, $this->admin());
        $second = app(TradeFormPublisher::class)->publish($trade->fresh(), $this->admin());

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertNotNull($trade->fresh()->published_at);
    }

    /** La version figée porte tout ce qu'il faut pour rejouer un devis, pas seulement le libellé. */
    public function test_the_frozen_version_carries_enough_to_replay_a_quote(): void
    {
        $schema = app(TradeFormPublisher::class)->publish($this->peinture(), $this->admin())->schema;

        $this->assertSame(12000, $schema['trade']['base_price_cents']);

        $surface = collect($schema['questions'])->firstWhere('code', 'surface_m2');
        $this->assertSame(250, $surface['pricing']['coefficient']);

        $etendue = collect($schema['questions'])->firstWhere('code', 'etendue');
        $this->assertSame(4500, collect($etendue['options'])->firstWhere('value', 'murs_plafonds')['price_modifier_cents']);
    }

    /**
     * Un défaut bloquant empêche la mise en ligne.
     *
     * Deux réponses par défaut produisent un écran dont le comportement dépend de l'ordre de tri :
     * le client validerait une réponse qu'il n'a pas choisie, et personne ne le saurait.
     */
    public function test_a_blocking_flaw_refuses_publication(): void
    {
        $trade = $this->peinture();
        $trade->questions()->where('code', 'etendue')->firstOrFail()->options()->update(['is_default' => true]);

        $this->expectException(ValidationException::class);

        app(TradeFormPublisher::class)->publish($trade, $this->admin());
    }

    /** Un simple avertissement, lui, ne bloque pas : il coûte des clients, il ne casse rien. */
    public function test_a_mere_warning_does_not_block_publication(): void
    {
        $trade = $this->peinture();
        $trade->questions()->first()->update(['allows_unknown' => false]);

        $this->assertNotNull(app(TradeFormPublisher::class)->publish($trade, $this->admin()));
    }

    /**
     * Le brouillon en attente se juge sur le CONTENU.
     *
     * Renommer une question puis annuler laisse une trace dans `updated_at` sans rien changer au
     * parcours ; signaler « en attente » dans ce cas apprendrait à l'administrateur à ignorer
     * l'avertissement, et il l'ignorerait aussi le jour où il compte.
     */
    public function test_pending_changes_are_judged_on_content_not_timestamps(): void
    {
        $trade = $this->peinture();
        $publisher = app(TradeFormPublisher::class);
        $publisher->publish($trade, $this->admin());

        $question = $trade->questions()->first();
        $original = $question->label;

        $question->touch();
        $this->assertFalse($publisher->hasUnpublishedChanges($trade->fresh()));

        $question->update(['label' => 'Un tout autre libellé']);
        $this->assertTrue($publisher->hasUnpublishedChanges($trade->fresh()));

        $question->update(['label' => $original]);
        $this->assertFalse($publisher->hasUnpublishedChanges($trade->fresh()));
    }

    /**
     * LE maillon qui manquait : la commande cite la révision employée.
     *
     * Sans elle, un devis de six mois n'est plus rejouable — le questionnaire aura changé trois
     * fois d'ici la contestation.
     */
    public function test_an_order_line_records_the_revision_it_used(): void
    {
        $trade = $this->peinture();
        $revision = app(TradeFormPublisher::class)->publish($trade, $this->admin());

        $manager = app(OrderDraftManager::class);
        $item = $manager->itemFor($manager->resumeOrCreate('jeton'), $trade->fresh());

        $this->assertSame($revision->id, $item->trade_form_revision_id);
    }

    // ─── Portabilité ─────────────────────────────────────────────────────────────────────────

    /** Dupliquer recopie questions, options et conditions vers un autre métier. */
    public function test_duplicating_carries_the_whole_questionnaire(): void
    {
        $source = $this->peinture();
        $target = Trade::where('slug', 'vitrerie')->firstOrFail();

        $result = app(QuestionnairePortability::class)->duplicate($source, $target);

        $this->assertGreaterThan(0, $result['created']);
        $this->assertNotNull($target->questions()->where('code', 'surface_m2')->first());
        $this->assertNotNull($target->questions()->where('code', 'type_pistolet')->first());
    }

    /**
     * LA garantie de la duplication : les conditions pointent vers le NOUVEAU métier.
     *
     * Recopiées par identifiant, elles viseraient les questions d'origine et se déclencheraient sur
     * les réponses de quelqu'un d'autre — sans erreur, jusqu'à ce qu'un client voie une question
     * surgir sans raison.
     */
    public function test_duplicated_conditions_point_inside_the_target_trade(): void
    {
        $source = $this->peinture();
        $target = Trade::where('slug', 'vitrerie')->firstOrFail();

        app(QuestionnairePortability::class)->duplicate($source, $target);

        $dependent = $target->questions()->where('code', 'type_pistolet')->firstOrFail();
        $condition = $dependent->conditions()->firstOrFail();
        $trigger = Question::findOrFail($condition->depends_on_question_id);

        $this->assertSame($target->id, $trigger->trade_id, 'La condition vise encore le métier d’origine.');
        $this->assertSame('application', $trigger->code);
    }

    /** Rejouer le même import met à jour au lieu de dupliquer : deux environnements se synchronisent. */
    public function test_importing_twice_updates_instead_of_duplicating(): void
    {
        $source = $this->peinture();
        $target = Trade::where('slug', 'vitrerie')->firstOrFail();
        $portability = app(QuestionnairePortability::class);

        $portability->duplicate($source, $target);
        $countAfterFirst = $target->questions()->count();

        $second = $portability->duplicate($source, $target->fresh());

        $this->assertSame($countAfterFirst, $target->fresh()->questions()->count());
        $this->assertSame(0, $second['created']);
        $this->assertGreaterThan(0, $second['updated']);
    }

    /**
     * Un import n'EFFACE rien.
     *
     * C'est une contribution, pas une remise à zéro : supprimer silencieusement des questions déjà
     * répondues rendrait des devis inexplicables.
     */
    public function test_an_import_never_deletes_what_it_does_not_mention(): void
    {
        $target = Trade::where('slug', 'vitrerie')->firstOrFail();
        $ownQuestion = $target->questions()->firstOrFail();

        app(QuestionnairePortability::class)->duplicate($this->peinture(), $target);

        $this->assertNotNull(Question::find($ownQuestion->id));
    }

    /**
     * Une question archivée n'est pas ressuscitée par un import.
     *
     * Son code reste réservé — ce qui garde les instantanés univoques — mais la réécrire lui
     * donnerait un sens neuf sous une clé déjà employée par d'anciennes réponses.
     */
    public function test_an_import_does_not_resurrect_an_archived_question(): void
    {
        $target = Trade::where('slug', 'vitrerie')->firstOrFail();

        // On archive une question dont le code existe aussi dans la source.
        $clash = Question::create([
            'trade_id' => $target->id, 'code' => 'surface_m2',
            'label' => 'Ancienne surface', 'type' => QuestionType::SURFACE,
        ]);
        $clash->update(['is_active' => false]);
        $clash->delete();

        $result = app(QuestionnairePortability::class)->duplicate($this->peinture(), $target);

        $this->assertContains('surface_m2', $result['skipped']);
        $this->assertNull($target->questions()->where('code', 'surface_m2')->first());
    }

    /** Une condition orpheline n'est pas exportée : elle rendrait sa question invisible pour toujours. */
    public function test_a_dangling_condition_is_not_exported(): void
    {
        $trade = $this->peinture();
        $orphan = Question::create([
            'trade_id' => Trade::where('slug', 'vitrerie')->value('id'),
            'code' => 'ailleurs', 'label' => 'Ailleurs', 'type' => QuestionType::TEXT,
        ]);
        QuestionCondition::create([
            'question_id' => $trade->questions()->first()->id,
            'depends_on_question_id' => $orphan->id,
            'operator' => ConditionOperator::IS_ANSWERED,
            'action' => ConditionAction::SHOW,
        ]);

        $exported = app(QuestionnairePortability::class)->export($trade->fresh());
        $codes = collect($exported['questions'])->flatMap(fn ($q) => collect($q['conditions'])->pluck('depends_on_code'));

        $this->assertFalse($codes->contains('ailleurs'));
    }

    /** L'export se relit : c'est le seul moyen de rejouer un questionnaire ailleurs. */
    public function test_an_export_can_be_replayed_on_an_empty_trade(): void
    {
        $payload = app(QuestionnairePortability::class)->export($this->peinture());

        $fresh = Trade::create([
            'slug' => 'peinture-copie', 'code' => 'PNT-C', 'name' => 'Peinture (copie)',
        ]);

        app(QuestionnairePortability::class)->import($fresh, $payload);

        $this->assertSame(
            $this->peinture()->questions()->count(),
            $fresh->fresh()->questions()->count(),
        );
    }

    // ─── Le constructeur ─────────────────────────────────────────────────────────────────────

    public function test_the_builder_publishes_and_announces_the_version(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('publish')
            ->assertSee('version 1');

        $this->assertSame(1, TradeFormRevision::count());
    }

    /** Le constructeur refuse de publier un parcours cassé, et le dit. */
    public function test_the_builder_refuses_to_publish_a_broken_journey(): void
    {
        $this->actingAs($this->admin());
        $trade = $this->peinture();
        $trade->questions()->where('code', 'etendue')->firstOrFail()->options()->update(['is_default' => true]);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('publish')
            ->assertHasErrors('publication');

        $this->assertSame(0, TradeFormRevision::count());
    }

    /** Le brouillon en attente est signalé après une modification. */
    public function test_the_builder_reports_pending_changes(): void
    {
        $this->actingAs($this->admin());
        $trade = $this->peinture();

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])->call('publish');
        $this->assertFalse($component->instance()->hasUnpublishedChanges());

        $component->call('edit', $trade->questions()->first()->id)
            ->set('form.label', 'Un libellé tout neuf')
            ->call('save');

        $this->assertTrue($component->instance()->hasUnpublishedChanges());
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /**
     * Deux schémas au contenu identique mais aux clés dans un autre ordre sont LE MÊME schéma.
     *
     * MySQL réordonne les clés d'une colonne JSON ; ce qu'on relit n'a pas l'ordre de ce qu'on a
     * écrit. Comme `!==` sur des tableaux PHP compare aussi l'ordre, la comparaison directe
     * déclarait le questionnaire modifié à chaque appel — et le constructeur affichait
     * « modifications non publiées » en permanence, y compris juste après une publication.
     *
     * Le test attaque la comparaison directement plutôt que de passer par la base : il tient donc
     * sur SQLite comme sur MySQL, alors que le défaut n'était visible que sur le second.
     */
    public function test_two_schemas_differing_only_in_key_order_are_the_same(): void
    {
        $publisher = app(TradeFormPublisher::class);

        $ecrit = [
            'format_version' => 1,
            'trade' => ['slug' => 'peinture', 'name' => 'Peinture', 'base_price_cents' => 12000],
            'questions' => [
                ['code' => 'surface_m2', 'label' => 'Surface', 'options' => [['value' => 'a', 'label' => 'A']]],
            ],
        ];

        // Le même contenu, clés remises dans l'ordre où MySQL les rend.
        $relu = [
            'trade' => ['name' => 'Peinture', 'slug' => 'peinture', 'base_price_cents' => 12000],
            'questions' => [
                ['label' => 'Surface', 'code' => 'surface_m2', 'options' => [['label' => 'A', 'value' => 'a']]],
            ],
            'format_version' => 1,
        ];

        $this->assertTrue($publisher->sameSchema($ecrit, $relu));

        // Mais un vrai changement de contenu reste détecté.
        $modifie = $relu;
        $modifie['questions'][0]['label'] = 'Superficie';
        $this->assertFalse($publisher->sameSchema($ecrit, $modifie));

        // Et l'ORDRE DES QUESTIONS reste significatif : ce n'est pas une clé, c'est une séquence.
        $permute = $ecrit;
        $permute['questions'][] = ['code' => 'etendue', 'label' => 'Étendue'];
        $inverse = $permute;
        $inverse['questions'] = array_reverse($inverse['questions']);
        $this->assertFalse($publisher->sameSchema($permute, $inverse));
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
