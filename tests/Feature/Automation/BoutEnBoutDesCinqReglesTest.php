<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Mission;
use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\ReglagesDActions;
use App\Support\Alerts\BusinessAlerts;
use Closure;
use Database\Seeders\ReglesDAlerteMetierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Sentry\State\Scope;
use Tests\TestCase;

/**
 * Le chemin complet, sans raccourci, pour deux des cinq règles semées : une qui porte une
 * entité liée (`stuck_mission_holding_funds`) et une qui n'en porte aucune (`webhook_backlog`).
 * Leurs émetteurs restent muets aujourd'hui : on lève l'alerte directement, comme la référence.
 */
class BoutEnBoutDesCinqReglesTest extends TestCase
{
    use RefreshDatabase;

    /** Espion Sentry minimal, identique à CeQueLesAlertesFontAujourdHuiTest : rejoue le
     *  callback avec un vrai Scope, capture chaque message. */
    private function sentrySpy(): object
    {
        return new class
        {
            /** @var list<array{message: string, level: mixed}> */
            public array $captured = [];

            public function withScope(callable $callback): void
            {
                $callback(new Scope);
            }

            public function captureMessage(string $message, mixed $level = null): void
            {
                $this->captured[] = ['message' => $message, 'level' => $level];
            }
        };
    }

    /** @param list<array{message: string, level: mixed}> $captured
     *  @return list<array{message: string, level: mixed}> */
    private function messagesSentryPour(array $captured, string $cle): array
    {
        return array_values(array_filter(
            $captured,
            fn (array $appel): bool => str_contains($appel['message'], "[{$cle}]"),
        ));
    }

    private function regleSeedee(string $cle): AutomationRule
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        return AutomationRule::query()
            ->where('cle_de_reference', "systeme.alerte_metier.{$cle}")
            ->firstOrFail();
    }

    /**
     * Le chemin entier : brouillon -> observation réelle -> première alerte réelle -> commande
     * réelle -> ligne `simulee` -> armement réel -> seconde alerte -> commande réelle -> ligne
     * `executee` et notification. Sans Event::fake() : les deux écouteurs réels tournent.
     *
     * @return array{alerte1: AlerteMetier, alerte2: AlerteMetier}
     */
    private function cheminComplet(string $cle, Closure $leveUneAlerte): array
    {
        config()->set('features.automation', true);
        Notification::fake();

        $sentry = $this->sentrySpy();
        app()->instance('sentry', $sentry);

        $regle = $this->regleSeedee($cle);
        $this->assertSame(AutomationRule::ETAT_BROUILLON, $regle->etat, 'La règle semée naît en brouillon.');

        $auteur = User::factory()->create();
        app(ReglagesDActions::class)->basculer('notifier.admins', true, $auteur);
        $admin = User::factory()->admin()->create(['is_active' => true]);

        // Un administrateur la met en observation, par le chemin réel — jamais un forceFill.
        app(EtatDeRegle::class)->observer($regle);

        $leveUneAlerte();
        $alerte1 = AlerteMetier::where('cle', $cle)->sole();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', 'observation')
            ->where('resultat', AutomationAction::RESULTAT_SIMULEE)
            ->where('entite_id', $alerte1->id)
            ->count(), 'Une ligne `simulee` doit paraître au journal après le premier passage.');
        $this->assertSame(0, AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_EXECUTEE)->count());

        // La règle s'arme par le chemin réel : l'observation ci-dessus lui a donné de quoi lire.
        app(EtatDeRegle::class)->armer($regle->fresh());
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);

        $leveUneAlerte();
        $alerte2 = AlerteMetier::where('cle', $cle)->where('id', '!=', $alerte1->id)->sole();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', 'armee')
            ->where('resultat', AutomationAction::RESULTAT_EXECUTEE)
            ->where('entite_id', $alerte2->id)
            ->count(), 'Une ligne `executee` doit paraître au journal après le second passage.');

        Notification::assertSentTo($admin, RegleDeclencheeNotification::class);

        // TÉMOIN — la voie Sentry reçoit toujours les deux alertes : la règle s'AJOUTE, elle ne remplace rien.
        $messages = $this->messagesSentryPour($sentry->captured, $cle);
        $this->assertCount(2, $messages, 'Sentry doit recevoir un message par alerte levée, comme avant la règle.');
        foreach ($messages as $message) {
            $this->assertSame('fatal', (string) $message['level']);
        }

        return ['alerte1' => $alerte1, 'alerte2' => $alerte2];
    }

    public function test_mission_bloquee_retenant_des_fonds_du_brouillon_a_l_execution(): void
    {
        $mission = Mission::factory()->create();

        $resultat = $this->cheminComplet(
            'stuck_mission_holding_funds',
            fn () => BusinessAlerts::stuckMissionHoldingFunds($mission),
        );

        // L'ENTITÉ LIÉE : le contexte de l'alerte porte `mission_id`, dénormalisé sur la ligne.
        $this->assertSame('mission', $resultat['alerte1']->entite_type);
        $this->assertSame($mission->id, $resultat['alerte1']->entite_id);
        $this->assertSame($mission->id, $resultat['alerte1']->contexte['mission_id']);
    }

    public function test_file_de_webhooks_qui_deborde_du_brouillon_a_l_execution(): void
    {
        $resultat = $this->cheminComplet(
            'webhook_backlog',
            fn () => BusinessAlerts::webhookBacklog(412),
        );

        // AUCUNE ENTITÉ LIÉE — à l'opposé de la mission bloquée, nommé explicitement dans le brief.
        $this->assertNull($resultat['alerte1']->entite_type);
        $this->assertNull($resultat['alerte1']->entite_id);
    }
}
