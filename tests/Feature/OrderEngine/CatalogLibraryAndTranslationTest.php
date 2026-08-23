<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\AuditEvent;
use App\Models\OrderDraft;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

/** La bibliothèque, les traductions, l'audit et le réordonnancement. Trois principes s'y jouent. */
class CatalogLibraryAndTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    protected function tearDown(): void
    {
        App::setLocale('fr');
        parent::tearDown();
    }

    // ─── La bibliothèque ─────────────────────────────────────────────────────────────────────

    /** Une question de bibliothèque n'apparaît dans AUCUN parcours : c'est un modèle. */
    public function test_a_library_question_never_shows_up_in_a_journey(): void
    {
        $template = $this->libraryQuestion('ascenseur');

        $this->assertTrue($template->isLibraryTemplate());
        $this->assertFalse(
            $this->peinture()->questions()->pluck('code')->contains('ascenseur'),
            'Une question globale ne doit pas être rendue telle quelle dans un métier.',
        );
    }

    /** Reprendre crée une COPIE, pas un partage. */
    public function test_adopting_creates_an_independent_copy(): void
    {
        $template = $this->libraryQuestion('ascenseur');

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('adoptFromLibrary', $template->id);

        $copy = $this->peinture()->questions()->where('code', 'ascenseur')->firstOrFail();
        $this->assertNotSame($template->id, $copy->id);

        // Modifier la copie ne touche pas le modèle.
        $copy->update(['label' => 'Ascenseur dans l’immeuble ?']);
        $this->assertSame('Y a-t-il un ascenseur ?', $template->fresh()->label);
    }

    /** Les options et les traductions suivent la copie : reprendre ne fait rien perdre. */
    public function test_adopting_carries_options_and_translations(): void
    {
        $template = $this->libraryQuestion('ascenseur');
        $template->setTranslation('label', 'nl', 'Is er een lift?');

        $option = QuestionOption::create([
            'question_id' => $template->id,
            'label' => 'Oui',
            'value' => 'oui',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $option->setTranslation('label', 'nl', 'Ja');

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('adoptFromLibrary', $template->id);

        $copy = $this->peinture()->questions()->where('code', 'ascenseur')->firstOrFail();

        $this->assertSame('Is er een lift?', $copy->translate('label', 'nl'));
        $this->assertSame('Ja', $copy->options()->firstOrFail()->translate('label', 'nl'));
    }

    /** Un code déjà pris n'est pas écrasé : ce serait remplacer une question déjà répondue. */
    public function test_adopting_does_not_overwrite_an_existing_code(): void
    {
        $existing = $this->peinture()->questions()->firstOrFail();
        $template = $this->libraryQuestion($existing->code);

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('adoptFromLibrary', $template->id)
            ->assertSee('existe déjà');

        $this->assertSame(
            1,
            $this->peinture()->questions()->where('code', $existing->code)->count(),
        );
    }

    // ─── Les traductions ─────────────────────────────────────────────────────────────────────

    /** Sans traduction, on retombe sur le libellé de base — JAMAIS sur du vide. */
    public function test_a_missing_translation_falls_back_instead_of_going_blank(): void
    {
        $question = $this->peinture()->questions()->firstOrFail();

        $this->assertSame($question->label, $question->translate('label', 'nl'));
        $this->assertNotEmpty($question->translate('label', 'nl'));
    }

    public function test_a_translation_is_used_when_it_exists(): void
    {
        $question = $this->peinture()->questions()->firstOrFail();
        $question->setTranslation('label', 'nl', 'Oppervlakte in m²');

        $this->assertSame('Oppervlakte in m²', $question->fresh()->translate('label', 'nl'));
        $this->assertSame($question->label, $question->fresh()->translate('label', 'fr'));
    }

    /** Vider une traduction la SUPPRIME : effacer doit ramener au libellé de base, pas au blanc. */
    public function test_clearing_a_translation_restores_the_base_label(): void
    {
        $question = $this->peinture()->questions()->firstOrFail();
        $question->setTranslation('label', 'nl', 'Oppervlakte');
        $question->setTranslation('label', 'nl', '');

        $this->assertSame(0, $question->translations()->count());
        $this->assertSame($question->label, $question->fresh()->translate('label', 'nl'));
    }

    /** L'écran d'administration dit ce qui MANQUE, plutôt que de laisser découvrir le trou. */
    public function test_the_builder_reports_which_languages_are_missing(): void
    {
        $question = $this->peinture()->questions()->firstOrFail();

        $this->assertContains('nl', $question->missingLocales());

        $question->setTranslation('label', 'nl', 'Oppervlakte');
        $this->assertNotContains('nl', $question->fresh()->missingLocales());
    }

    /** L'INSTANTANÉ garde ce que le client a vu, dans SA langue. */
    public function test_the_quote_snapshot_keeps_the_language_the_client_read(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'etendue')->firstOrFail();
        $question->setTranslation('label', 'nl', 'Wat moet er geschilderd worden?');
        $question->options()->where('value', 'murs_plafonds')->firstOrFail()
            ->setTranslation('label', 'nl', 'Muren en plafonds');

        App::setLocale('nl');

        $manager = app(OrderDraftManager::class);
        $draft = $manager->resumeOrCreate('jeton-nl');
        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        $answer = $item->fresh('answers')->answers->firstWhere('question_code', 'etendue');

        $this->assertSame('Wat moet er geschilderd worden?', $answer->question_label_snapshot);
        $this->assertSame('Muren en plafonds', $answer->answer_label_snapshot);
    }

    // ─── L'audit ─────────────────────────────────────────────────────────────────────────────

    /** Toucher au catalogue laisse une trace. */
    public function test_changing_a_price_leaves_a_trace(): void
    {
        $question = $this->peinture()->questions()->firstOrFail();

        $before = AuditEvent::query()->where('domain', 'catalog')->count();
        $question->update(['pricing' => ['coefficient_cents' => 999]]);

        $this->assertGreaterThan(
            $before,
            AuditEvent::query()->where('domain', 'catalog')->count(),
            'Un changement de tarification aurait dû être audité.',
        );
    }

    // ─── Le réordonnancement ─────────────────────────────────────────────────────────────────

    /** Glisser-déposer enregistre l'ordre reçu. */
    public function test_reordering_persists_the_new_order(): void
    {
        $trade = $this->peinture();
        $ids = $trade->questions()->orderBy('sort_order')->pluck('id')->all();
        $reversed = array_reverse($ids);

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('reorder', $reversed);

        $this->assertSame(
            $reversed,
            $trade->fresh()->questions()->orderBy('sort_order')->pluck('id')->all(),
        );
    }

    /** Le serveur ne croit pas l'ordre reçu du navigateur. */
    public function test_an_order_containing_a_foreign_question_is_refused(): void
    {
        $trade = $this->peinture();
        $ids = $trade->questions()->orderBy('sort_order')->pluck('id')->all();
        $foreign = Trade::where('slug', 'plumbing')->firstOrFail()->questions()->firstOrFail();

        $before = $trade->questions()->orderBy('sort_order')->pluck('id')->all();

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('reorder', array_merge([$foreign->id], array_slice($ids, 1)));

        $this->assertSame($before, $trade->fresh()->questions()->orderBy('sort_order')->pluck('id')->all());
    }

    /** Une liste incomplète est refusée : réordonner à moitié laisse des questions au hasard. */
    public function test_a_partial_order_is_refused(): void
    {
        $trade = $this->peinture();
        $ids = $trade->questions()->orderBy('sort_order')->pluck('id')->all();
        $before = $ids;

        Livewire::actingAs($this->admin())
            ->test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('reorder', [array_pop($ids)]);

        $this->assertSame($before, $trade->fresh()->questions()->orderBy('sort_order')->pluck('id')->all());
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']);
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** Une question de bibliothèque : pas de métier, donc pas de parcours. */
    private function libraryQuestion(string $code): Question
    {
        return Question::create([
            'trade_id' => null,
            'code' => $code,
            'label' => 'Y a-t-il un ascenseur ?',
            'type' => QuestionType::SINGLE_CHOICE,
            'is_required' => false,
            'sort_order' => 0,
            'allows_unknown' => true,
            'is_active' => true,
        ]);
    }

    /** @noinspection PhpUnused — utilisé par les assertions sur le panier. */
    private function draft(): ?OrderDraft
    {
        return OrderDraft::first();
    }
}
