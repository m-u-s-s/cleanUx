<?php

namespace Tests\Feature\Automation;

use App\Events\BusinessAlertRaised;
use App\Listeners\Alerts\BusinessAlertSentryListener;
use App\Listeners\Automation\EnregistrerLAlerteMetier;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Providers\EventServiceProvider;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use Sentry\State\Scope;
use Tests\TestCase;

/**
 * Reference figee de ce que les cinq alertes du chemin de l'argent font AUJOURD'HUI, sans
 * aucune regle admin (phase 5). La phase suivante comparera ses regles a CE fichier.
 */
class CeQueLesAlertesFontAujourdHuiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fait tourner les DEUX ecouteurs reels (Sentry + persistance) sans Event::fake(), qui les
     * aurait coupes. Un troisieme ecouteur, a nous, capture l'objet evenement pour l'inspecter.
     *
     * @return array{captures: list<BusinessAlertRaised>, sentry: object}
     */
    private function observeDeuxAlertes(callable $leveMesuree, callable $leveDistractrice): array
    {
        $captures = [];
        Event::listen(BusinessAlertRaised::class, function (BusinessAlertRaised $e) use (&$captures): void {
            $captures[] = $e;
        });

        $sentry = $this->sentrySpy();
        app()->instance('sentry', $sentry);

        $leveMesuree();
        $leveDistractrice();

        return ['captures' => $captures, 'sentry' => $sentry];
    }

    /** Espion Sentry minimal : rejoue le callback avec un vrai Scope, capture chaque message. */
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

    /** @param list<BusinessAlertRaised> $captures */
    private function uneCapture(array $captures, string $cle): BusinessAlertRaised
    {
        $trouvees = array_values(array_filter($captures, fn (BusinessAlertRaised $e): bool => $e->key === $cle));

        $this->assertCount(1, $trouvees, "Un seul BusinessAlertRaised attendu pour la cle « {$cle} ».");

        return $trouvees[0];
    }

    /**
     * @param  list<array{message: string, level: mixed}>  $captured
     * @return array{message: string, level: mixed}
     */
    private function messageSentryPour(array $captured, string $cle): array
    {
        $trouves = array_values(array_filter(
            $captured,
            fn (array $appel): bool => str_contains($appel['message'], "[{$cle}]"),
        ));

        $this->assertCount(1, $trouves, "La voie Sentry devrait recevoir exactement un message pour « {$cle} ».");

        return $trouves[0];
    }

    /** ANCRE structurelle : si l'un des deux disparait, la description ci-dessous ment. */
    public function test_les_deux_ecouteurs_sont_branches_sur_l_evenement(): void
    {
        $table = (new ReflectionClass(EventServiceProvider::class))->getDefaultProperties()['listen'];

        $this->assertSame(
            [BusinessAlertSentryListener::class, EnregistrerLAlerteMetier::class],
            $table[BusinessAlertRaised::class] ?? [],
            'Aujourd hui, ces deux ecouteurs et eux seuls repondent a une alerte metier.',
        );
    }

    public function test_paiement_capture_en_echec(): void
    {
        // refresh() : la factory ne pose pas `currency`, la colonne ne vit qu'en DEFAULT SQL —
        // un appelant reel recevrait un Booking rechargé, jamais l'instance brute du create().
        $booking = Booking::factory()->create()->refresh();

        // `payment_amount_cents` est pose en meme temps que l'intention Stripe : toute
        // reservation qui peut voir sa capture echouer le porte deja.
        $booking->forceFill(['payment_amount_cents' => 9000, 'estimated_price' => 75.00])->save();
        $booking->refresh();

        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::paymentCaptureFailed($booking),
            fn () => BusinessAlerts::webhookBacklog(999),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'payment_capture_failed');
        $this->uneCapture($resultat['captures'], 'webhook_backlog'); // temoin : distincte, pas confondue

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(
            ['booking_id', 'client_id', 'amount', 'currency'],
            array_keys($mesuree->context),
        );
        $this->assertSame($booking->id, $mesuree->context['booking_id']);
        $this->assertSame($booking->client_id, $mesuree->context['client_id']);
        $this->assertSame('EUR', $mesuree->context['currency']);
        // Le montant autorise, celui dont la capture a echoue — pas l'estime, qui n'est qu'un repli.
        $this->assertSame(90.00, $mesuree->context['amount']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'payment_capture_failed');
        $this->messageSentryPour($resultat['sentry']->captured, 'webhook_backlog'); // temoin cote Sentry
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseCount('business_alertes', 2);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'payment_capture_failed', 'niveau' => 'critical']);
    }

    /** TEMOIN : sans montant autorise, l'alerte dit l'estime — jamais `null`, qui ne dit rien. */
    public function test_temoin_sans_prix_final_l_alerte_dit_le_prix_estime(): void
    {
        $booking = Booking::factory()->create()->refresh();
        $booking->forceFill(['payment_amount_cents' => null, 'estimated_price' => 75.00])->save();
        $booking->refresh();

        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::paymentCaptureFailed($booking),
            fn () => BusinessAlerts::webhookBacklog(999),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'payment_capture_failed');

        $this->assertSame(75.00, $mesuree->context['amount']);
    }

    public function test_versement_prestataire_en_echec(): void
    {
        $payout = ProviderPayout::factory()->failed()->create();

        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::payoutFailed($payout),
            fn () => BusinessAlerts::webhookBacklog(999),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'payout_failed');
        $this->uneCapture($resultat['captures'], 'webhook_backlog');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(
            ['payout_id', 'provider_user_id', 'amount', 'currency', 'provider_payout_id'],
            array_keys($mesuree->context),
        );
        $this->assertSame($payout->id, $mesuree->context['payout_id']);
        $this->assertSame($payout->provider_user_id, $mesuree->context['provider_user_id']);
        $this->assertEquals($payout->amount, $mesuree->context['amount']);
        $this->assertSame($payout->currency, $mesuree->context['currency']);
        $this->assertNull($mesuree->context['provider_payout_id']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'payout_failed');
        $this->messageSentryPour($resultat['sentry']->captured, 'webhook_backlog');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseCount('business_alertes', 2);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'payout_failed', 'niveau' => 'critical']);
    }

    public function test_file_de_webhooks_qui_deborde(): void
    {
        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::webhookBacklog(412),
            fn () => BusinessAlerts::reconciliationDivergence(['delta' => 1]),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'webhook_backlog');
        $this->uneCapture($resultat['captures'], 'reconciliation_divergence');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(['count'], array_keys($mesuree->context));
        $this->assertSame(412, $mesuree->context['count']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'webhook_backlog');
        $this->messageSentryPour($resultat['sentry']->captured, 'reconciliation_divergence');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseCount('business_alertes', 2);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'webhook_backlog', 'niveau' => 'critical']);
    }

    public function test_mission_bloquee_retenant_des_fonds(): void
    {
        $mission = Mission::factory()->create();

        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::stuckMissionHoldingFunds($mission),
            fn () => BusinessAlerts::webhookBacklog(999),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'stuck_mission_holding_funds');
        $this->uneCapture($resultat['captures'], 'webhook_backlog');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(
            ['mission_id', 'status', 'booking_id', 'planned_start_at'],
            array_keys($mesuree->context),
        );
        $this->assertSame($mission->id, $mesuree->context['mission_id']);
        $this->assertSame($mission->status, $mesuree->context['status']);
        $this->assertSame($mission->booking_id, $mesuree->context['booking_id']);
        $this->assertSame((string) $mission->planned_start_at, $mesuree->context['planned_start_at']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'stuck_mission_holding_funds');
        $this->messageSentryPour($resultat['sentry']->captured, 'webhook_backlog');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseCount('business_alertes', 2);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'stuck_mission_holding_funds', 'niveau' => 'critical']);
    }

    public function test_reconciliation_qui_diverge(): void
    {
        $detail = ['stripe_total' => 10000, 'db_total' => 9950, 'delta' => 50, 'period' => '2026-05'];

        $resultat = $this->observeDeuxAlertes(
            fn () => BusinessAlerts::reconciliationDivergence($detail),
            fn () => BusinessAlerts::webhookBacklog(999),
        );

        $mesuree = $this->uneCapture($resultat['captures'], 'reconciliation_divergence');
        $this->uneCapture($resultat['captures'], 'webhook_backlog');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(['detail'], array_keys($mesuree->context));
        $this->assertSame($detail, $mesuree->context['detail']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'reconciliation_divergence');
        $this->messageSentryPour($resultat['sentry']->captured, 'webhook_backlog');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseCount('business_alertes', 2);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'reconciliation_divergence', 'niveau' => 'critical']);
    }
}
