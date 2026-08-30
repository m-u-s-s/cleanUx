<?php

namespace Tests\Feature\Marketing;

use App\Models\ActivityLog;
use App\Models\MarketingSegment;
use App\Models\User;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\SegmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le rattrapage de RuleTreeTooComplex dans SegmentEngine n'etait exerce par aucun test. */
class SegmentEngineRuleTreeTooComplexTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> 201 noeuds : la racine plus 200 feuilles, au-dela de NOEUDS_MAX. */
    protected function arbreTropLarge(): array
    {
        $feuille = ['field' => 'role', 'op' => 'eq', 'value' => 'client'];

        return ['and' => array_fill(0, RuleTreeEvaluator::NOEUDS_MAX, $feuille)];
    }

    protected function makeSegment(array $rules): MarketingSegment
    {
        return MarketingSegment::create([
            'code' => 'seg_'.uniqid(),
            'name' => 'Trop large',
            'rules' => $rules,
            'is_active' => true,
        ]);
    }

    public function test_compute_rejette_un_arbre_trop_large_et_journalise(): void
    {
        User::factory()->client()->count(3)->create();
        $segment = $this->makeSegment($this->arbreTropLarge());

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(0, $count);
        $this->assertTrue(ActivityLog::where('action', 'marketing.segment_rejected')->exists());
    }

    public function test_preview_rejette_un_arbre_trop_large(): void
    {
        User::factory()->client()->count(3)->create();

        $preview = app(SegmentEngine::class)->preview($this->arbreTropLarge());

        $this->assertSame(['count' => 0, 'sample' => []], $preview);
    }

    /** TEMOIN compute() — sans lui, le rejet ci-dessus passerait au vert sur un moteur casse. */
    public function test_temoin_compute_regles_normales_rend_un_resultat_non_nul(): void
    {
        User::factory()->client()->count(3)->create();
        $segment = $this->makeSegment(['field' => 'role', 'op' => 'eq', 'value' => 'client']);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(3, $count);
    }

    /** TEMOIN preview() — distinct du precedent : mutation-teste, un preview() muet
     *  a `['count' => 0, 'sample' => []]` passait les 3 autres tests du fichier. */
    public function test_temoin_preview_regles_normales_rend_un_resultat_non_vide(): void
    {
        User::factory()->client()->count(3)->create();

        $preview = app(SegmentEngine::class)->preview(['field' => 'role', 'op' => 'eq', 'value' => 'client']);

        $this->assertNotSame(0, $preview['count']);
        $this->assertNotEmpty($preview['sample']);
    }
}
