<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocleTest extends TestCase
{
    use RefreshDatabase;

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Missions sans intervenant',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ], $attributs));
    }

    public function test_une_regle_nait_en_brouillon_avec_ses_defauts(): void
    {
        $regle = $this->regle();

        $this->assertSame('brouillon', $regle->etat);
        $this->assertSame('une_fois', $regle->politique_reprise);
        $this->assertSame(50, $regle->quota_par_passage);
        $this->assertSame(0, $regle->plafonds_consecutifs);
    }

    public function test_les_colonnes_json_se_relisent_en_tableau(): void
    {
        $regle = $this->regle()->fresh();

        $this->assertSame('en_attente', $regle->conditions['value']);
        $this->assertSame('journaliser', $regle->actions[0]['cle']);
    }

    public function test_un_passage_et_ses_actions_appartiennent_a_leur_regle(): void
    {
        $regle = $this->regle();

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'observation',
            'demarre_le' => now(),
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 42,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => 'simulee',
            'pose_le' => now(),
        ]);

        $this->assertSame(1, $regle->passages()->count());
        $this->assertSame(1, $regle->actionsPosees()->count());
        $this->assertSame($regle->id, AutomationAction::first()->regle->id);
    }

    /** TEMOIN — supprimer la regle emporte son journal, la contrainte est bien posee. */
    public function test_temoin_supprimer_une_regle_emporte_ses_lignes(): void
    {
        $regle = $this->regle();

        AutomationRun::create(['automation_rule_id' => $regle->id, 'mode' => 'observation', 'demarre_le' => now()]);

        $regle->delete();

        $this->assertSame(0, AutomationRun::count());
    }
}
