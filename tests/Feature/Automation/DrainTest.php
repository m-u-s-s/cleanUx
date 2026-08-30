<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationReevaluation;
use App\Models\AutomationRule;
use App\Services\Automation\FileDeReevaluation;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrainTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    private function regle(string $etat): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Alertes webhook backlog',
            'entite' => 'alerte',
            'declencheur' => 'alerte.webhook_backlog',
            'conditions' => ['field' => 'niveau', 'op' => 'eq', 'value' => 'critical'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => $etat,
        ]);
    }

    /** Graine directe (hors ecouteur) : sans elle, l'observation n'a rien a voir et l'armement est refuse. */
    private function seedAlerte(): AlerteMetier
    {
        return AlerteMetier::create([
            'cle' => 'seed_armement',
            'niveau' => 'critical',
            'message' => 'graine pour armement',
            'levee_le' => now(),
        ]);
    }

    public function test_une_alerte_levee_puis_un_passage_pose_une_action(): void
    {
        config()->set('features.automation', true);

        $this->seedAlerte();
        $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        BusinessAlerts::webhookBacklog(412);
        $alerte = AlerteMetier::where('cle', 'webhook_backlog')->sole();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('mode', 'armee')
            ->where('entite_type', 'alerte')
            ->where('entite_id', $alerte->id)
            ->count());
    }

    /** TEMOIN — sans alerte levee, le meme passage ne pose rien : la file event est vide. */
    public function test_temoin_sans_alerte_le_meme_passage_ne_pose_rien(): void
    {
        config()->set('features.automation', true);

        $this->seedAlerte();
        $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    public function test_la_file_est_vide_apres_le_passage(): void
    {
        config()->set('features.automation', true);

        $this->seedAlerte();
        $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        BusinessAlerts::webhookBacklog(412);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationReevaluation::count());
    }

    /** DEFAUT possible — sans restriction aux identifiants, la regle balaierait TOUTE la table
     *  (meme niveau critique) au lieu de la seule entite deposee. */
    public function test_une_regle_evenementielle_ne_voit_que_l_entite_deposee(): void
    {
        config()->set('features.automation', true);

        $this->seedAlerte();
        $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        // Distractrice : meme niveau critique, jamais deposee dans la file evenementielle.
        $distractrice = AlerteMetier::create([
            'cle' => 'payout_failed',
            'niveau' => 'critical',
            'message' => 'autre alerte, jamais deposee',
            'levee_le' => now(),
        ]);

        BusinessAlerts::webhookBacklog(412);
        $visee = AlerteMetier::where('cle', 'webhook_backlog')->sole();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('mode', 'armee')->where('entite_id', $visee->id)->count());
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->where('entite_id', $distractrice->id)->count());
    }

    public function test_un_depot_sans_regle_branchee_est_purge_quand_meme(): void
    {
        config()->set('features.automation', true);

        app(FileDeReevaluation::class)->deposer('evenement.sans.regle', 'alerte', 999);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationReevaluation::count());
        $this->assertSame(0, AutomationAction::count());
    }

    /** L'INTERRUPTEUR EST FERME : rien n'est draine, et la file n'est PAS purgee — sinon
     *  la coupure mangerait des evenements que personne n'a traites. */
    public function test_interrupteur_ferme_ne_draine_ni_ne_purge(): void
    {
        config()->set('features.automation', false);

        $this->seedAlerte();
        $this->armer($this->regle(AutomationRule::ETAT_ARMEE));

        BusinessAlerts::webhookBacklog(412);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationReevaluation::count(), 'La coupure ne doit pas manger les evenements.');
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }
}
