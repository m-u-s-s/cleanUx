<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Country;
use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Le taux d'abandon, VU par l'administrateur. */
class QuestionInsightsScreenTest extends TestCase
{
    /**
     * Le contexte géographique exigé par l'écran, créé à la demande.
     *
     * @return array{country: Country, zone: ServiceZone}
     */
    private function contexteCatalogue(): array
    {
        if ($this->contexteCatalogue === null) {
            $pays = Country::factory()->create();
            $this->contexteCatalogue = [
                'country' => $pays,
                'zone' => ServiceZone::factory()->create(['country_id' => $pays->id]),
            ];
        }

        return $this->contexteCatalogue;
    }

    /** @var array{country: Country, zone: ServiceZone}|null */
    private ?array $contexteCatalogue = null;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']));
    }

    /** Le constructeur affiche l'abandon là où l'on écrit les questions. */
    public function test_the_builder_shows_the_drop_rate_on_the_question(): void
    {
        $trade = $this->peinture();
        $this->losingClients($trade, 25);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->assertSee('abandon')
            ->assertSee('%');
    }

    /** La question qui fait décrocher est NOMMÉE, et l'écran dit quoi faire. */
    public function test_a_losing_question_is_flagged_with_what_to_do(): void
    {
        $trade = $this->peinture();
        $this->losingClients($trade, 25);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->assertSee('fait décrocher');
    }

    /** SOUS LE VOLUME MINIMUM, aucun verdict — et on dit pourquoi. */
    public function test_a_tiny_sample_says_it_cannot_conclude_yet(): void
    {
        $trade = $this->peinture();
        $this->losingClients($trade, 3);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->assertDontSee('fait décrocher')
            ->assertSee('pas encore assez de commandes');
    }

    /** Le catalogue signale le métier concerné. */
    public function test_the_catalog_points_at_the_trade_that_loses_clients(): void
    {
        $trade = $this->peinture();
        $this->losingClients($trade, 25);

        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())
            ->assertSee('fait décrocher');
    }

    /** Le coût des statistiques NE GRANDIT PAS avec le nombre de questions. */
    public function test_the_statistics_do_not_cost_one_query_per_question(): void
    {
        $trade = $this->peinture();
        $this->losingClients($trade, 25);

        $this->assertGreaterThan(
            5,
            $trade->questions()->count(),
            'Le métier testé doit porter assez de questions pour que le défaut se voie.',
        );

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $component->instance()->insights;
        $component->instance()->losingQuestionCodes;

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            6,
            $count,
            sprintf('Les statistiques coûtent %d requêtes : elles sont calculées question par question.', $count),
        );
    }

    /** Vingt-cinq commandes qui s'arrêtent toutes à la première question. */
    private function losingClients(Trade $trade, int $howMany): void
    {
        foreach (range(1, $howMany) as $i) {
            $draft = OrderDraft::create([
                'reference' => OrderDraft::generateReference(),
                'session_token' => 'jeton-'.$i,
                'mode' => OrderMode::SCHEDULED,
                'status' => OrderDraftStatus::DRAFT,
            ]);

            $item = OrderDraftItem::create(['order_draft_id' => $draft->id, 'trade_id' => $trade->id]);
            $question = $trade->questions()->where('code', 'surface_m2')->firstOrFail();

            OrderDraftAnswer::create([
                'order_draft_item_id' => $item->id,
                'question_id' => $question->id,
                'question_code' => $question->code,
                'question_label_snapshot' => $question->label,
                'answer_label_snapshot' => 'une réponse',
            ]);
        }
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }
}
