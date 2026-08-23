<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Question;
use App\Models\QuestionStep;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\OrderEngine\PricingEngine;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PricingUnit;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** Le catalogue de démonstration — et les lois du parcours, vérifiées sur les DONNÉES. */
class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_it_seeds_three_sectors_and_nine_trades(): void
    {
        $this->assertSame(3, Sector::count());
        $this->assertSame(9, Trade::whereNotNull('sector_id')->count());
    }

    /** Rejouer le seeder ne doit RIEN dupliquer. */
    public function test_running_it_twice_changes_nothing(): void
    {
        $before = [Sector::count(), Trade::count(), Question::count(), QuestionStep::count()];

        $this->seed(OrderEngineCatalogSeeder::class);

        $this->assertSame($before, [Sector::count(), Trade::count(), Question::count(), QuestionStep::count()]);
    }

    /** Loi 3 — au plus sept questions par étape. Au-delà, un client sur trois abandonne. */
    public function test_no_step_asks_more_than_seven_questions(): void
    {
        $max = (int) Config::get('order_engine.max_questions_per_step', 7);

        // On relève TOUTES les étapes trop longues : une assertion par tour n'en nommerait qu'une,
        // et il faudrait autant d'exécutions que de questionnaires à retailler.
        $trop_longues = [];

        foreach (QuestionStep::withCount('questions')->get() as $step) {
            if ($step->questions_count > $max) {
                $trop_longues[] = "{$step->title} → {$step->questions_count} questions";
            }
        }

        $this->assertSame([], $trop_longues, "Au-delà de {$max} questions d'un coup, un client sur trois abandonne.");
    }

    /** Loi 6 — chaque question offre une porte de sortie. Une question sans échappatoire est un mur. */
    public function test_every_question_offers_a_way_out(): void
    {
        $walls = Question::where('allows_unknown', false)->pluck('code');

        $this->assertTrue($walls->isEmpty(), 'Questions sans porte de sortie : '.$walls->implode(', '));
    }

    /** Loi 5 — un défaut intelligent, et UN SEUL. */
    public function test_every_choice_question_has_exactly_one_default(): void
    {
        $questions = Question::with('options')
            ->whereIn('type', QuestionType::optionBased())
            ->get();

        $this->assertNotEmpty($questions);

        $mauvaises = [];

        foreach ($questions as $question) {
            $defauts = $question->options->where('is_default', true)->count();

            if ($defauts !== 1) {
                $mauvaises[] = "{$question->code} → {$defauts} option(s) par défaut";
            }
        }

        $this->assertSame([], $mauvaises, 'Chaque question doit offrir exactement une porte de sortie.');
    }

    /** Loi 4 — la photo est proposée partout, et n'est jamais obligatoire : c'est un raccourci, pas un péage. */
    public function test_every_trade_offers_an_optional_photo(): void
    {
        $ecarts = [];

        foreach (Trade::whereNotNull('sector_id')->get() as $trade) {
            $photo = $trade->questions()->where('type', QuestionType::PHOTO)->first();

            if ($photo === null) {
                $ecarts[] = "{$trade->name} → aucune question photo";
            } elseif ((bool) $photo->is_required) {
                $ecarts[] = "{$trade->name} → photo OBLIGATOIRE (c'est un raccourci offert, pas un passage obligé)";
            }
        }

        $this->assertSame([], $ecarts, 'Ces métiers ne proposent pas correctement la photo.');
    }

    /** Le mode immédiat n'est ouvert que là où il a un sens, et il pose alors des questions essentielles — sans quoi il n'aurait rien à demander avant d'envoyer un prestataire. */
    public function test_asap_trades_declare_their_essential_questions(): void
    {
        $asapTrades = Trade::whereNotNull('sector_id')->where('allows_asap', true)->get();

        $this->assertNotEmpty($asapTrades, 'Aucun métier ouvert au service immédiat : le mode serait mort-né.');

        $muets = [];

        foreach ($asapTrades as $trade) {
            if ($trade->questions()->where('is_essential', true)->count() === 0) {
                $muets[] = $trade->name;
            }
        }

        $this->assertSame([], $muets, 'Ces métiers sont ouverts au mode immédiat sans rien demander avant d’envoyer quelqu’un.');
    }

    /** Un chantier de peinture ou une toiture ne se commandent pas dans l'heure. */
    public function test_heavy_trades_stay_closed_to_the_immediate_mode(): void
    {
        // Les trois metiers releves ensemble : ouvrir l'immediat par erreur les ouvre souvent
        // tous les trois, et une assertion par tour n'en nommerait qu'un.
        $ouverts = array_values(array_filter(
            ['peinture', 'roofing', 'elagage'],
            fn (string $slug) => (bool) Trade::where('slug', $slug)->firstOrFail()->allows_asap,
        ));

        $this->assertSame([], $ouverts, 'Ces metiers ne se commandent pas dans l heure.');
    }

    /** La question conditionnelle décrite dans la spécification est bien câblée. */
    public function test_the_spray_gun_question_depends_on_the_spray_answer(): void
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail();

        $dependent = $trade->questions()->where('code', 'type_pistolet')->firstOrFail();
        $condition = $dependent->conditions()->firstOrFail();
        $trigger = Question::findOrFail($condition->depends_on_question_id);

        $this->assertSame('application', $trigger->code);
        $this->assertSame('pistolet', $condition->value['value']);
    }

    /** La preuve de bout en bout : le questionnaire semé produit un prix cohérent et explicable. */
    public function test_the_seeded_painting_questionnaire_produces_an_explainable_price(): void
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail();
        $questions = $trade->questions()->with(['options', 'conditions'])->get();

        $quote = app(PricingEngine::class)->quoteItem($trade, $questions, [
            'surface_m2' => 40,
            'etendue' => 'murs_plafonds',
            'etat_support' => 'bon',
            'application' => 'rouleau',
            'fourniture' => 'client',
        ]);

        // 12 000 (base) + 40 × 250 (surface) + 4 500 (plafonds) = 26 500
        $this->assertSame(26500, $quote->minCents);
        $this->assertTrue($quote->isExact(), 'Sans « je ne sais pas », le prix ne doit pas être une fourchette.');

        // Chaque euro rattaché à une ligne : c'est ce qui désamorce les litiges.
        $this->assertSame(26500, collect($quote->lines)->sum('min_cents'));
    }

    /** La porte de sortie élargit la fourchette au lieu de bloquer — sur des données réelles. */
    public function test_an_unknown_answer_widens_the_seeded_estimate(): void
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail();
        $questions = $trade->questions()->with(['options', 'conditions'])->get();

        $quote = app(PricingEngine::class)->quoteItem($trade, $questions, [
            'surface_m2' => 40,
            'etendue' => ['unknown' => true],
            'application' => 'rouleau',
        ]);

        $this->assertFalse($quote->isExact());
        // L'écart est celui des options réelles : 0 € pour les murs seuls, 90 € pour le complet.
        $this->assertSame(9000, $quote->spreadCents());
    }

    /** Un métier au devis obligatoire n'annonce toujours aucun prix, données réelles comprises. */
    public function test_the_roofing_trade_still_refuses_to_estimate(): void
    {
        $trade = Trade::where('slug', 'roofing')->firstOrFail();

        $this->assertSame(PricingUnit::QUOTE_ONLY, $trade->pricing_unit);
        $this->assertTrue(
            app(PricingEngine::class)->quoteItem($trade, $trade->questions()->with('options')->get(), [])->quoteOnly,
        );
    }

    /** Les associations du mode multi-services portent le temps de séchage, pas seulement un ordre. */
    public function test_bundle_suggestions_carry_a_drying_delay(): void
    {
        $plumbing = Trade::where('slug', 'plumbing')->firstOrFail();

        $suggestion = $plumbing->bundleSuggestions()
            ->whereHas('suggestedTrade', fn ($q) => $q->where('slug', 'nettoyage-fin-chantier'))
            ->firstOrFail();

        $this->assertGreaterThan(0, $suggestion->default_sequence_gap_min);
    }

    /** Le seeder ENRICHIT les métiers existants, il ne les remplace pas. */
    public function test_it_never_duplicates_a_pre_existing_trade(): void
    {
        // Un semeur non idempotent duplique TOUT : la liste complete dit l'ampleur, pas seulement
        // que le premier metier est en double.
        $doublons = [];

        foreach (['peinture', 'plumbing', 'electrical', 'roofing', 'nettoyage', 'jardinage'] as $slug) {
            $n = Trade::where('slug', $slug)->count();

            if ($n !== 1) {
                $doublons[] = "{$slug} : {$n} exemplaires";
            }
        }

        $this->assertSame([], $doublons, 'Le semeur a duplique ces metiers.');
    }

    /** Le mode immédiat annonce sa majoration : elle se voit dans le prix, avant confirmation. */
    public function test_the_immediate_mode_costs_more_on_real_data(): void
    {
        $trade = Trade::where('slug', 'plumbing')->firstOrFail();
        $questions = $trade->questions()->with(['options', 'conditions'])->get();
        $answers = ['type_intervention' => 'fuite', 'acces' => 'libre', 'fourniture_piece' => 'prestataire'];

        $engine = app(PricingEngine::class);
        $scheduled = $engine->quoteItem($trade, $questions, $answers);
        $asap = $engine->quoteItem($trade, $questions, $answers, ['mode' => OrderMode::ASAP]);

        $this->assertGreaterThan($scheduled->minCents, $asap->minCents);
    }
}
