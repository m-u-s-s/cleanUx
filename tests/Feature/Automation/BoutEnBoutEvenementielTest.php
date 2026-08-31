<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationReevaluation;
use App\Models\AutomationRule;
use App\Models\ProviderPayout;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le chemin entier, sans raccourci : evenement metier -> ecouteur -> file -> drain -> action. */
class BoutEnBoutEvenementielTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    private function regleVersementEnEchec(): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Versement prestataire en echec',
            'entite' => 'alerte',
            'declencheur' => 'alerte.payout_failed',
            'conditions' => [],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_BROUILLON,
        ]);
    }

    /** Graine, pour donner a l'observation restreinte quelque chose a voir avant l'armement. */
    private function seedAlerte(): AlerteMetier
    {
        return AlerteMetier::create([
            'cle' => 'seed_armement',
            'niveau' => 'critical',
            'message' => 'graine pour armement',
            'levee_le' => now(),
        ]);
    }

    public function test_un_versement_en_echec_traverse_tout_le_chemin(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regle = $this->armerParDrain($this->regleVersementEnEchec(), [$graine->id]);

        // Presente dans la meme table, une autre cle : ne doit rien recevoir.
        $distractrice = AlerteMetier::create([
            'cle' => 'webhook_backlog',
            'niveau' => 'critical',
            'message' => 'distractrice, autre cle',
            'levee_le' => now(),
        ]);

        $payout = ProviderPayout::factory()->failed()->create();
        BusinessAlerts::payoutFailed($payout);

        $alerte = AlerteMetier::where('cle', 'payout_failed')->sole();
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('automation_rule_id', $regle->id)
            ->where('mode', 'armee')
            ->where('resultat', AutomationAction::RESULTAT_EXECUTEE)
            ->where('entite_id', $alerte->id)
            ->count());

        $this->assertSame(0, AutomationReevaluation::count());

        // Ni la graine d'armement ni la distractrice n'ont recu d'action armee.
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->where('entite_id', $graine->id)->count());
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->where('entite_id', $distractrice->id)->count());
    }

    /** TEMOIN — l'interrupteur ferme : la meme chaine ne pose rien. */
    public function test_temoin_interrupteur_ferme_ne_pose_rien(): void
    {
        config()->set('features.automation', false);

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleVersementEnEchec(), [$graine->id]);

        $payout = ProviderPayout::factory()->failed()->create();
        BusinessAlerts::payoutFailed($payout);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }
}
