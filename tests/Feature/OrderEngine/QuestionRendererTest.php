<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\PricingEngine;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Le composant qui rend une question — le même pour le client et pour l'aperçu du constructeur. */
class QuestionRendererTest extends TestCase
{
    use RefreshDatabase;

    /** Loi 5 — la réponse la plus fréquente est déjà là. Le client valide plus qu'il ne remplit. */
    public function test_it_preselects_the_default_answer(): void
    {
        $question = $this->choice([
            ['label' => 'Les murs', 'value' => 'murs', 'is_default' => true],
            ['label' => 'Murs et plafonds', 'value' => 'murs_plafonds'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->assertSet('value', 'murs');
    }

    /** Loi 10 — revenir en arrière ne perd rien. */
    public function test_the_default_never_overwrites_an_existing_answer(): void
    {
        $question = $this->choice([
            ['label' => 'Les murs', 'value' => 'murs', 'is_default' => true],
            ['label' => 'Murs et plafonds', 'value' => 'murs_plafonds'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question, 'value' => 'murs_plafonds'])
            ->assertSet('value', 'murs_plafonds');
    }

    /** LE contrat avec le moteur tarifaire. */
    public function test_the_way_out_produces_exactly_what_the_pricing_engine_reads(): void
    {
        $trade = Trade::create([
            'slug' => 'peinture-'.uniqid(), 'code' => strtoupper(substr(uniqid(), -8)),
            'name' => 'Peinture', 'base_price_cents' => 10000,
        ]);

        $question = $this->choice([
            ['label' => 'Les murs', 'value' => 'murs', 'price_modifier_cents' => 0, 'is_default' => true],
            ['label' => 'Tout', 'value' => 'tout', 'price_modifier_cents' => 5000],
        ], $trade);

        $payload = Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->call('markUnknown')
            ->instance()
            ->answerPayload();

        $this->assertSame(['unknown' => true], $payload);

        // Et le moteur l'interprète bien comme une fourchette, pas comme une absence de réponse.
        $quote = app(PricingEngine::class)->quoteItem(
            $trade,
            $trade->questions()->with(['options', 'conditions'])->get(),
            [$question->code => $payload],
        );

        $this->assertSame(5000, $quote->spreadCents());
    }

    public function test_answering_after_a_way_out_cancels_it(): void
    {
        $question = $this->choice([
            ['label' => 'A', 'value' => 'a', 'is_default' => true],
            ['label' => 'B', 'value' => 'b'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->call('markUnknown')
            ->assertSet('unknown', true)
            ->set('value', 'b')
            ->assertSet('unknown', false);
    }

    /** Le parent apprend la réponse par un événement : le composant ignore tout du prix et de la commande. */
    public function test_it_announces_the_answer_to_its_parent(): void
    {
        $question = $this->choice([
            ['label' => 'A', 'value' => 'a', 'is_default' => true],
            ['label' => 'B', 'value' => 'b'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->set('value', 'b')
            ->assertDispatched('question-answered', code: $question->code, value: 'b', valid: true);
    }

    /** L'erreur n'apparaît qu'une fois le champ quitté. */
    public function test_no_error_is_shown_before_the_field_has_been_left(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'surface_m2', 'label' => 'Surface',
            'type' => QuestionType::SURFACE, 'is_required' => true,
            'validation' => ['min' => 5, 'max' => 400, 'unit' => 'm²'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->assertSet('touched', false)
            ->assertDontSee('nécessaire');
    }

    /** Le message dit QUOI FAIRE, pas seulement ce qui ne va pas. */
    public function test_the_error_tells_the_client_what_to_do(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'surface_m2', 'label' => 'Surface',
            'type' => QuestionType::SURFACE,
            'validation' => ['min' => 5, 'max' => 400, 'unit' => 'm²'],
        ]);

        $component = Livewire::test(QuestionRenderer::class, ['question' => $question])->set('value', 900);

        $this->assertStringContainsString('je ne sais pas', (string) $component->get('error'));
        $this->assertStringContainsString('400', (string) $component->get('error'));
    }

    /** Le pas et les bornes viennent de la question, jamais d'une constante du composant. */
    public function test_the_counter_obeys_the_bounds_declared_by_the_administrator(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'etages', 'label' => 'Étages',
            'type' => QuestionType::COUNTER,
            'validation' => ['min' => 0, 'max' => 2, 'step' => 1],
            'display' => ['layout' => 'counter'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->set('value', 2)
            ->call('increment')
            ->assertSet('value', 2.0)
            ->set('value', 0)
            ->call('decrement')
            ->assertSet('value', 0.0);
    }

    /** Peu de gens connaissent leurs mètres carrés ; tout le monde sait mesurer deux côtés. */
    public function test_the_surface_helper_multiplies_two_sides(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'surface_m2', 'label' => 'Surface',
            'type' => QuestionType::SURFACE, 'display' => ['layout' => 'slider'],
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->set('helperLength', 5)
            ->set('helperWidth', 4.5)
            ->call('applySurfaceHelper')
            ->assertSet('value', 22.5);
    }

    public function test_a_multi_choice_adds_then_removes(): void
    {
        $question = $this->choice([
            ['label' => 'Tonte', 'value' => 'tonte', 'is_default' => true],
            ['label' => 'Haies', 'value' => 'haies'],
        ]);
        $question->update(['type' => QuestionType::MULTI_CHOICE]);

        Livewire::test(QuestionRenderer::class, ['question' => $question->fresh()->load('options')])
            ->call('toggleOption', 'haies')
            ->assertSet('value', ['tonte', 'haies'])
            ->call('toggleOption', 'tonte')
            ->assertSet('value', ['haies']);
    }

    /** Chaque question du catalogue livré se rend sans erreur. */
    public function test_every_seeded_question_renders(): void
    {
        $this->seed(OrderEngineCatalogSeeder::class);

        $questions = Question::with('options')->get();
        $this->assertGreaterThan(40, $questions->count());

        foreach ($questions as $question) {
            Livewire::test(QuestionRenderer::class, ['question' => $question])
                ->assertOk()
                ->assertSee($question->label);
        }
    }

    /** Un type inconnu retombe sur un champ texte plutôt que de casser le parcours. */
    public function test_an_unknown_type_falls_back_instead_of_breaking(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'exotique', 'label' => 'Type inédit',
            'type' => 'quelque_chose_de_neuf',
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->assertOk()
            ->assertSee('Type inédit');
    }

    /** La porte de sortie n'est proposée que là où l'administrateur l'a ouverte. */
    public function test_the_way_out_is_hidden_when_the_question_forbids_it(): void
    {
        $question = Question::create([
            'trade_id' => $this->trade()->id, 'code' => 'adresse', 'label' => 'Adresse',
            'type' => QuestionType::ADDRESS, 'allows_unknown' => false,
        ]);

        Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->assertDontSee('Je ne sais pas');
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function trade(): Trade
    {
        return Trade::create([
            'slug' => 'trade-'.uniqid(),
            'code' => strtoupper(substr(uniqid(), -8)),
            'name' => 'Métier de test',
        ]);
    }

    /** @param  list<array<string, mixed>>  $options */
    private function choice(array $options, ?Trade $trade = null): Question
    {
        $question = Question::create([
            'trade_id' => ($trade ?? $this->trade())->id,
            'code' => 'etendue',
            'label' => 'Que faut-il traiter ?',
            'type' => QuestionType::SINGLE_CHOICE,
            'pricing' => ['mode' => PriceImpactMode::NONE],
        ]);

        foreach ($options as $index => $option) {
            QuestionOption::create($option + ['question_id' => $question->id, 'sort_order' => $index]);
        }

        return $question->load('options');
    }
}
