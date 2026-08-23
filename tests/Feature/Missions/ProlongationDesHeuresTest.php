<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Services\Missions\HourlyExtensionService;
use App\Services\Missions\HourlyMissionClock;
use App\Services\OrderEngine\HourlyRateResolver;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** PROLONGER — acheter du temps en plus, au tarif normal, avant ou pendant l'intervention. */
class ProlongationDesHeuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_prolonger_repousse_lecheance(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 100);

        $etat = app(HourlyExtensionService::class)->prolonger($mission->booking, 60);

        $this->assertSame(240, $etat['purchased_minutes']);
        $this->assertSame(140, $etat['remaining_minutes'], 'Une heure de plus : 140 min restantes au lieu de 80.');
        $this->assertSame(0, $etat['overtime_amount_cents']);
    }

    /** LE TÉMOIN CENTRAL — le tarif déduit ne bouge pas d'un centime. */
    public function test_prolonger_ne_deplace_pas_la_base_du_tarif(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 30);
        $booking = $mission->booking;

        $avant = app(HourlyRateResolver::class)->tarifEffectifDeLaReservation($booking);
        $this->assertSame(5850, $avant);

        app(HourlyExtensionService::class)->prolonger($booking, 120);

        $this->assertSame(180, (int) $booking->refresh()->duree_estimee, 'La base du tarif ne se prolonge pas.');
        $this->assertSame(5850, app(HourlyRateResolver::class)->tarifEffectifDeLaReservation($booking->refresh()));
    }

    /** PROLONGER PENDANT LA FRANCHISE ANNULE LE DÉPASSEMENT. */
    public function test_prolonger_pendant_la_franchise_efface_la_penalite(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 188);

        $avant = app(HourlyMissionClock::class)->etat($mission);
        $this->assertSame(8, $avant['overrun_minutes'], 'On déborde déjà — sans pénalité, la franchise court.');

        $etat = app(HourlyExtensionService::class)->prolonger($mission->booking, 60);

        $this->assertSame(0, $etat['overrun_minutes']);
        $this->assertSame(0, $etat['overtime_amount_cents']);
        $this->assertSame(52, $etat['remaining_minutes']);
    }

    /** LA FENÊTRE SE FERME À LA FIN DE LA FRANCHISE. */
    public function test_apres_la_franchise_la_prolongation_est_refusee(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 220);

        $etat = app(HourlyExtensionService::class)->etatDeLaProlongation($mission->booking);
        $this->assertFalse($etat['allowed']);
        $this->assertStringContainsString('déjà en cours de facturation', (string) $etat['reason']);

        $this->expectException(RuntimeException::class);
        app(HourlyExtensionService::class)->prolonger($mission->booking, 60);
    }

    /** Avant le démarrage, rien ne court : la prolongation est toujours « à temps ». */
    public function test_avant_le_demarrage_on_peut_prolonger(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 0);
        $mission->forceFill(['actual_start_at' => null, 'status' => MissionStatus::ASSIGNED])->save();

        $etat = app(HourlyExtensionService::class)->etatDeLaProlongation($mission->booking);

        $this->assertTrue($etat['allowed']);
        $this->assertNull($etat['reason']);
    }

    public function test_une_intervention_terminee_ne_se_prolonge_plus(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 60);
        $mission->forceFill(['status' => MissionStatus::COMPLETED])->save();

        $this->assertFalse(
            app(HourlyExtensionService::class)->etatDeLaProlongation($mission->booking)['allowed'],
        );
    }

    /** Le pas est celui du sélecteur de commande : on n'achète pas 7 minutes. */
    public function test_la_prolongation_respecte_le_pas(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tranches de 30 minutes');

        app(HourlyExtensionService::class)->prolonger($mission->booking, 7);
    }

    /** Le plafond de la prestation borne aussi la prolongation. */
    public function test_le_plafond_de_la_prestation_borne_la_prolongation(): void
    {
        config()->set('order_engine.hourly_max_hours', 4.0);

        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 10);

        $this->assertSame(60, app(HourlyExtensionService::class)->etatDeLaProlongation($mission->booking)['max_minutes']);

        $this->expectException(RuntimeException::class);
        app(HourlyExtensionService::class)->prolonger($mission->booking, 120);
    }

    /** La trace du geste — sans elle, un litige sur la facture oppose deux souvenirs. */
    public function test_la_prolongation_laisse_une_trace_chiffree(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 10);

        app(HourlyExtensionService::class)->prolonger($mission->booking, 60);

        $journal = (array) $mission->booking->refresh()->metadata;
        $lignes = $journal['prolongations'] ?? [];

        $this->assertCount(1, $lignes);
        $this->assertSame(60, $lignes[0]['minutes']);
        $this->assertSame(180, $lignes[0]['minutes_avant']);
        $this->assertSame(240, $lignes[0]['minutes_apres']);
        $this->assertSame(5850, $lignes[0]['tarif_horaire_cents']);
        $this->assertSame(5850, $lignes[0]['montant_cents'], 'Une heure au tarif NORMAL, sans majoration.');
    }

    /** TÉMOIN : une prestation au forfait n'a pas de prolongation du tout. */
    public function test_un_forfait_na_pas_de_prolongation(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 10);
        $mission->booking->resolveTrade()?->forceFill(['hourly_billing' => false])->save();

        $this->assertNull(
            app(HourlyExtensionService::class)->etatDeLaProlongation($mission->booking->refresh()),
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    private function missionAuTemps(int $minutesAchetees, int $prixCents, int $ecouleesMinutes): Mission
    {
        $scenario = SpineScenario::make()->build();

        $scenario->booking->resolveTrade()?->forceFill([
            'hourly_billing' => true,
            'default_hourly_rate' => 45,
        ])->save();

        $scenario->booking->forceFill([
            'purchased_minutes' => $minutesAchetees,
            'duree_estimee' => $minutesAchetees,
            'devis_estime' => $prixCents / 100,
            'payment_amount_cents' => $prixCents,
        ])->save();

        $mission = $scenario->mission;
        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subMinutes($ecouleesMinutes),
            'actual_end_at' => null,
        ])->save();

        return $mission->refresh()->load('booking');
    }
}
