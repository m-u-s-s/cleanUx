<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationReevaluation;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Booking;
use App\Services\Automation\FileDeReevaluation;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrainTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    /** Sans condition : legitime pour une regle evenementielle, restreinte par les identifiants du drain. */
    private function regleEvenementielle(string $entite, string $declencheur = 'alerte.webhook_backlog'): AutomationRule
    {
        return AutomationRule::create([
            'nom' => "Regle evenementielle ({$entite})",
            'entite' => $entite,
            'declencheur' => $declencheur,
            'conditions' => [],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_BROUILLON,
        ]);
    }

    private function regleCadence(): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'chaque_minute',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_BROUILLON,
        ]);
    }

    /** Graine directe (hors ecouteur), pour donner a l'observation restreinte quelque chose a voir. */
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

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

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

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    public function test_la_file_est_vide_apres_le_passage(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        BusinessAlerts::webhookBacklog(412);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationReevaluation::count());
    }

    /** Conditions vides : sans la restriction aux identifiants du drain, la regle balaierait
     *  TOUTE la table (meme niveau critique) au lieu de la seule entite deposee. */
    public function test_une_regle_evenementielle_ne_voit_que_l_entite_deposee(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        // Distractrice : jamais deposee dans la file evenementielle.
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

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        BusinessAlerts::webhookBacklog(412);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationReevaluation::count(), 'La coupure ne doit pas manger les evenements.');
        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    /**
     * TOUR 1, POINT 1 — l'ordre drain -> cadence, mesure par l'ordre des passages ecrits.
     * Choix : `automation_runs.id` croit dans l'ordre d'execution (AUTO_INCREMENT / rowid) ;
     * comparer les DEUX id ne peut passer que dans UN seul ordre, jamais dans les deux — a
     * la difference de compter juste le nombre de passages, qui resterait vert quel que soit
     * l'ordre. Mutation verifiee : deplacer `drainer()` apres la boucle des cadences fait
     * tomber cette assertion (voir le rapport).
     */
    public function test_le_drain_passe_avant_la_cadence(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleEvenement = $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        Booking::factory()->create(['status' => 'en_attente']);
        $regleCadence = $this->armer($this->regleCadence());
        // Remis a null : l'armement vient d'ecrire ce champ, on isole CE tour comme du.
        $regleCadence->forceFill(['dernier_passage_le' => null])->save();

        BusinessAlerts::webhookBacklog(412);

        $avant = (int) (AutomationRun::max('id') ?? 0);

        $this->artisan('automation:executer')->assertExitCode(0);

        $runs = AutomationRun::where('id', '>', $avant)->orderBy('id')->get();

        $this->assertCount(2, $runs, 'Un seul passage attendu par regle active a ce tour.');
        $this->assertSame(
            $regleEvenement->id,
            $runs[0]->automation_rule_id,
            'Le passage du drain doit etre ecrit EN PREMIER, avant celui de la cadence.'
        );
        $this->assertSame($regleCadence->id, $runs[1]->automation_rule_id);
    }

    /**
     * TOUR 1, POINT 2 — la purge doit se limiter aux LIGNES LUES par CE passage, pas a tout
     * l'evenement. Faute d'un crochet reel declenche par le passage lui-meme, on simule un
     * depot CONCURRENT en decorant `FileDeReevaluation::parEvenement()` : il ecrit une
     * ligne neuve juste apres la lecture que le drain va traiter, avant que `purger()` ne
     * soit appelee. Documente ici : c'est un artifice de test, pas un chemin de production.
     */
    public function test_une_ligne_deposee_pendant_le_passage_n_est_pas_purgee(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        BusinessAlerts::webhookBacklog(412);
        $originale = AutomationReevaluation::sole();

        $this->app->bind(FileDeReevaluation::class, function () {
            return new class extends FileDeReevaluation
            {
                public function parEvenement(): array
                {
                    $groupes = parent::parEvenement();

                    // Depot concurrent, SUR LE MEME EVENEMENT : arrive apres cette lecture,
                    // ne doit pas etre emporte par la purge du groupe deja lu.
                    if (isset($groupes['alerte.webhook_backlog'])) {
                        $this->deposer('alerte.webhook_backlog', 'alerte', 777777);
                    }

                    return $groupes;
                }
            };
        });

        $this->artisan('automation:executer')->assertExitCode(0);

        $restante = AutomationReevaluation::sole();
        $this->assertNotSame($originale->id, $restante->id, 'La ligne concurrente a ete purgee sans avoir ete lue.');
        $this->assertSame(777777, $restante->entite_id);
    }

    /**
     * TOUR 1, POINT 3 — le drain doit filtrer par ENTITE, pas seulement par declencheur.
     * La regle « booking » est armee avec le MEME identifiant numerique que l'alerte reelle
     * (coincidence forcee expres) : sans le filtre d'entite, `whereKey()` sur `bookings`
     * trouverait cette reservation et agirait dessus a tort.
     */
    public function test_une_regle_sur_une_autre_entite_n_agit_pas(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleAlerte = $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        BusinessAlerts::webhookBacklog(412);
        $alerte = AlerteMetier::where('cle', 'webhook_backlog')->sole();

        // Coincidence forcee : le meme id numerique que l'alerte deposee.
        $bookingCoincident = Booking::factory()->create(['id' => $alerte->id, 'status' => 'en_attente']);
        $regleBooking = $this->armerParDrain(
            $this->regleEvenementielle('booking'),
            [$bookingCoincident->id]
        );

        $this->artisan('automation:executer')->assertExitCode(0);

        // TEMOIN — la regle sur la bonne entite agit bien : sans lui, un zero partout
        // pourrait aussi bien mesurer une regle qui n'agit jamais.
        $this->assertSame(
            1,
            AutomationAction::where('automation_rule_id', $regleAlerte->id)->where('mode', 'armee')->count()
        );
        $this->assertSame(
            0,
            AutomationAction::where('automation_rule_id', $regleBooking->id)->where('mode', 'armee')->count()
        );
    }
}
