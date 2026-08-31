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
                    foreach ($groupes as $groupe) {
                        if ($groupe['evenement'] === 'alerte.webhook_backlog') {
                            $this->deposer('alerte.webhook_backlog', 'alerte', 777777);

                            break;
                        }
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

    /**
     * C1 — NON-REGRESSION. Le quota bride le balayage : aucune entite ne doit se perdre.
     * Depuis le correctif 2, le drain purge sur l'intersection des entites REELLEMENT
     * traitees (une seule regle ici : l'intersection vaut sa propre liste) — il ne garde
     * donc que la 3e alerte, jamais les trois, et les trois finissent servies.
     */
    public function test_le_quota_bride_le_passage_et_la_purge_garde_le_groupe_pour_le_suivant(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regle = $this->regleEvenementielle('alerte');
        $regle->forceFill(['quota_par_passage' => 2])->save();
        $regle = $this->armerParDrain($regle, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);
        $this->assertSame(3, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(2, AutomationAction::where('mode', 'armee')->count());
        $this->assertSame(
            1,
            AutomationReevaluation::count(),
            'Le passage bride doit purger les 2 lignes DEJA traitees et garder la 3e pour le suivant.'
        );

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(3, AutomationAction::where('mode', 'armee')->count());
        $this->assertSame(0, AutomationReevaluation::count(), 'Le second passage doit vider la file.');
    }

    /**
     * CORRECTIF 2 — LA FAMINE. Sous `chaque_passage`, le registre « deja agi » n'exclut
     * rien : sans purge partielle, les memes entites regagnent le quota indefiniment et la
     * derniere n'est jamais servie. La purge doit desormais liberer les entites deja
     * traitees a chaque passage, si bien que la file finit par se vider.
     */
    public function test_un_groupe_bride_sert_toutes_ses_entites_en_plusieurs_passages(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regle = $this->regleEvenementielle('alerte');
        $regle->forceFill(['quota_par_passage' => 2, 'politique_reprise' => 'chaque_passage'])->save();
        $regle = $this->armerParDrain($regle, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);
        $alertes = AlerteMetier::where('cle', 'webhook_backlog')->pluck('id')->all();
        $this->assertSame(3, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);
        $this->artisan('automation:executer')->assertExitCode(0);
        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            0,
            AutomationReevaluation::count(),
            'La file ne se vide jamais : les memes entites regagnent le quota a chaque passage.'
        );
        foreach ($alertes as $id) {
            $this->assertSame(
                1,
                AutomationAction::where('mode', 'armee')->where('entite_id', $id)->count(),
                "L'alerte {$id} n'a jamais ete servie."
            );
        }
    }

    /** TEMOIN — meme regle, quota suffisant : `chaque_passage` sert les trois d'un coup et
     *  vide la file des le premier passage. */
    public function test_temoin_chaque_passage_avec_quota_suffisant_vide_la_file_au_premier_passage(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regle = $this->regleEvenementielle('alerte');
        $regle->forceFill(['quota_par_passage' => 10, 'politique_reprise' => 'chaque_passage'])->save();
        $regle = $this->armerParDrain($regle, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(3, AutomationAction::where('mode', 'armee')->count());
        $this->assertSame(0, AutomationReevaluation::count());
    }

    /**
     * CORRECTIF 2 — INTERSECTION, PAS UNION. Deux regles du meme groupe peuvent traiter des
     * sous-ensembles differents (quotas differents) : seule l'entite traitee par TOUTES les
     * regles est finie. Ici B (quota 1) ne traite qu'une entite, incluse dans les deux de A
     * (quota 2) : seule celle-la doit purger.
     */
    public function test_le_drain_purge_l_intersection_des_regles_pas_leur_union(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleA = $this->regleEvenementielle('alerte');
        $regleA->forceFill(['quota_par_passage' => 2])->save();
        $this->armerParDrain($regleA, [$graine->id]);

        $regleB = $this->regleEvenementielle('alerte');
        $regleB->forceFill(['quota_par_passage' => 1])->save();
        $this->armerParDrain($regleB, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);
        $this->assertSame(3, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            2,
            AutomationReevaluation::count(),
            "L'union purgerait 2 lignes ; l'intersection n'en purge qu'une, la seule commune aux deux regles."
        );
    }

    /**
     * CORRECTIF 2 — PASSAGES EN ECHEC IGNORES. Une regle armee SANS observation refuse en
     * amont : `echec` avec une liste vide. Sans l'exclure de l'intersection, elle viderait
     * l'ensemble commun pour toujours et bloquerait la purge decidee par l'autre regle.
     */
    public function test_un_passage_en_echec_n_empeche_pas_la_purge_de_l_autre_regle(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleBonne = $this->regleEvenementielle('alerte');
        $this->armerParDrain($regleBonne, [$graine->id]);

        // Armee directement, SANS observation : refus en amont garanti (voir le piege de
        // l'armement documente dans ArmeSesRegles) — statut `echec`, liste vide.
        $regleCassee = $this->regleEvenementielle('alerte');
        $regleCassee->forceFill(['etat' => AutomationRule::ETAT_ARMEE])->save();

        BusinessAlerts::webhookBacklog(412);
        $this->assertSame(1, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            0,
            AutomationReevaluation::count(),
            "Le passage en echec de la regle cassee ne doit pas retenir la ligne traitee par l'autre regle."
        );
    }

    /**
     * CORRECTIF 2, TOUR 2 — LA FAMINE UN CRAN PLUS HAUT. Sous `une_fois`, une regle qui a
     * deja fini avec une entite ne la balaie plus JAMAIS : compter seulement ce qui est
     * balaye CE tour la ferait disparaitre de `entites_finies` a chaque tour suivant, et
     * l'intersection ne se reconstituerait jamais tant qu'une AUTRE regle reste bridee.
     */
    public function test_deux_regles_une_fois_avec_des_quotas_differents_finissent_par_tout_purger(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleA = $this->regleEvenementielle('alerte');
        $regleA->forceFill(['quota_par_passage' => 10])->save();
        $regleA = $this->armerParDrain($regleA, [$graine->id]);

        $regleB = $this->regleEvenementielle('alerte');
        $regleB->forceFill(['quota_par_passage' => 1])->save();
        $regleB = $this->armerParDrain($regleB, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);
        $alertes = AlerteMetier::where('cle', 'webhook_backlog')->pluck('id')->all();
        $this->assertSame(3, AutomationReevaluation::count());

        $this->artisan('automation:executer')->assertExitCode(0);
        $this->artisan('automation:executer')->assertExitCode(0);
        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            0,
            AutomationReevaluation::count(),
            'La file ne se vide jamais : la regle au quota large ne balaie plus les entites deja finies.'
        );
        foreach ($alertes as $id) {
            $this->assertSame(
                1,
                AutomationAction::where('automation_rule_id', $regleA->id)->where('mode', 'armee')->where('entite_id', $id)->count(),
                "La regle A n'a pas servi l'alerte {$id}."
            );
            $this->assertSame(
                1,
                AutomationAction::where('automation_rule_id', $regleB->id)->where('mode', 'armee')->where('entite_id', $id)->count(),
                "La regle B n'a pas servi l'alerte {$id}."
            );
        }
    }

    /** TEMOIN — deux quotas larges : les deux regles servent tout au premier passage, file vide. */
    public function test_temoin_deux_regles_une_fois_avec_des_quotas_larges_vident_la_file_au_premier_passage(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleA = $this->regleEvenementielle('alerte');
        $regleA->forceFill(['quota_par_passage' => 10])->save();
        $regleA = $this->armerParDrain($regleA, [$graine->id]);

        $regleB = $this->regleEvenementielle('alerte');
        $regleB->forceFill(['quota_par_passage' => 10])->save();
        $regleB = $this->armerParDrain($regleB, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationReevaluation::count());
        $this->assertSame(3, AutomationAction::where('automation_rule_id', $regleA->id)->where('mode', 'armee')->count());
        $this->assertSame(3, AutomationAction::where('automation_rule_id', $regleB->id)->where('mode', 'armee')->count());
    }

    /** TEMOIN — quota suffisant : le premier passage traite tout et vide la file. */
    public function test_temoin_quota_suffisant_le_premier_passage_vide_la_file(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regle = $this->regleEvenementielle('alerte');
        $regle->forceFill(['quota_par_passage' => 10])->save();
        $regle = $this->armerParDrain($regle, [$graine->id]);

        BusinessAlerts::webhookBacklog(1);
        BusinessAlerts::webhookBacklog(2);
        BusinessAlerts::webhookBacklog(3);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(3, AutomationAction::where('mode', 'armee')->count());
        $this->assertSame(0, AutomationReevaluation::count());
    }

    /**
     * C4 — une regle evenementielle dont les conditions levent (arbre trop large) ne doit
     * pas empecher une regle de cadence de tourner dans le MEME passage : le drain passe
     * en premier, une regle empoisonnee bloquerait sinon tout le moteur, chaque minute.
     */
    public function test_une_regle_evenementielle_qui_leve_n_empeche_pas_la_cadence_de_tourner(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleCassee = $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);
        $feuille = ['field' => 'cle', 'op' => 'eq', 'value' => 'seed_armement'];
        $regleCassee->forceFill(['conditions' => ['and' => array_fill(0, 201, $feuille)]])->save();

        Booking::factory()->create(['status' => 'en_attente']);
        $regleCadence = $this->armer($this->regleCadence());
        $regleCadence->forceFill(['dernier_passage_le' => null])->save();

        BusinessAlerts::webhookBacklog(412);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            1,
            AutomationAction::where('automation_rule_id', $regleCadence->id)->where('mode', 'armee')->count(),
            'La regle cassee ne doit pas empecher la regle de cadence de tourner.'
        );
    }

    /** TEMOIN — sans regle cassee, la regle evenementielle ET la regle de cadence tournent toutes les deux. */
    public function test_temoin_une_regle_evenementielle_saine_et_une_cadence_saine_tournent_toutes_les_deux(): void
    {
        config()->set('features.automation', true);

        $graine = $this->seedAlerte();
        $regleEvenement = $this->armerParDrain($this->regleEvenementielle('alerte'), [$graine->id]);

        Booking::factory()->create(['status' => 'en_attente']);
        $regleCadence = $this->armer($this->regleCadence());
        $regleCadence->forceFill(['dernier_passage_le' => null])->save();

        BusinessAlerts::webhookBacklog(412);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('automation_rule_id', $regleEvenement->id)->where('mode', 'armee')->count());
        $this->assertSame(1, AutomationAction::where('automation_rule_id', $regleCadence->id)->where('mode', 'armee')->count());
    }

    /**
     * C4 — meme regle si l'echec vient de la boucle des CADENCES : une regle cassee n'empeche
     * pas une deuxieme regle de cadence, saine, de tourner dans le meme passage.
     */
    public function test_une_regle_de_cadence_qui_leve_n_empeche_pas_une_autre_regle_de_cadence(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);

        $feuille = ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'];
        $regleCassee = $this->armer($this->regleCadence());
        $regleCassee->forceFill([
            'conditions' => ['and' => array_fill(0, 201, $feuille)],
            'dernier_passage_le' => null,
        ])->save();

        $regleSaine = $this->armer($this->regleCadence());
        $regleSaine->forceFill(['dernier_passage_le' => null])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(
            1,
            AutomationAction::where('automation_rule_id', $regleSaine->id)->where('mode', 'armee')->count(),
            'La regle cassee ne doit pas empecher une autre regle de cadence de tourner.'
        );
    }

    /** TEMOIN — deux regles de cadence saines tournent toutes les deux dans le meme passage. */
    public function test_temoin_deux_regles_de_cadence_saines_tournent_toutes_les_deux(): void
    {
        config()->set('features.automation', true);

        // UNE seule reservation : chaque regle la voit independamment (pas de partage de quota
        // entre regles), donc une action chacune plutot que deux si on en avait cree deux.
        Booking::factory()->create(['status' => 'en_attente']);

        $premiere = $this->armer($this->regleCadence());
        $premiere->forceFill(['dernier_passage_le' => null])->save();

        $seconde = $this->armer($this->regleCadence());
        $seconde->forceFill(['dernier_passage_le' => null])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('automation_rule_id', $premiere->id)->where('mode', 'armee')->count());
        $this->assertSame(1, AutomationAction::where('automation_rule_id', $seconde->id)->where('mode', 'armee')->count());
    }
}
