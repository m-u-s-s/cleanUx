<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le supplément d'une réponse arrive bien dans le prix — et seulement si on la choisit.
 *
 * POURQUOI CE TEST EXISTE À CÔTÉ DE CELUI DE L'ÉCRAN. L'écran peut enregistrer parfaitement une
 * valeur qui n'atteint jamais le devis. Ces deux vérités sont indépendantes, et seule la seconde
 * intéresse le client.
 *
 * C'est aussi ce qui distingue le prix de l'OPTION de celui de la QUESTION : le mode `add` posé sur
 * une question ajoute son montant dès qu'elle est répondue, donc aussi quand on répond « Non ».
 * Le test ci-dessous fixe cette distinction pour de bon.
 */
class OptionSupplementReachesPriceTest extends TestCase
{
    use RefreshDatabase;

    private Trade $trade;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        $this->trade = Trade::query()->firstOrFail();
        $this->trade->update(['base_price_cents' => 10000]);

        $this->question = Question::create([
            'trade_id' => $this->trade->id,
            'code' => 'installation',
            'label' => 'Voulez-vous l’installation ?',
            'type' => QuestionType::BOOLEAN,
            'is_required' => true,
            'sort_order' => 99,
            'is_active' => true,
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'Oui',
            'value' => 'oui',
            'price_modifier_cents' => 15000,
            'sort_order' => 0,
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'Non',
            'value' => 'non',
            'price_modifier_cents' => 0,
            'sort_order' => 1,
            'is_default' => true,
        ]);
    }

    /** @param array<string, mixed> $reponses */
    private function devis(array $reponses): PriceBreakdown
    {
        return app(PricingEngine::class)->quoteItem(
            $this->trade,
            $this->question->newCollection([$this->question->load('options')]),
            $reponses,
        );
    }

    public function test_repondre_oui_ajoute_le_supplement(): void
    {
        $devis = $this->devis(['installation' => 'oui']);

        // 100 € de prestation + 150 € d'installation.
        $this->assertSame(25000, $devis->minCents);
        $this->assertSame(25000, $devis->maxCents);
    }

    public function test_repondre_non_n_ajoute_rien(): void
    {
        $devis = $this->devis(['installation' => 'non']);

        /*
         * LE CŒUR DE L'AFFAIRE. Un montant posé sur la QUESTION (mode `add`) s'ajouterait ici
         * aussi, puisqu'il s'applique dès qu'elle est répondue. Posé sur l'OPTION, il suit le
         * choix du client — ce que tout le monde attend en lisant « Voulez-vous l'installation ? ».
         */
        $this->assertSame(10000, $devis->minCents);
    }

    public function test_le_supplement_apparait_nommement_dans_le_detail(): void
    {
        $devis = $this->devis(['installation' => 'oui']);

        // Un montant qu'on ne sait pas rattacher à une ligne est un montant que le client
        // conteste, et à raison.
        $libelles = collect($devis->lines)->pluck('label')->implode(' | ');

        $this->assertStringContainsString('installation', mb_strtolower($libelles));
    }

    public function test_un_supplement_negatif_retire_du_prix(): void
    {
        QuestionOption::where('question_id', $this->question->id)
            ->where('value', 'oui')
            ->update(['price_modifier_cents' => -2000]);

        $devis = $this->devis(['installation' => 'oui']);

        // « Je fournis le matériel » : une option peut retirer du prix.
        $this->assertSame(8000, $devis->minCents);
    }
}
